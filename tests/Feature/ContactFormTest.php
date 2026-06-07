<?php

use App\Livewire\ContactForm;
use App\Mail\ContactMessageMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// Fase 3 — Livewire-contactformulier: validatie, succes-mail, spambescherming.

test('contact form requires name, email and message', function (): void {
    Mail::fake();

    Livewire::test(ContactForm::class)
        ->call('submit')
        ->assertHasErrors([
            'name' => 'required',
            'email' => 'required',
            'message' => 'required',
        ]);

    Mail::assertNothingSent();
});

test('contact form rejects an invalid email address', function (): void {
    Mail::fake();

    Livewire::test(ContactForm::class)
        ->set('name', 'Jan Schutter')
        ->set('email', 'geen-geldig-adres')
        ->set('message', 'Een geldig en voldoende lang bericht.')
        ->call('submit')
        ->assertHasErrors(['email' => 'email']);

    Mail::assertNothingSent();
});

test('contact form sends a mail to the configured address on success', function (): void {
    Mail::fake();
    config()->set('landing.contact_to', 'support@aimtrack.nl');

    Livewire::test(ContactForm::class)
        ->set('name', 'Jan Schutter')
        ->set('email', 'jan@example.com')
        ->set('message', 'Ik heb een vraag over de WM-4 export.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
        return $mail->hasTo('support@aimtrack.nl')
            && $mail->hasReplyTo('jan@example.com')
            && $mail->senderName === 'Jan Schutter'
            && $mail->senderEmail === 'jan@example.com';
    });
});

test('contact form respects a configured non-default recipient', function (): void {
    Mail::fake();
    config()->set('landing.contact_to', 'info@andere-club.nl');

    Livewire::test(ContactForm::class)
        ->set('name', 'Piet')
        ->set('email', 'piet@example.com')
        ->set('message', 'Een geldig en voldoende lang testbericht.')
        ->call('submit')
        ->assertHasNoErrors();

    Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail): bool => $mail->hasTo('info@andere-club.nl'));
});

test('contact form silently drops submissions when the honeypot is filled', function (): void {
    Mail::fake();

    Livewire::test(ContactForm::class)
        ->set('name', 'Bot')
        ->set('email', 'bot@spam.test')
        ->set('message', 'Koop nu goedkope horloges, klik hier!')
        ->set('website', 'http://spam.example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Mail::assertNothingSent();
});

test('contact form associates validation errors with their inputs for screen readers', function (): void {
    Livewire::test(ContactForm::class)
        ->call('submit')
        ->assertSee('id="contact-name-error"', escape: false)
        ->assertSee('aria-describedby="contact-name-error"', escape: false)
        ->assertSee('aria-invalid="true"', escape: false);
});

test('contact form success state is focusable so assistive tech lands on it', function (): void {
    Mail::fake();

    Livewire::test(ContactForm::class)
        ->set('name', 'Jan Schutter')
        ->set('email', 'jan@example.com')
        ->set('message', 'Een geldig en voldoende lang bericht.')
        ->call('submit')
        ->assertSet('sent', true)
        ->assertSee('tabindex="-1"', escape: false);
});

test('contact form blocks after too many submissions from the same client', function (): void {
    Mail::fake();

    $component = Livewire::test(ContactForm::class);

    foreach (range(1, 3) as $i) {
        $component
            ->set('name', 'Jan')
            ->set('email', "jan{$i}@example.com")
            ->set('message', "Een geldig en voldoende lang bericht nummer {$i}.")
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->set('sent', false);
    }

    $component
        ->set('name', 'Jan')
        ->set('email', 'jan4@example.com')
        ->set('message', 'Nog een geldig en voldoende lang bericht.')
        ->call('submit')
        ->assertHasErrors('form');

    Mail::assertSent(ContactMessageMail::class, 3);
});
