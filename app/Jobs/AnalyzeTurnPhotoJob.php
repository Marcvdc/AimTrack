<?php

namespace App\Jobs;

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionTurnAnalysis;
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

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private Session $session,
        private int $turnIndex,
        private string $photoPath,
        private ?int $expectedShotCount = null,
    ) {}

    public function expectedShotCountForTest(): ?int
    {
        return $this->expectedShotCount;
    }

    public function handle(): void
    {
        try {
            if (! Storage::disk('private')->exists($this->photoPath)) {
                Log::error("Photo not found: {$this->photoPath}", [
                    'session_id' => $this->session->id,
                    'turn_index' => $this->turnIndex,
                ]);

                return;
            }

            $targetType = $this->session->target_type?->value;

            if (blank($targetType)) {
                Log::warning('[AnalyzeTurnPhotoJob] No target_type on session; cannot analyze photo', [
                    'session_id' => $this->session->id,
                    'turn_index' => $this->turnIndex,
                ]);

                $this->recordTurnAnalysis(
                    needsReview: true,
                    overallConfidence: 0.0,
                    detectedCount: 0,
                    countMatchesExpected: false,
                    calibrationRmsMm: null,
                    visionModel: null,
                );

                return;
            }

            $imageContent = Storage::disk('private')->get($this->photoPath);
            $baseUrl = config('services.image_processor.url');

            $response = Http::timeout(90)
                ->attach('file', $imageContent, basename($this->photoPath))
                ->post($baseUrl.'/api/v2/analyze-target', [
                    'target_type' => $targetType,
                    'expected_shot_count' => $this->expectedShotCount,
                ]);

            if (! $response->successful()) {
                throw new Exception("Image processing failed: {$response->status()}");
            }

            $data = $response->json();

            if (! ($data['success'] ?? false) || ! isset($data['shots'])) {
                throw new Exception('Invalid response from image processor');
            }

            SessionShot::where('session_id', $this->session->id)
                ->where('turn_index', $this->turnIndex)
                ->where('source', 'photo_detected')
                ->delete();

            $targetRadiusRatio = 0.46;

            foreach ($data['shots'] as $index => $shot) {
                $x = (float) $shot['x'];
                $y = (float) $shot['y'];

                SessionShot::create([
                    'session_id' => $this->session->id,
                    'turn_index' => $this->turnIndex,
                    'shot_index' => $index + 1,
                    'x_normalized' => max(0, min(1, 0.5 + $x * $targetRadiusRatio)),
                    'y_normalized' => max(0, min(1, 0.5 + $y * $targetRadiusRatio)),
                    'distance_from_center' => sqrt($x ** 2 + $y ** 2),
                    'ring' => (int) ($shot['ring'] ?? 0),
                    'score' => (int) ($shot['score'] ?? 0),
                    'source' => 'photo_detected',
                    'metadata' => [
                        'confidence' => $shot['confidence'] ?? null,
                        'kind' => $shot['kind'] ?? null,
                        'photo_path' => $this->photoPath,
                        'processed_at' => now()->toISOString(),
                        'original_x' => $x,
                        'original_y' => $y,
                        'vision_model' => $data['vision_model'] ?? null,
                    ],
                ]);
            }

            $this->recordTurnAnalysis(
                needsReview: (bool) ($data['needs_review'] ?? true),
                overallConfidence: (float) ($data['overall_confidence'] ?? 0.0),
                detectedCount: (int) ($data['detected_count'] ?? count($data['shots'])),
                countMatchesExpected: (bool) ($data['count_matches_expected'] ?? false),
                calibrationRmsMm: $data['calibration']['rms_error_mm'] ?? null,
                visionModel: $data['vision_model'] ?? null,
            );

            Log::info('Successfully processed turn photo (v2)', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'shots_detected' => count($data['shots']),
                'needs_review' => $data['needs_review'] ?? null,
            ]);
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

    private function recordTurnAnalysis(
        bool $needsReview,
        float $overallConfidence,
        int $detectedCount,
        bool $countMatchesExpected,
        ?float $calibrationRmsMm,
        ?string $visionModel,
    ): void {
        SessionTurnAnalysis::updateOrCreate(
            [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
            ],
            [
                'needs_review' => $needsReview,
                'overall_confidence' => $overallConfidence,
                'expected_shot_count' => $this->expectedShotCount,
                'detected_count' => $detectedCount,
                'count_matches_expected' => $countMatchesExpected,
                'calibration_rms_mm' => $calibrationRmsMm,
                'vision_model' => $visionModel,
                'analyzed_at' => now(),
            ]
        );
    }
}
