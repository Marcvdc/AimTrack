<?php

namespace App\Services\Vision;

use App\Enums\TargetType;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bepaalt de schoten op een foto van een schietkaart.
 *
 * Dit is de PHP-poort van het vision-direct pad uit de Python-service. Dat pad
 * analyseert de RUWE foto: geen homografie, geen perspectiefcorrectie, geen
 * klassieke beeldbewerking. Het model bepaalt zelf het middelpunt van de roos en
 * leest de ring direct van de gedrukte ringen af, wat op zwaar geplakte rozen
 * betrouwbaarder bleek dan de kalibratie die dat pad ooit voorafging.
 *
 * De call loopt via Http:: en niet via de Anthropic SDK, omdat ShooterCoach dat
 * ook doet en composer.json geen SDK kent. Dat is een bewuste keuze om de
 * afhankelijkheden van de applicatie niet te wijzigen.
 */
class TargetPhotoAnalyzer
{
    public function __construct(
        private readonly TargetPhotoPreparer $preparer,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $maxTokens,
        private readonly string $anthropicVersion,
        private readonly int $connectTimeout,
        private readonly int $timeout,
        private readonly string $effort = 'high',
    ) {}

    public static function make(): self
    {
        $config = config('vision');

        return new self(
            preparer: new TargetPhotoPreparer(
                maxDimension: (int) ($config['max_image_dimension'] ?? 1500),
                jpegQuality: (int) ($config['jpeg_quality'] ?? 90),
            ),
            model: (string) ($config['model'] ?? 'claude-opus-5'),
            baseUrl: (string) ($config['base_url'] ?? 'https://api.anthropic.com'),
            maxTokens: (int) ($config['max_tokens'] ?? 16000),
            anthropicVersion: (string) ($config['anthropic_version'] ?? '2023-06-01'),
            connectTimeout: (int) ($config['connect_timeout'] ?? 10),
            timeout: (int) ($config['timeout'] ?? 180),
            effort: (string) ($config['effort'] ?? 'high'),
        );
    }

