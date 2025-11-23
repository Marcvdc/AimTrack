<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::redirect('/login', '/admin/login')->name('login');

Route::get('/health', HealthController::class)->name('health');
