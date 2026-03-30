<?php

namespace App\Jobs;

use App\Models\Session;
use App\Models\SessionShot;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeTurnPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120; // 120 seconds timeout for image processing

    public int $tries = 3; // Retry 3 times on failure

    public int $backoff = 10; // Wait 10 seconds between retries

    public function __construct(
        private Session $session,
        private int $turnIndex,
        private string $photoPath
    ) {}

    public function handle(): void
    {
        try {
            // Check if photo exists
            if (! Storage::disk('private')->exists($this->photoPath)) {
                Log::error("Photo not found: {$this->photoPath}", [
                    'session_id' => $this->session->id,
                    'turn_index' => $this->turnIndex,
                ]);

                return;
            }

            // Get image content
            $imageContent = Storage::disk('private')->get($this->photoPath);

            // Send to Python service using config
            $imageProcessorUrl = config('services.image_processor.url');

            Log::info('[AnalyzeTurnPhotoJob] Sending to image processor', [
                'url' => $imageProcessorUrl,
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
            ]);

            $response = Http::timeout(60)
                ->attach('file', $imageContent, basename($this->photoPath))
                ->post($imageProcessorUrl.'/api/v1/analyze-target-v2');

            if (! $response->successful()) {
                throw new Exception("Image processing failed: {$response->status()}");
            }

            $data = $response->json();

            if (! $data['success'] || ! isset($data['shots'])) {
                throw new Exception('Invalid response from image processor');
            }

            // Log the detected shots for debugging
            Log::info('[AnalyzeTurnPhotoJob] Python service response', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'total_detected' => $data['total_detected'] ?? count($data['shots']),
                'first_5_shots' => array_slice($data['shots'], 0, 5),
                'photo_path' => $this->photoPath,
            ]);

            // Remove existing shots for this turn (to avoid duplicates)
            SessionShot::where('session_id', $this->session->id)
                ->where('turn_index', $this->turnIndex)
                ->where('source', 'photo_detected')
                ->delete();

            // Create SessionShot records for detected shots
            foreach ($data['shots'] as $index => $shot) {
                try {
                    // Python gives coordinates in [-1, 1] range where:
                    // - (0, 0) = center of detected target
                    // - ±1 = edge of target (ring 1)
                    //
                    // We need to convert to canvas space [0, 1] where:
                    // - (0.5, 0.5) = center of canvas
                    // - Target has radius of 0.46 (TARGET_RADIUS_RATIO)
                    //
                    // Formula: canvas_coord = 0.5 + (target_coord * 0.46)
                    $targetRadiusRatio = 0.46;

                    $xCanvas = 0.5 + ($shot['x'] * $targetRadiusRatio);
                    $yCanvas = 0.5 + ($shot['y'] * $targetRadiusRatio);

                    // Clamp to [0, 1] range (shots outside target may be > 1 or < 0)
                    $xNormalized = max(0, min(1, $xCanvas));
                    $yNormalized = max(0, min(1, $yCanvas));

                    // Calculate distance from center in target space (not canvas space)
                    // This gives us the actual distance for ring calculation
                    $distanceFromCenter = sqrt($shot['x'] ** 2 + $shot['y'] ** 2);

                    // For ring calculation, use the original target space coordinates
                    $xFromCenter = $shot['x'];
                    $yFromCenter = $shot['y'];

                    SessionShot::create([
                        'session_id' => $this->session->id,
                        'turn_index' => $this->turnIndex,
                        'shot_index' => $index + 1, // Start from 1 for each turn
                        'x_normalized' => $xNormalized,
                        'y_normalized' => $yNormalized,
                        'distance_from_center' => $distanceFromCenter,
                        'ring' => $this->calculateRing($xFromCenter, $yFromCenter),
                        'score' => $this->calculateScore($xFromCenter, $yFromCenter),
                        'source' => 'photo_detected',
                        'metadata' => [
                            'confidence' => $shot['confidence'],
                            'photo_path' => $this->photoPath,
                            'processed_at' => now()->toISOString(),
                            'original_x' => $shot['x'], // Store original Python coordinates
                            'original_y' => $shot['y'],
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('[AnalyzeTurnPhotoJob] Failed to create shot', [
                        'session_id' => $this->session->id,
                        'turn_index' => $this->turnIndex,
                        'shot_index' => $index + 1,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Successfully processed turn photo', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'shots_detected' => count($data['shots']),
            ]);

            // Note: Browser refresh must be triggered client-side via polling or websockets
            // The Livewire component will need to poll or listen for updates

        } catch (HttpClientException $e) {
            Log::error('HTTP error while processing photo', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        } catch (Exception $e) {
            Log::error('Error while processing turn photo', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }

    private function calculateRing(float $x, float $y): ?int
    {
        $distance = sqrt($x ** 2 + $y ** 2);

        // Standard ISSF target ring calculations (simplified)
        $rings = [
            10 => 0.0,   // Bullseye
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

        return 0; // Miss
    }

    private function calculateScore(float $x, float $y): int
    {
        return $this->calculateRing($x, $y) ?? 0;
    }
}
