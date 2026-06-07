<div class="mkc-form-wrap">
    @if ($sent)
        <div class="mkc-success" role="status" aria-live="polite" tabindex="-1" x-init="$nextTick(() => $el.focus())">
            <h2>Bericht verstuurd ✓</h2>
            <p>Bedankt — we nemen zo snel mogelijk contact met je op.</p>
            <button type="button" class="mkc-btn-ghost" wire:click="$set('sent', false)">
                Nog een bericht sturen
            </button>
        </div>
    @else
        <form wire:submit="submit" class="mkc-form" novalidate>
            @error('form')
                <p class="mkc-alert" role="alert">{{ $message }}</p>
            @enderror

            <label class="mkc-field">
                <span class="mkc-label">Naam</span>
                <input type="text" wire:model="name" class="mkc-input" autocomplete="name"
                    @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror required>
                @error('name') <span id="contact-name-error" class="mkc-error">{{ $message }}</span> @enderror
            </label>

            <label class="mkc-field">
                <span class="mkc-label">E-mail</span>
                <input type="email" wire:model="email" class="mkc-input" autocomplete="email"
                    @error('email') aria-invalid="true" aria-describedby="contact-email-error" @enderror required>
                @error('email') <span id="contact-email-error" class="mkc-error">{{ $message }}</span> @enderror
            </label>

            <label class="mkc-field">
                <span class="mkc-label">Bericht</span>
                <textarea wire:model="message" class="mkc-input mkc-textarea" rows="6"
                    @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror required></textarea>
                @error('message') <span id="contact-message-error" class="mkc-error">{{ $message }}</span> @enderror
            </label>

            {{-- Honeypot: verborgen voor mensen, ingevuld door bots → stil laten vallen. --}}
            <div class="mkc-hp" aria-hidden="true">
                <label>
                    Laat dit veld leeg
                    <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <button type="submit" class="mkc-btn" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Verstuur bericht</span>
                <span wire:loading wire:target="submit">Versturen…</span>
            </button>
        </form>
    @endif
</div>
