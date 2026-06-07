<?php

namespace App\Console\Commands;

use App\Models\SessionShot;
use Illuminate\Console\Command;

class ConvertPhotoShotCoordinates extends Command
{
    protected $signature = 'shots:convert-photo-coordinates {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Convert photo-detected shot coordinates from [-1,1] to [0,1] range';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find all photo-detected shots with coordinates outside [0, 1] range
        $shots = SessionShot::where('source', 'photo_detected')
            ->where(function ($query) {
                $query->where('x_normalized', '<', 0)
                    ->orWhere('x_normalized', '>', 1)
                    ->orWhere('y_normalized', '<', 0)
                    ->orWhere('y_normalized', '>', 1);
            })
            ->get();

        if ($shots->isEmpty()) {
            $this->info('No shots found that need conversion.');

            return self::SUCCESS;
        }

        $this->info("Found {$shots->count()} shots that need coordinate conversion.");

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }

        $converted = 0;

        foreach ($shots as $shot) {
            $oldX = $shot->x_normalized;
            $oldY = $shot->y_normalized;

            // Convert from [-1, 1] to [0, 1]
            $newX = ($oldX + 1) / 2;
            $newY = ($oldY + 1) / 2;

            // Recalculate distance and ring/score
            $xFromCenter = $newX - 0.5;
            $yFromCenter = $newY - 0.5;
            $distance = sqrt($xFromCenter ** 2 + $yFromCenter ** 2);

            $this->line("Shot #{$shot->id}: ({$oldX}, {$oldY}) → ({$newX}, {$newY})");

            if (! $dryRun) {
                $shot->update([
                    'x_normalized' => $newX,
                    'y_normalized' => $newY,
                    'distance_from_center' => $distance,
                    'ring' => $this->calculateRing($xFromCenter, $yFromCenter),
                    'score' => $this->calculateScore($xFromCenter, $yFromCenter),
                    'metadata' => array_merge($shot->metadata ?? [], [
                        'coordinate_converted' => true,
                        'original_x' => $oldX,
                        'original_y' => $oldY,
                    ]),
                ]);

                $converted++;
            }
        }

        if ($dryRun) {
            $this->info("Would convert {$shots->count()} shots.");
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->info("Successfully converted {$converted} shots.");
        }

        return self::SUCCESS;
    }

    private function calculateRing(float $x, float $y): ?int
    {
        $distance = sqrt($x ** 2 + $y ** 2);

        $rings = [
            10 => 0.0,
            9 => 0.05,
            8 => 0.1,
            7 => 0.15,
            6 => 0.2,
            5 => 0.25,
            4 => 0.3,
            3 => 0.35,
            2 => 0.4,
            1 => 0.45,
        ];

        foreach ($rings as $ring => $maxDistance) {
            if ($distance <= $maxDistance) {
                return $ring;
            }
        }

        return 0;
    }

    private function calculateScore(float $x, float $y): int
    {
        return $this->calculateRing($x, $y) ?? 0;
    }
}
