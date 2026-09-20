<?php

namespace App\Support\Ai;

/**
 * Eén bron voor wat AimTrack over de AI-datastroom beweert.
 *
 * De coachpagina, de landingspagina en de gebruikersdocumentatie beschreven dit
 * ieder in eigen woorden en liepen daardoor uit de pas (#132). Alles wat naar
 * buiten gaat komt nu hiervandaan, zodat een correctie op één plek volstaat en
 * een test de docs ertegenaan kan leggen.
 *
 * De opsomming van velden volgt de prompts in {@see \App\Services\Ai\ShooterCoach}.
 */
class AiPrivacyNotice
{
    /**
     * De partij die de AI-antwoorden genereert, inclusief endpoint.
     */
    public const PROVIDER = 'Anthropic (api.anthropic.com)';

    /**
     * De omgevingsvlag waarmee een beheerder de hele AI-functie uitzet.
     */
    public const FEATURE_FLAG = 'FEATURE_AIMTRACK_AI';

    /**
     * Korte intro boven het coachgesprek: waar draait wat, en waar gaat het heen.
     */
    public static function intro(): string
    {
        return 'De app en je database draaien op je eigen server. Voor een antwoord gaat je vraag samen met de betrokken sessie- en wapengegevens naar '.self::PROVIDER.'. Zonder API-key doet AimTrack geen enkele call.';
    }

    /**
     * De volledige uitleg voor het privacy-blok op de coachpagina.
     */
    public static function panel(): string
    {
        return 'De app en de database draaien op je eigen server. Voor elk AI-antwoord gaat je vraag naar '.self::PROVIDER.', samen met '.self::sharedDataSentence().' Dat loopt op jouw eigen Claude-key of op de gedeelde key van je vereniging, dus onder dat account wordt het verwerkt. Zonder key doet AimTrack geen enkele call, en een beheerder kan de AI-functie helemaal uitzetten met '.self::FEATURE_FLAG.'.';
    }

    /**
     * Checklist-regel in het AI-blok op de landingspagina.
     */
    public static function landingCheckItem(): string
    {
        return 'AI is optioneel: zonder eigen Claude-key geen enkele call, met key gaan je sessie- en wapengegevens naar '.self::PROVIDER;
    }

    /**
     * Wat er feitelijk meegaat, als lopende zin.
     */
    public static function sharedDataSentence(): string
    {
        return 'de sessiecontext (datum, baan, locatie, ruwe notities, schotstatistiek en je handmatige reflectie) en de wapengegevens (naam, type, kaliber, serienummer, opslaglocatie, afstand, aantal patronen, munitiesoort en groepering).';
    }

    /**
     * De losse velden die de prompts meesturen, voor een opsomming.
     *
     * @return array{sessie: list<string>, wapen: list<string>}
     */
    public static function sharedFields(): array
    {
        return [
            'sessie' => [
                'datum',
                'baan en locatie',
                'ruwe notities',
                'schotstatistiek (series, scores, groepering)',
                'je handmatige reflectie',
            ],
            'wapen' => [
                'naam',
                'type',
                'kaliber',
                'serienummer',
                'opslaglocatie',
                'afstand, aantal patronen, munitiesoort en groepering per sessie',
            ],
        ];
    }
}
