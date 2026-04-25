<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CalibrateTarget extends Command
{
    protected $signature = 'aimtrack:calibrate
                            {foto : Pad naar de roos-foto (bijv. /home/marc/roos.jpg)}
                            {--type=kkp_25m : Rozentype (kkp_25m, gkp_25m, kkg_50m, kkg_100m, gkg_100m)}
                            {--output= : Opslaan als (standaard: storage/app/calibrated_<timestamp>.png)}';

    protected $description = 'Stuur een roos-foto naar de Python service en sla het gecorrigeerde resultaat op als PNG.';

    public function handle(): int
    {
        $fotoPath = $this->argument('foto');
        $targetType = $this->option('type');

        $geldigeTypes = ['kkp_25m', 'gkp_25m', 'kkg_50m', 'kkg_100m', 'gkg_100m'];

        if (! in_array($targetType, $geldigeTypes)) {
            $this->error("Onbekend rozentype '{$targetType}'. Geldige types: ".implode(', ', $geldigeTypes));

            return self::FAILURE;
        }

        if (! file_exists($fotoPath)) {
            $this->error("Bestand niet gevonden: {$fotoPath}");

            return self::FAILURE;
        }

        $outputPath = $this->option('output')
            ?? storage_path('app/calibrated_'.now()->format('Ymd_His').'.png');

        $serviceUrl = config('services.image_processor.url');

        $this->info("Foto:       {$fotoPath}");
        $this->info("Rozentype:  {$targetType}");
        $this->info("Service:    {$serviceUrl}");
        $this->newLine();
        $this->line('Kalibratie bezig...');

        try {
            $response = Http::timeout(30)
                ->attach('file', file_get_contents($fotoPath), basename($fotoPath))
                ->post("{$serviceUrl}/api/v1/calibrate", [
                    'target_type' => $targetType,
                ]);
        } catch (\Exception $e) {
            $this->error('Verbinding met Python service mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->status() === 422) {
            $body = $response->json();
            $this->error('Kalibratie mislukt: '.($body['message'] ?? 'Onbekende fout'));
            $this->line('  Ringen gevonden: '.($body['rings_detected'] ?? '?'));
            $this->line('  Tip: zorg dat de roos niet volledig bedekt is met plakkers.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Service fout ('.$response->status().'): '.$response->body());

            return self::FAILURE;
        }

        $data = $response->json();

        $pngData = base64_decode($data['canonical_image_b64']);
        file_put_contents($outputPath, $pngData);

        $this->info('Kalibratie geslaagd!');
        $this->newLine();
        $this->table(
            ['Eigenschap', 'Waarde'],
            [
                ['Rozentype', $data['target_type']],
                ['Methode', $data['calibration_method']],
                ['Ringen gevonden', $data['rings_detected']],
                ['Betrouwbaarheid', round($data['calibration_confidence'] * 100, 1).'%'],
                ['RMS fout', $data['rms_error_mm'].' mm'],
                ['Output', $outputPath],
            ]
        );

        $this->newLine();
        $this->line('Open het resultaat met:');
        $this->comment("  xdg-open {$outputPath}");

        return self::SUCCESS;
    }
}
