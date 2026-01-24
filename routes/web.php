<?php

use App\Http\Controllers\Auth\AdminLogoutController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LandingPageController;
use App\Services\Export\SessionExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('welcome');

Route::redirect('/login', '/admin/login')->name('login');

Route::get('/admin/logout', AdminLogoutController::class)
    ->name('filament.admin.auth.logout.get');

Route::get('/exports/sessions/download', function (Request $request, SessionExportService $exportService) {
    $from = Carbon::parse($request->query('from'));
    $to = Carbon::parse($request->query('to'));

    $weaponIdsParam = $request->query('weapon_ids');
    $weaponIds = filled($weaponIdsParam)
        ? explode(',', $weaponIdsParam)
        : null;

    $format = $request->query('format', 'csv');

    return $exportService->exportSessions($request->user(), $from, $to, $weaponIds, $format);
})->middleware(['auth'])->name('exports.sessions.download');

Route::get('/health', HealthController::class)->name('health');
