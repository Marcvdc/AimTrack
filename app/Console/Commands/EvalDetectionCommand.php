<?php

namespace App\Console\Commands;

use App\Services\Vision\DetectionEvalHarness;
use App\Services\Vision\DetectionManifest;
use App\Services\Vision\DetectionMetrics;
use App\Services\Vision\DetectionReportWriter;
use App\Services\Vision\ShotSelector;
use App\Services\Vision\TargetOverlayRenderer;
use App\Services\Vision\TargetPhotoAnalyzer;
use App\Services\Vision\TargetPhotoPreparer;
use App\Services\Vision\VisionException;
use Illuminate\Console\Command;

/**
 * Meet de detectie op een gelabelde set roosfoto's.
 *
 * Dit commando doet echte API-calls en kost dus geld: aantal foto's maal het
 * aantal runs. Daarom zit er een bevestiging op, tenzij --force is meegegeven.
 */
class EvalDetectionCommand extends Command
{
    protected $signature = 'aimtrack:eval-detectie
        {manifest : Pad naar het manifest met de gelabelde foto\'s}
        {--runs=3 : Aantal onafhankelijke runs per foto}
        {--out= : Map voor het rapport en de overlays (standaard storage/app/detectie-metingen/<datum>)}
        {--no-overlays : Sla het tekenen van de overlays over}
        {--hervat : Sla foto\'s over die al in het rapport in de --out map staan}
        {--force : Vraag niet om bevestiging}';

    protected $description = 'Draait de foto-naar-schoten detectie over een gelabelde meetset en rapporteert de nauwkeurigheid';

    public function handle(DetectionManifest $manifest, DetectionReportWriter $writer): int
    {
        $this->raiseMemoryLimit();

        $runs = max(1, (int) $this->option('runs'));

        try {
            $entries = $manifest->load((string) $this->argument('manifest'));
        } catch (VisionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $apiKey = (string) config('vision.api_key');

        if ($apiKey === '') {
            $this->error('Geen ANTHROPIC_API_KEY gevonden. Zet hem in .env van deze worktree.');

            return self::FAILURE;
        }

        $jsonPath = $this->outputDirectory().'/rapport.json';
        $alreadyDone = $this->option('hervat') ? $this->eerderVerwerkt($jsonPath) : [];
        $done = array_column($alreadyDone, 'file');
        $todo = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ! in_array(basename((string) $entry['path']), $done, true),
        ));

        if ($alreadyDone !== []) {
            $this->info(sprintf(
                '%d foto(s) al gemeten in een eerdere run, die worden overgeslagen.',
                count($alreadyDone),
            ));
        }

        if ($todo === []) {
            $this->info('Alle foto\'s uit het manifest zijn al gemeten. Niets te doen.');

            return self::SUCCESS;
        }

        $calls = count($todo) * $runs;

        $this->line(sprintf(
            'Meetset: %d foto(s), %d run(s) per foto, %d API-call(s) op model %s (effort %s).',
            count($todo),
            $runs,
            $calls,
            (string) config('vision.model'),
            (string) config('vision.effort'),
        ));
        $this->line($this->kostenIndicatie($jsonPath, $calls));

        if (! $this->option('force') && ! $this->confirm('Doorgaan? Dit doet echte, betaalde API-calls.', false)) {
            $this->warn('Afgebroken.');

            return self::SUCCESS;
        }

        $outputDirectory = $this->outputDirectory();
        $overlayDirectory = $this->option('no-overlays') ? null : $outputDirectory.'/overlays';

        $report = $this->harness()->run(
            entries: $todo,
            apiKey: $apiKey,
            runs: $runs,
            overlayDirectory: $overlayDirectory,
            progress: function (string $file, int $run, int $total): void {
                $this->line(sprintf('  %s: run %d van %d', $file, $run, $total));
            },
            alreadyDone: $alreadyDone,
            onPhotoDone: function (array $partial) use ($writer, $outputDirectory): void {
                $writer->writeJson($partial, $outputDirectory.'/rapport.json');
                $writer->writeMarkdown($partial, $outputDirectory.'/rapport.md');
            },
        );

        $writer->writeJson($report, $outputDirectory.'/rapport.json');
        $writer->writeMarkdown($report, $outputDirectory.'/rapport.md');

        $this->newLine();
        $this->line($writer->toMarkdown($report));
        $this->info("Rapport weggeschreven naar {$outputDirectory}");

        return self::SUCCESS;
    }

    /**
     * Een kostenindicatie die op meting berust en niet op een natte vinger.
     *
     * Vooraf schatten wat een vision-call kost, gaat mis: het denkwerk zit in de
     * output-tokens en varieert sterk per foto. Daarom wordt de prijs per call van
     * een eerdere meting gebruikt als die er is, en anders eerlijk gezegd dat het
     * onbekend is.
     */
    private function kostenIndicatie(string $jsonPath, int $calls): string
    {
        $perCall = null;

        if (is_readable($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            $perCall = is_array($decoded) ? ($decoded['kosten']['per_call_usd'] ?? null) : null;
        }

        if (! is_numeric($perCall)) {
            return 'Kosten: onbekend. Een vision-call met denkwerk liep in eerdere metingen sterk '
                .'uiteen; na afloop staat de gemeten prijs in het rapport.';
        }

        return sprintf(
            'Kosten: vorige meting kostte $%s per call, dus ruwweg $%s voor deze %d call(s).',
            number_format((float) $perCall, 3, ',', '.'),
            number_format((float) $perCall * $calls, 2, ',', '.'),
            $calls,
        );
    }

    private function outputDirectory(): string
    {
        return rtrim(
            (string) ($this->option('out') ?: storage_path('app/detectie-metingen/'.now()->format('Y-m-d_His'))),
            '/',
        );
    }

    /**
     * De geslaagde foto's uit een eerder, mogelijk afgebroken rapport. Omdat er na
     * elke foto wordt weggeschreven, is dit altijd een geldig rapport tot en met de
     * laatst voltooide foto.
     *
     * Foto's waarvan elke run op een fout eindigde tellen NIET als verwerkt. Een
     * netwerk-timeout is meestal van voorbijgaande aard, en een hervatting die de
     * mislukte foto's overslaat laat juist de gaten staan die je wilde dichten.
     *
     * @return list<array<string, mixed>>
     */
    private function eerderVerwerkt(string $jsonPath): array
    {
        if (! is_readable($jsonPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($jsonPath), true);

        if (! is_array($decoded) || ! is_array($decoded['photos'] ?? null)) {
            return [];
        }

        $geslaagd = array_filter(
            $decoded['photos'],
            static fn (mixed $photo): bool => is_array($photo) && array_any(
                $photo['runs'] ?? [],
                static fn (mixed $run): bool => is_array($run) && ! isset($run['error']),
            ),
        );

        return array_values($geslaagd);
    }

    /**
     * Vangnet voor omgevingen zonder ImageMagick, waar GD het werk doet. Die pakt
     * een 12MP-telefoonfoto uit tot ruim 48 MB aan bitmap en houdt tijdens het
     * verkleinen origineel en kopie tegelijk vast, met een gemeten piek van 139 MB
     * tegen een standaardlimiet van 128 MB. Draait ImageMagick wel, dan gebeurt het
     * uitpakken buiten PHP en blijft de piek op 42 MB.
     */
    private function raiseMemoryLimit(): void
    {
        $limit = (string) config('vision.cli_memory_limit');

        if ($limit !== '') {
            ini_set('memory_limit', $limit);
        }
    }

    private function harness(): DetectionEvalHarness
    {
        $preparer = new TargetPhotoPreparer(
            maxDimension: (int) config('vision.max_image_dimension'),
            jpegQuality: (int) config('vision.jpeg_quality'),
        );

        return new DetectionEvalHarness(
            analyzer: TargetPhotoAnalyzer::make(),
            selector: new ShotSelector((float) config('vision.min_shot_confidence')),
            metrics: new DetectionMetrics,
            overlays: new TargetOverlayRenderer($preparer),
        );
    }
}
