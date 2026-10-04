<?php

namespace App\Console\Commands;

use App\Services\Vision\CorrectionExportService;
use Illuminate\Console\Command;

/**
 * Exporteert de bevestigde beurten als meetset voor aimtrack:eval-detectie.
 */
class ExportCorrectionSetCommand extends Command
{
    protected $signature = 'aimtrack:meetset-exporteren
        {--disk=local : De schijf waarop de beurtfotos staan}
        {--out= : Waar het manifest heen moet (standaard storage/app/meetset/manifest.json)}';

    protected $description = 'Bouwt een meetset-manifest uit de beurten die een mens heeft bevestigd';

    public function handle(CorrectionExportService $service): int
    {
        $manifest = $service->build((string) $this->option('disk'));
        $path = (string) ($this->option('out') ?: storage_path('app/meetset/manifest.json'));

        if ($manifest['photos'] === []) {
            $this->warn('Geen bevestigde beurten met een bewaarde foto gevonden. '
                .'Bevestig eerst een paar beurten op het schotbord.');

            return self::SUCCESS;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $gecorrigeerd = array_sum(array_column(array_column($manifest['photos'], 'bron'), 'gecorrigeerd'));

        $this->info(sprintf(
            '%d foto(s) geëxporteerd, waarvan %d schot(en) met de hand gecorrigeerd.',
            count($manifest['photos']),
            $gecorrigeerd,
        ));

        if ($manifest['overgeslagen'] !== []) {
            $this->warn(sprintf('%d beurt(en) overgeslagen omdat de foto weg is.', count($manifest['overgeslagen'])));
        }

        $this->line("Manifest: {$path}");

        return self::SUCCESS;
    }
}
