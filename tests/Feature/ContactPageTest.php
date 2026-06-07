<?php

use App\Livewire\ContactForm;

// Fase 3 — afronding footer-links. /contact is een eigen pagina met een
// Livewire-contactformulier dat naar het support-adres mailt.

test('contact page renders for guests', function (): void {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Vragen of feedback?', escape: false)
        ->assertSee('Verstuur bericht', escape: false);
});

test('contact page surfaces the support email address', function (): void {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('support@aimtrack.nl', escape: false);
});

test('contact page embeds the ContactForm Livewire component', function (): void {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSeeLivewire(ContactForm::class);
});

test('contact page email reflects the configured recipient', function (): void {
    config()->set('landing.contact_to', 'info@andere-club.nl');

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('mailto:info@andere-club.nl', escape: false)
        ->assertDontSee('support@aimtrack.nl');
});
