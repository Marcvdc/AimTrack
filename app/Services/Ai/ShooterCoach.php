<?php

namespace App\Services\Ai;

use App\Models\AiReflection;
use App\Models\AiWeaponInsight;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\Weapon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ShooterCoach
{
    public function __construct(
        private readonly string $driver = '',
        private readonly string $model = '',
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
    ) {
    }

    public static function make(): self
    {
        $config = config('ai');

        return new self(
            driver: $config['driver'] ?? 'openai',
            model: $config['model'] ?? 'gpt-4.1-mini',
            baseUrl: $config['base_url'] ?? null,
            apiKey: $config['api_key'] ?? env('OPENAI_API_KEY'),
        );
    }

    public function generateSessionReflection(Session $session): AiReflection
    {
        $prompt = $this->buildSessionPrompt($session);
        $rawContent = $this->callModel($prompt);
        $parsed = $this->parseReflectionPayload($rawContent);

        return $session->aiReflection()->updateOrCreate([], $parsed);
    }

    public function generateWeaponInsight(Weapon $weapon): AiWeaponInsight
    {
        $prompt = $this->buildWeaponPrompt($weapon);
        $rawContent = $this->callModel($prompt);
        $parsed = $this->parseWeaponPayload($rawContent);

        return $weapon->aiWeaponInsight()->updateOrCreate([], $parsed);
    }

    public function answerCoachQuestion($user, string $question): string
    {
        $prompt = $this->buildCoachPrompt((string) $question);

        return $this->callModel($prompt);
    }

    private function callModel(string $prompt): string
    {
        if (blank($this->apiKey)) {
            Log::error('AI: geen API key geconfigureerd.');

            return 'AI-configuratie ontbreekt. Voeg een API key toe om antwoorden te genereren.';
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemContext(),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = Http::baseUrl($this->baseUrl ?: 'https://api.openai.com/v1')
                ->timeout(30)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post('/chat/completions', $payload);

            if (! $response->successful()) {
                Log::error('AI: fout bij API-call', ['status' => $response->status(), 'body' => $response->body()]);

                return 'Geen AI-antwoord beschikbaar (API-fout).';
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            return is_string($content) ? $content : json_encode($content) ?: '';
        } catch (Throwable $exception) {
            Log::error('AI: exception tijdens API-call', [
                'message' => $exception->getMessage(),
            ]);

            return 'Geen AI-antwoord beschikbaar (technical error).';
        }
    }

    private function buildSessionPrompt(Session $session): string
    {
        $weaponLines = $session->sessionWeapons
            ->map(fn (SessionWeapon $entry) => sprintf(
                '- %s | afstand: %sm | patronen: %s | munitie: %s | afwijking: %s | groepering: %s',
                $entry->weapon?->name ?? 'Onbekend wapen',
                $entry->distance_m ?? 'n.v.t.',
                $entry->rounds_fired ?? '0',
                $entry->ammo_type ?? 'onbekend',
                $entry->deviation ?? 'n.v.t.',
                Str::limit($entry->group_quality_text ?? '-', 120)
            ))
            ->filter()
            ->values()
            ->join("\n");

        $manualReflection = $session->manual_reflection ? "Handmatige reflectie gebruiker: {$session->manual_reflection}" : 'Geen handmatige reflectie ingevoerd.';

        return trim(<<<PROMPT
Je bent een AI-coach voor sportschutters. Geef veilige, constructieve adviezen, geen illegale of gevaarlijke tips.

Context sessie:
- Datum: {$session->date?->format('Y-m-d')}
- Locatie/baan: {$session->range_name} ({$session->location})
- Ruwe notities: {$session->notes_raw}
- Wapenregels:
{$weaponLines}
- {$manualReflection}

Gevraagde output: JSON met velden summary (string), positives (array van strings), improvements (array van strings), next_focus (string).
PROMPT);
    }

    private function buildWeaponPrompt(Weapon $weapon): string
    {
        $recentEntries = $weapon->sessionWeapons()->latest('created_at')->take(5)->get();
        $entriesText = $recentEntries
            ->map(fn (SessionWeapon $entry) => sprintf(
                '- %s op %s m, %s schoten, afwijking: %s, notitie: %s',
                $entry->session?->date?->format('Y-m-d') ?? 'onbekende datum',
                $entry->distance_m ?? 'n.v.t.',
                $entry->rounds_fired ?? '0',
                $entry->deviation ?? 'n.v.t.',
                Str::limit($entry->group_quality_text ?? '-', 120)
            ))
            ->filter()
            ->values()
            ->join("\n");

        return trim(<<<PROMPT
Je bent een AI-coach voor sportschutters. Analyseer trends per wapen en geef veilige aanbevelingen.

Wapen:
- Naam: {$weapon->name}
- Type: {$weapon->weapon_type?->value}
- Kaliber: {$weapon->caliber}
- Serienummer: {$weapon->serial_number}
- Opslaglocatie: {$weapon->storage_location}

Recente sessies (max 5):
{$entriesText}

Gevraagde output: JSON met velden summary (string), patterns (array van strings), suggestions (array van strings).
PROMPT);
    }

    private function buildCoachPrompt(string $question): string
    {
        return trim(<<<PROMPT
Je bent een persoonlijke AI-schietcoach. Antwoord beknopt in het Nederlands, focus op veiligheid en legaal handelen.
Vraag: {$question}

Antwoord in maximaal 120 woorden.
PROMPT);
    }

    private function parseReflectionPayload(string $raw): array
    {
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return [
                'summary' => $raw,
                'positives' => [],
                'improvements' => [],
                'next_focus' => '',
            ];
        }

        return [
            'summary' => Arr::get($data, 'summary', $raw),
            'positives' => $this->normalizeList(Arr::get($data, 'positives', [])),
            'improvements' => $this->normalizeList(Arr::get($data, 'improvements', [])),
            'next_focus' => Arr::get($data, 'next_focus', ''),
        ];
    }

    private function parseWeaponPayload(string $raw): array
    {
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return [
                'summary' => $raw,
                'patterns' => [],
                'suggestions' => [],
            ];
        }

        return [
            'summary' => Arr::get($data, 'summary', $raw),
            'patterns' => $this->normalizeList(Arr::get($data, 'patterns', [])),
            'suggestions' => $this->normalizeList(Arr::get($data, 'suggestions', [])),
        ];
    }

    private function normalizeList(mixed $value): array
    {
        return collect($value)
            ->filter()
            ->map(fn ($item) => is_string($item) ? $item : json_encode($item))
            ->values()
            ->all();
    }

    private function systemContext(): string
    {
        return 'Je bent een zorgvuldige AI-schietcoach. Weiger illegale of gevaarlijke instructies en focus op veiligheid, discipline en wettelijk toegestane adviezen.';
    }
}
