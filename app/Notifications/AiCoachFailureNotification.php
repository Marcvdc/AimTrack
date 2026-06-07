<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AiCoachFailureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reason,
        public readonly array $context = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('AiCoach melding: fout in AI-provider')
            ->line('De AI-instructeur kon geen antwoord genereren.')
            ->line("Reden: {$this->reason}")
            ->line('Controleer de providerconfiguratie, API-key en netwerkverbinding.');

        if (! empty($this->context)) {
            $mail->line('Context:')
                ->line(json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $mail;
    }
}
