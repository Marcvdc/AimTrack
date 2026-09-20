<?php

use App\Http\Controllers\Auth\AdminLogoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\SessionExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('welcome');

Route::get('/contact', ContactController::class)->name('contact');

Route::redirect('/login', '/admin/login')->name('login');

Route::get('/admin/logout', AdminLogoutController::class)
    ->name('filament.admin.auth.logout.get');

Route::get('/exports/sessions/download', SessionExportController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('exports.sessions.download');

Route::get('/health', HealthController::class)->name('health');
