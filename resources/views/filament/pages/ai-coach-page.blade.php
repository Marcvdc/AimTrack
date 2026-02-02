<x-filament::page>
    {{ $this->form }}
    
    <!-- Show the Filament table for old sessions -->
    @if (!$this->oldSessions->isEmpty())
        <div class="mt-6">
            {{ $this->table }}
        </div>
    @endif
</x-filament::page>
