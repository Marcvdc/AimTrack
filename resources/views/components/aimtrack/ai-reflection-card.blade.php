@props([
    'reflection' => null,
    'sessionId' => null,
    'generatedAt' => null,
    'compact' => false,
])

@php
    $summary = $reflection?->summary ?? null;
    $positives = $reflection?->positives ?? null;
    $improvements = $reflection?->improvements ?? null;
    $nextFocus = $reflection?->next_focus ?? null;

    $headerSub = $sessionId !== null ? '· '.$sessionId : '';
    $tsLabel = $generatedAt
        ? (is_string($generatedAt) ? $generatedAt : $generatedAt->diffForHumans())
        : null;

    $listOrDash = static function ($value): string {
        if (is_array($value)) {
            return collect($value)->filter()->implode(' · ');
        }
        return filled($value) ? (string) $value : '—';
    };
@endphp

<x-aimtrack.bracket-frame
    :rounded="8"
    :padding="16"
    {{ $attributes->merge(['class' => 'aimtrack-ai-reflection-card', 'style' => 'display: flex; flex-direction: column; gap: 12px;']) }}
>
    <div style="display: flex; align-items: center; gap: 8px; font-size: 11px; font-family: var(--at-font-mono); letter-spacing: 0.12em; text-transform: uppercase; color: var(--at-accent);">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" role="presentation">
            <path d="M12 3l2 4 4 1-3 3 1 4-4-2-4 2 1-4-3-3 4-1z" />
        </svg>
        <span>AI-reflectie {{ $headerSub }}</span>
        @if ($tsLabel)
            <span style="margin-left: auto; color: var(--at-muted);">{{ $tsLabel }}</span>
        @endif
    </div>

    <div style="font-size: 13px; line-height: 1.55; color: var(--at-text);">
        {{ filled($summary) ? $summary : '—' }}
    </div>

    @unless ($compact)
        <div style="height: 1px; background: var(--at-line);"></div>

        <div class="aimtrack-ai-reflect-grid" style="display: grid; grid-template-columns: 90px 1fr; gap: 6px; font-size: 12px;">
            <div class="at-label" style="color: var(--at-accent);">STERK</div>
            <div style="color: var(--at-text);">{{ $listOrDash($positives) }}</div>
            <div class="at-label">VERBETER</div>
            <div style="color: var(--at-text);">{{ $listOrDash($improvements) }}</div>
            <div class="at-label">VOLGENDE</div>
            <div style="color: var(--at-text);">{{ $listOrDash($nextFocus) }}</div>
        </div>
    @endunless

    @isset($actions)
        <div style="display: flex; gap: 6px; margin-top: 4px;">
            {{ $actions }}
        </div>
    @endisset
</x-aimtrack.bracket-frame>