    /**
     * @throws VisionException
     */
    public function analyze(
        string $imagePath,
        TargetType $targetType,
        ?int $expectedShotCount,
        ?string $apiKey,
    ): VisionAnalysisResult {
        if (blank($apiKey)) {
            throw new VisionException('Geen Claude-key beschikbaar voor de beeldherkenning.');
        }

        $photo = $this->preparer->prepare($imagePath);
        $payload = $this->payload($photo, $targetType, $expectedShotCount);
        [$text, $usage] = $this->send($payload, $apiKey);

        return $this->toResult($this->decode($text), $targetType, $expectedShotCount, $photo, $usage);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: TokenUsage}
     *
     * @throws VisionException
     */
    private function send(array $payload, string $apiKey): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $this->anthropicVersion,
                ])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->post('/v1/messages', $payload);
        } catch (Throwable $exception) {
            throw new VisionException('Netwerkfout bij de beeldherkenning: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new VisionException(sprintf(
                'Beeldherkenning gaf HTTP %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            ));
        }

        $body = $response->json();

        if (($body['stop_reason'] ?? null) === 'refusal') {
            throw new VisionException('De beeldherkenning is geweigerd door het model.');
        }

        /*
         * Op Opus 5 staat adaptief denken standaard aan, dus content[0] is een
         * thinking-blok en niet de tekst. Het JSON-antwoord zit in het eerste
         * text-blok, waar dat ook staat.
         */
        $usage = TokenUsage::fromArray($body['usage'] ?? null);

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                return [$block['text'], $usage];
            }
        }

        throw new VisionException('Geen tekstantwoord van het vision-model.');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws VisionException
     */
    private function decode(string $raw): array
    {
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new VisionException('Ongeldige JSON van het vision-model.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toResult(
        array $data,
        TargetType $targetType,
        ?int $expectedShotCount,
        PreparedPhoto $photo,
        TokenUsage $usage,
    ): VisionAnalysisResult {
        $shots = [];

        foreach ($data['shots'] ?? [] as $shot) {
            if (is_array($shot)) {
                $shots[] = DetectedShot::fromArray($shot);
            }
        }

        $rejected = [];

        foreach ($data['rejected'] ?? [] as $item) {
            if (is_array($item)) {
                $rejected[] = RejectedCandidate::fromArray($item);
            }
        }

        return new VisionAnalysisResult(
            targetType: $targetType,
            shots: $shots,
            rejected: $rejected,
            frame: TargetFrame::fromArray(is_array($data['target'] ?? null) ? $data['target'] : null),
            orientationNote: is_string($data['orientation_note'] ?? null) ? $data['orientation_note'] : '',
            overallConfidence: max(0.0, min(1.0, (float) ($data['overall_confidence'] ?? 0.0))),
            countMatchesExpected: (bool) ($data['count_matches_expected'] ?? false),
            expectedShotCount: $expectedShotCount,
            model: $this->model,
            imageWidth: $photo->width,
            imageHeight: $photo->height,
            usage: $usage,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PreparedPhoto $photo, TargetType $targetType, ?int $expectedShotCount): array
    {
        return [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $this->systemPrompt($targetType, $expectedShotCount),
            'thinking' => ['type' => 'adaptive'],
            'output_config' => [
                'effort' => $this->effort,
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $this->schema(),
                ],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $photo->mediaType,
                                'data' => $photo->toBase64(),
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Rapporteer alle verse kogelgaten als JSON volgens het schema.',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function systemPrompt(TargetType $targetType, ?int $expectedShotCount): string
    {
        $countLine = $expectedShotCount !== null
            ? sprintf(
                'Er zijn precies %d schoten gelost in deze beurt; rapporteer er bij voorkeur exact %d, '
                .'maar rapporteer NOOIT iets wat geen vers kogelgat is alleen om dat aantal te halen.',
                $expectedShotCount,
                $expectedShotCount,
            )
            : 'Het aantal schoten is onbekend; rapporteer elk vers kogelgat waar je zeker van bent.';

        return implode(' ', [
            sprintf(
                'Je analyseert een ONBEWERKTE foto van een %s schietkaart; het perspectief is NIET gecorrigeerd, '
                .'dus de kaart kan schuin of gedraaid in beeld staan.',
                $targetType->label(),
            ),
            'Bepaal zelf het middelpunt van het zwarte richtvlak en lees de concentrische ringen af.',
            'Zoek eerst de BUITENSTE GEDRUKTE RING die je op de kaart kunt zien, en bepaal welk '
            .'ringnummer daarbij hoort. Let op: dat is lang niet altijd ring 1. Er zijn kaarten '
            .'waarvan de buitenste gedrukte ring een 6 is. Lees dat nummer af van de gedrukte '
            .'ringcijfers en gok het niet.',
            'Geef in "target" het middelpunt in pixels, het nummer van die buitenste gedrukte ring '
            .'("outer_ring_number") en de straal van de BUITENrand van diezelfde ring in pixels '
            .'("outer_ring_radius_px"). Verwar die rand niet met de rand van het zwarte vlak: het '
            .'zwart beslaat maar een deel van de ringen.',
            'Rapporteer per VERS kogelgat: (1) de positie GENORMALISEERD ten opzichte van het '
            .'middelpunt, waarbij (0,0) het midden is en straal 1.0 de buitenrand van diezelfde '
            .'buitenste gedrukte ring (rechts is +x_norm, omlaag is +y_norm); (2) de RING (1 tot 10, '
            .'of 0 als het schot buiten de buitenste ring valt) die je DIRECT van de gedrukte ringen '
            .'afleest en NIET uit de afstand berekent, want door het perspectief is de afstand geen '
            .'betrouwbare ringmaat.',
            'Oude treffers zijn dichtgeplakt met lichte (witte of lichtblauwe) plakkers op het papier of met ZWARTE '
            .'plakkers op het zwarte vlak. Dat zijn GEEN schoten.',
            'Rapporteer UITSLUITEND verse kogelgaten: donkere perforaties op licht papier, of lichte, gescheurde '
            .'kraters waar VERS door het zwart is geschoten.',
            'Zet elke ronde vlek die je bewust NIET als schot telt in "rejected", met de reden. Dat geldt voor '
            .'plakkers, gedrukte ringcijfers (zoals 8, 9, 10), ringlijnen, kartonscheuren en tape. Die lijst is '
            .'belangrijk: daarmee is te zien wat je hebt afgewogen.',
            'Liever minder gaten rapporteren waar je zeker van bent dan twijfelgevallen meetellen. Zet de '
            .'confidence onder 0.4 bij twijfel.',
            $countLine,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'target' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'center_x_px' => ['type' => 'number'],
                        'center_y_px' => ['type' => 'number'],
                        'outer_ring_number' => ['type' => 'integer'],
                        'outer_ring_radius_px' => ['type' => 'number'],
                    ],
                    'required' => ['center_x_px', 'center_y_px', 'outer_ring_number', 'outer_ring_radius_px'],
                ],
                'shots' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'x_norm' => ['type' => 'number'],
                            'y_norm' => ['type' => 'number'],
                            'ring' => ['type' => 'integer'],
                            'confidence' => ['type' => 'number'],
                            'kind' => ['type' => 'string', 'enum' => ['hole', 'uncertain']],
                        ],
                        'required' => ['x_norm', 'y_norm', 'ring', 'confidence', 'kind'],
                    ],
                ],
                'rejected' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'x_px' => ['type' => 'integer'],
                            'y_px' => ['type' => 'integer'],
                            'kind' => [
                                'type' => 'string',
                                'enum' => ['paster', 'printed_number', 'ring_line', 'tear', 'tape', 'other'],
                            ],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['x_px', 'y_px', 'kind', 'reason'],
                    ],
                ],
                'orientation_note' => ['type' => 'string'],
                'overall_confidence' => ['type' => 'number'],
                'count_matches_expected' => ['type' => 'boolean'],
            ],
            'required' => [
                'target', 'shots', 'rejected', 'orientation_note', 'overall_confidence', 'count_matches_expected',
            ],
        ];
    }
}
