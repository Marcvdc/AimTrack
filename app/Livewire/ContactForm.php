<?php

namespace App\Livewire;

use App\Services\Contact\ContactService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $message = '';

    /**
     * Honeypot — verborgen voor mensen, ingevuld door bots. Gevuld → stil laten vallen.
     */
    public string $website = '';

    public bool $sent = false;

    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 60;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vul je naam in.',
            'email.required' => 'Vul je e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'message.required' => 'Vul een bericht in.',
            'message.min' => 'Je bericht is wat kort — geef wat meer details (minimaal 10 tekens).',
        ];
    }

    public function submit(ContactService $contactService): void
    {
        if (RateLimiter::tooManyAttempts($this->rateLimitKey(), self::MAX_ATTEMPTS)) {
            $this->addError('form', 'Te veel berichten verstuurd. Probeer het over een minuut opnieuw.');

            return;
        }

        if (filled($this->website)) {
            $this->reset(['name', 'email', 'message', 'website']);
            $this->sent = true;

            return;
        }

        $validated = $this->validate();

        RateLimiter::hit($this->rateLimitKey(), self::DECAY_SECONDS);

        $contactService->send($validated['name'], $validated['email'], $validated['message']);

        $this->reset(['name', 'email', 'message', 'website']);
        $this->sent = true;
    }

    private function rateLimitKey(): string
    {
        return 'contact-form:'.request()->ip();
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}
