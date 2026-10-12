<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
 * Deze route gaf voorheen een vaste ['status' => 'ok'] terug, ongeacht de echte
 * staat van de applicatie. Monitoring die op /api/health stond in plaats van op
 * /health kreeg daardoor altijd een 200, ook toen productie op 503 stond met
 * storage_unwritable. Beide paden delen nu dezelfde controller.
 */
Route::get('/health', HealthController::class)->name('api.health');
