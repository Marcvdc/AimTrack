<?php

namespace App\Jobs;

use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Services\Sessions\TurnPhotoAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyseert de foto van één beurt op de achtergrond.
 *
 * Dit moet in de wachtrij omdat een vision-call met denkwerk in de metingen 90
 * seconden duurde. Dat kun je een schutter niet laten afwachten in een webrequest,
 * en het zou de PHP-worker al die tijd bezet houden.
 */
class AnalyzeTurnPhotoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * Ruim boven de 180 seconden die de analyzer zelf als HTTP-timeout hanteert,
     * zodat de job niet eerder opgeeft dan de call zelf.
     */
    public int $timeout = 300;

    public function __construct(
        public Session $session,
        public int $turnIndex,
        public string $photoPath,
        public ?int $expectedShotCount = null,
        public ?string $disk = null,
    ) {}

    public function handle(TurnPhotoAnalysisService $service): void
    {
        $service->analyze(
            session: $this->session,
            turnIndex: $this->turnIndex,
            photoPath: $this->photoPath,
            expectedShotCount: $this->expectedShotCount,
            disk: $this->disk,
        );
    }

    /**
     * Ook als de job helemaal omvalt moet de beurt zichtbaar blijven als iets dat
     * aandacht vraagt. Anders wacht een schutter op markers die nooit komen.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Foto-analyse van een beurt is mislukt', [
            'session_id' => $this->session->id,
            'turn_index' => $this->turnIndex,
            'message' => $exception?->getMessage(),
        ]);

        SessionTurnAnalysis::updateOrCreate(
            ['session_id' => $this->session->id, 'turn_index' => $this->turnIndex],
            [
                'status' => SessionTurnAnalysis::STATUS_FAILED,
                'needs_review' => true,
                'review_reason' => 'De foto-analyse is niet gelukt. Je foto is bewaard; '
                    .'probeer het opnieuw of zet de schoten handmatig op het bord.',
                'photo_path' => $this->photoPath,
                'expected_shot_count' => $this->expectedShotCount,
            ],
        );
    }
}
