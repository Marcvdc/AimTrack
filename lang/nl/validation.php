<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Validatieregels in het Nederlands
|--------------------------------------------------------------------------
|
| Laravel levert zelf alleen een Engelse set mee. AimTrack is Nederlandstalig
| (config/app.php: 'locale' => 'nl'), dus zonder dit bestand krijgt de
| gebruiker zinnen als "The bestand field is required." te zien: een Engelse
| zin met een Nederlands veldlabel erin. De sleutels volgen exact de Engelse
| set, zodat een nieuwe Laravel-regel hier zichtbaar ontbreekt in plaats van
| stilletjes verkeerd te gaan.
|
*/

return [
    'accepted' => ':attribute moet geaccepteerd worden.',
    'accepted_if' => ':attribute moet geaccepteerd worden als :other gelijk is aan :value.',
    'active_url' => ':attribute is geen geldige URL.',
    'after' => ':attribute moet een datum na :date zijn.',
    'after_or_equal' => ':attribute moet een datum na of gelijk aan :date zijn.',
    'alpha' => ':attribute mag alleen letters bevatten.',
    'alpha_dash' => ':attribute mag alleen letters, cijfers, liggende streepjes en koppeltekens bevatten.',
    'alpha_num' => ':attribute mag alleen letters en cijfers bevatten.',
    'any_of' => ':attribute is ongeldig.',
    'array' => ':attribute moet een reeks zijn.',
    'ascii' => ':attribute mag alleen tekens en symbolen uit de standaardtekenset bevatten.',
    'before' => ':attribute moet een datum voor :date zijn.',
    'before_or_equal' => ':attribute moet een datum voor of gelijk aan :date zijn.',
    'between' => [
        'array' => ':attribute moet tussen :min en :max items bevatten.',
        'file' => ':attribute moet tussen :min en :max kilobytes groot zijn.',
        'numeric' => ':attribute moet tussen :min en :max liggen.',
        'string' => ':attribute moet tussen :min en :max tekens bevatten.',
    ],
    'boolean' => ':attribute moet ja of nee zijn.',
    'can' => ':attribute bevat een waarde waarvoor je geen rechten hebt.',
    'confirmed' => ':attribute komt niet overeen met de bevestiging.',
    'contains' => 'Er ontbreekt een verplichte waarde in :attribute.',
    'current_password' => 'Het wachtwoord klopt niet.',
    'date' => ':attribute is geen geldige datum.',
    'date_equals' => ':attribute moet gelijk zijn aan :date.',
    'date_format' => ':attribute komt niet overeen met de notatie :format.',
    'decimal' => ':attribute moet :decimal decimalen hebben.',
    'declined' => ':attribute moet afgewezen worden.',
    'declined_if' => ':attribute moet afgewezen worden als :other gelijk is aan :value.',
    'different' => ':attribute en :other mogen niet gelijk zijn.',
    'digits' => ':attribute moet :digits cijfers bevatten.',
    'digits_between' => ':attribute moet tussen :min en :max cijfers bevatten.',
    'dimensions' => ':attribute heeft ongeldige afmetingen.',
    'distinct' => ':attribute bevat een dubbele waarde.',
    'doesnt_contain' => ':attribute mag geen van de volgende waarden bevatten: :values.',
    'doesnt_end_with' => ':attribute mag niet eindigen op een van de volgende waarden: :values.',
    'doesnt_start_with' => ':attribute mag niet beginnen met een van de volgende waarden: :values.',
    'email' => ':attribute is geen geldig e-mailadres.',
    'encoding' => ':attribute moet in :encoding gecodeerd zijn.',
    'ends_with' => ':attribute moet eindigen op een van de volgende waarden: :values.',
    'enum' => 'De gekozen waarde voor :attribute is ongeldig.',
    'exists' => 'De gekozen waarde voor :attribute bestaat niet.',
    'extensions' => ':attribute moet een van de volgende bestandsextensies hebben: :values.',
    'file' => ':attribute moet een bestand zijn.',
    'filled' => ':attribute mag niet leeg zijn.',
    'gt' => [
        'array' => ':attribute moet meer dan :value items bevatten.',
        'file' => ':attribute moet groter zijn dan :value kilobytes.',
        'numeric' => ':attribute moet groter zijn dan :value.',
        'string' => ':attribute moet langer zijn dan :value tekens.',
    ],
    'gte' => [
        'array' => ':attribute moet :value items of meer bevatten.',
        'file' => ':attribute moet groter of gelijk zijn aan :value kilobytes.',
        'numeric' => ':attribute moet groter of gelijk zijn aan :value.',
        'string' => ':attribute moet :value tekens of langer zijn.',
    ],
    'hex_color' => ':attribute moet een geldige hexadecimale kleurcode zijn.',
    'image' => ':attribute moet een afbeelding zijn.',
    'in' => 'De gekozen waarde voor :attribute is ongeldig.',
    'in_array' => ':attribute komt niet voor in :other.',
    'in_array_keys' => ':attribute moet minstens een van de volgende sleutels bevatten: :values.',
    'integer' => ':attribute moet een geheel getal zijn.',
    'ip' => ':attribute moet een geldig IP-adres zijn.',
    'ipv4' => ':attribute moet een geldig IPv4-adres zijn.',
    'ipv6' => ':attribute moet een geldig IPv6-adres zijn.',
    'json' => ':attribute moet geldige JSON zijn.',
    'list' => ':attribute moet een lijst zijn.',
    'lowercase' => ':attribute mag alleen kleine letters bevatten.',
    'lt' => [
        'array' => ':attribute moet minder dan :value items bevatten.',
        'file' => ':attribute moet kleiner zijn dan :value kilobytes.',
        'numeric' => ':attribute moet kleiner zijn dan :value.',
        'string' => ':attribute moet korter zijn dan :value tekens.',
    ],
    'lte' => [
        'array' => ':attribute mag niet meer dan :value items bevatten.',
        'file' => ':attribute moet kleiner of gelijk zijn aan :value kilobytes.',
        'numeric' => ':attribute moet kleiner of gelijk zijn aan :value.',
        'string' => ':attribute moet :value tekens of korter zijn.',
    ],
    'mac_address' => ':attribute moet een geldig MAC-adres zijn.',
    'max' => [
        'array' => ':attribute mag niet meer dan :max items bevatten.',
        'file' => ':attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => ':attribute mag niet groter zijn dan :max.',
        'string' => ':attribute mag niet langer zijn dan :max tekens.',
    ],
    'max_digits' => ':attribute mag niet meer dan :max cijfers bevatten.',
    'mimes' => ':attribute moet een bestand van het type :values zijn.',
    'mimetypes' => ':attribute moet een bestand van het type :values zijn.',
    'min' => [
        'array' => ':attribute moet minstens :min items bevatten.',
        'file' => ':attribute moet minstens :min kilobytes groot zijn.',
        'numeric' => ':attribute moet minstens :min zijn.',
        'string' => ':attribute moet minstens :min tekens bevatten.',
    ],
    'min_digits' => ':attribute moet minstens :min cijfers bevatten.',
    'missing' => ':attribute mag niet meegestuurd worden.',
    'missing_if' => ':attribute mag niet meegestuurd worden als :other gelijk is aan :value.',
    'missing_unless' => ':attribute mag niet meegestuurd worden tenzij :other gelijk is aan :value.',
    'missing_with' => ':attribute mag niet meegestuurd worden als :values aanwezig is.',
    'missing_with_all' => ':attribute mag niet meegestuurd worden als :values aanwezig zijn.',
    'multiple_of' => ':attribute moet een veelvoud van :value zijn.',
    'not_in' => 'De gekozen waarde voor :attribute is ongeldig.',
    'not_regex' => ':attribute heeft een ongeldige notatie.',
    'numeric' => ':attribute moet een getal zijn.',
    'password' => [
        'letters' => ':attribute moet minstens een letter bevatten.',
        'mixed' => ':attribute moet minstens een hoofdletter en een kleine letter bevatten.',
        'numbers' => ':attribute moet minstens een cijfer bevatten.',
        'symbols' => ':attribute moet minstens een leesteken bevatten.',
        'uncompromised' => ':attribute komt voor in een datalek. Kies een ander wachtwoord.',
    ],
    'present' => ':attribute moet meegestuurd worden.',
    'present_if' => ':attribute moet meegestuurd worden als :other gelijk is aan :value.',
    'present_unless' => ':attribute moet meegestuurd worden tenzij :other gelijk is aan :value.',
    'present_with' => ':attribute moet meegestuurd worden als :values aanwezig is.',
    'present_with_all' => ':attribute moet meegestuurd worden als :values aanwezig zijn.',
    'prohibited' => ':attribute mag niet ingevuld worden.',
    'prohibited_if' => ':attribute mag niet ingevuld worden als :other gelijk is aan :value.',
    'prohibited_if_accepted' => ':attribute mag niet ingevuld worden als :other geaccepteerd is.',
    'prohibited_if_declined' => ':attribute mag niet ingevuld worden als :other afgewezen is.',
    'prohibited_unless' => ':attribute mag niet ingevuld worden tenzij :other een van :values is.',
    'prohibits' => ':attribute zorgt ervoor dat :other niet ingevuld mag worden.',
    'regex' => ':attribute heeft een ongeldige notatie.',
    'required' => ':attribute is verplicht.',
    'required_array_keys' => ':attribute moet waarden bevatten voor :values.',
    'required_if' => ':attribute is verplicht als :other gelijk is aan :value.',
    'required_if_accepted' => ':attribute is verplicht als :other geaccepteerd is.',
    'required_if_declined' => ':attribute is verplicht als :other afgewezen is.',
    'required_unless' => ':attribute is verplicht tenzij :other een van :values is.',
    'required_with' => ':attribute is verplicht als :values aanwezig is.',
    'required_with_all' => ':attribute is verplicht als :values aanwezig zijn.',
    'required_without' => ':attribute is verplicht als :values niet aanwezig is.',
    'required_without_all' => ':attribute is verplicht als geen van :values aanwezig is.',
    'same' => ':attribute moet gelijk zijn aan :other.',
    'size' => [
        'array' => ':attribute moet :size items bevatten.',
        'file' => ':attribute moet :size kilobytes groot zijn.',
        'numeric' => ':attribute moet gelijk zijn aan :size.',
        'string' => ':attribute moet :size tekens bevatten.',
    ],
    'starts_with' => ':attribute moet beginnen met een van de volgende waarden: :values.',
    'string' => ':attribute moet tekst zijn.',
    'timezone' => ':attribute moet een geldige tijdzone zijn.',
    'unique' => ':attribute is al in gebruik.',
    'uploaded' => 'Het uploaden van :attribute is mislukt.',
    'uppercase' => ':attribute mag alleen hoofdletters bevatten.',
    'url' => ':attribute is geen geldige URL.',
    'ulid' => ':attribute moet een geldige ULID zijn.',
    'uuid' => ':attribute moet een geldige UUID zijn.',

    /*
    |--------------------------------------------------------------------------
    | Eigen validatieberichten
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Veldnamen
    |--------------------------------------------------------------------------
    |
    | Filament vult :attribute met het ->label() van het veld, dus de meeste
    | schermen hebben hier niets nodig. Dit blok dekt de velden die buiten
    | Filament om gevalideerd worden (auth-formulieren, contactformulier).
    |
    */

    'attributes' => [
        'current_password' => 'huidig wachtwoord',
        'date' => 'datum',
        'email' => 'e-mailadres',
        'message' => 'bericht',
        'name' => 'naam',
        'password' => 'wachtwoord',
        'password_confirmation' => 'wachtwoordbevestiging',
        'subject' => 'onderwerp',
    ],
];
