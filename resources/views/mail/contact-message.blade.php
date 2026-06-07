<x-mail::message>
# Nieuw contactbericht

Er is een bericht verstuurd via het contactformulier op aimtrack.nl.

**Naam:** {{ $senderName }}
**E-mail:** {{ $senderEmail }}

---

{{ $body }}

<x-mail::button :url="'mailto:' . $senderEmail">
Beantwoorden
</x-mail::button>

Met vriendelijke groet,<br>
{{ config('app.name') }}
</x-mail::message>
