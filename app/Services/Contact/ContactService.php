<?php

namespace App\Services\Contact;

use App\Mail\ContactMessageMail;
use Illuminate\Support\Facades\Mail;

class ContactService
{
    /**
     * Verstuur een contactbericht naar het geconfigureerde ontvangstadres.
     *
     * Synchroon verstuurd (geen queue) zodat een bericht ook zonder draaiende
     * queue-worker direct aankomt; reply-to wijst naar de afzender.
     */
    public function send(string $senderName, string $senderEmail, string $body): void
    {
        Mail::to(config('landing.contact_to'))->send(
            new ContactMessageMail($senderName, $senderEmail, $body),
        );
    }
}
