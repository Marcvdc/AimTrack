<x-filament-widgets::widget>
    @php
        // Debug: Check what we receive
        $hasSession = isset($session);
        $sessionExists = $hasSession && $session?->exists;
        $sessionId = $hasSession ? $session?->id : 'N/A';
    @endphp

    @if($sessionExists)
        @livewire(\App\Livewire\SessionShotBoard::class, [
            'session' => $session,
            'readOnly' => $readOnly ?? false,
        ], key('shot-board-widget-' . $session->id))
    @else
        <div class="p-6 text-center text-gray-500">
            <p>Debug info:</p>
            <ul class="text-left text-xs mt-2">
                <li>Has session var: {{ $hasSession ? 'Yes' : 'No' }}</li>
                <li>Session exists: {{ $sessionExists ? 'Yes' : 'No' }}</li>
                <li>Session ID: {{ $sessionId }}</li>
                <li>Session type: {{ $hasSession ? get_class($session) : 'N/A' }}</li>
            </ul>
        </div>
    @endif
</x-filament-widgets::widget>
