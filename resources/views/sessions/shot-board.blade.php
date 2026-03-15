<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Schoten - {{ $session->date?->format('d-m-Y') ?? 'Sessie #' . $session->id }} - AimTrack</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="min-h-full">
        {{-- Header --}}
        <div class="bg-white dark:bg-gray-800 shadow">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between items-center">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            Schoten registreren
                        </h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('filament.admin.resources.sessions.edit', ['record' => $session->id]) }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-white dark:bg-gray-700 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            </svg>
                            Terug naar sessie
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            @php
                $totalShots = $session->shots()->count();
                $totalPoints = $session->shots()->sum('score');
                $average = $totalShots > 0 ? round($totalPoints / $totalShots, 1) : 0;
                $sessionLabel = $session->date?->format('d-m-Y') ? 'Sessie: ' . $session->date->format('d-m-Y') : 'Sessie #' . $session->id;
            @endphp

            {{-- Stats Badges --}}
            <div class="mb-6">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/50 px-3 py-1.5 text-sm font-medium text-blue-700 dark:text-blue-300 ring-1 ring-inset ring-blue-700/10 dark:ring-blue-300/20">
                        Modus Bewerken
                    </span>
                    <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/50 px-3 py-1.5 text-sm font-medium text-green-700 dark:text-green-300 ring-1 ring-inset ring-green-600/20 dark:ring-green-300/20">
                        Punten {{ number_format($totalPoints) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-yellow-50 dark:bg-yellow-900/50 px-3 py-1.5 text-sm font-medium text-yellow-800 dark:text-yellow-300 ring-1 ring-inset ring-yellow-600/20 dark:ring-yellow-300/20">
                        Schoten {{ number_format($totalShots) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-red-50 dark:bg-red-900/50 px-3 py-1.5 text-sm font-medium text-red-700 dark:text-red-300 ring-1 ring-inset ring-red-600/10 dark:ring-red-300/20">
                        Gemiddelde {{ number_format($average, 1, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- Session Info --}}
            <div class="mb-4 rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $sessionLabel }}</h2>
                </div>
            </div>

            {{-- Shot Board Component --}}
            <div class="rounded-lg bg-white dark:bg-gray-800 p-6 shadow">
                @livewire(\App\Livewire\SessionShotBoard::class, [
                    'session' => $session,
                    'readOnly' => false,
                ])
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
