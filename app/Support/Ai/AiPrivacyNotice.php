<?php

namespace App\Support\Ai;

use App\Filament\Copilot\Tools\AddTrainingGoalTool;
use App\Filament\Copilot\Tools\ScoreDriftTool;
use App\Filament\Copilot\Tools\SessionLookupTool;
use App\Filament\Copilot\Tools\ShooterContextTool;
use App\Filament\Copilot\Tools\WeaponLookupTool;

/**
 * Eén bron voor wat AimTrack over de AI-datastroom beweert.
 *
 * De coachpagina, de landingspagina en de gebruikersdocumentatie beschreven dit
 * ieder in eigen woorden en liepen daardoor uit de pas (#132). Alles wat naar
 * buiten gaat komt nu hiervandaan, zodat een correctie op één plek volstaat.
 *
 * Er zijn twee routes naar Anthropic: de knoppen voor een reflectie en een
 * wapeninzicht ({@see \App\Services\Ai\ShooterCoach}) en de chat op de
 * coachpagina (Filament Copilot, met de tools in {@see self::CHAT_TOOLS}).
 * {@see self::PATHS} legt per route vast welke velden meegaan; een test wijzigt
 * elk veld afzonderlijk en faalt zodra de werkelijkheid daarvan afwijkt, in
 * beide richtingen.
 */
class AiPrivacyNotice
{
    /**
     * De omgevingsvlag waarmee een beheerder de AI-functie uitzet.
     */
    public const FEATURE_FLAG = 'FEATURE_AIMTRACK_AI';

    /**
     * Het commando dat de per gebruiker opgeslagen vlag wist, zodat uitzetten
     * ook geldt voor wie de AI al eerder gebruikte.
     */
    public const PURGE_COMMAND = 'php artisan pennant:purge aimtrack-ai';

    /**
     * Per AI-pad de modelvelden die de uitvoer naar Anthropic beïnvloeden.
     *
     * @var array<string, list<string>>
     */
    public const PATHS = [
        'reflectie' => [
            'sessions.date',
            'sessions.range_name',
            'sessions.location',
            'sessions.notes_raw',
            'sessions.manual_reflection',
            'session_shots.score',
            'session_shots.ring',
            'session_shots.x_normalized',
            'session_shots.y_normalized',
            'session_weapons.distance_m',
            'session_weapons.rounds_fired',
            'session_weapons.ammo_type',
            'session_weapons.deviation',
            'session_weapons.group_quality_text',
            'weapons.name',
        ],
        'wapeninzicht' => [
            'sessions.date',
            'session_weapons.distance_m',
            'session_weapons.rounds_fired',
            'session_weapons.deviation',
            'session_weapons.group_quality_text',
            'weapons.name',
            'weapons.weapon_type',
            'weapons.caliber',
            'weapons.serial_number',
            'weapons.storage_location',
        ],
        'chat_schietcontext' => [
            'sessions.date',
            'sessions.range_name',
            'session_weapons.distance_m',
            'session_weapons.rounds_fired',
            'session_weapons.deviation',
            'session_weapons.group_quality_text',
            'weapons.name',
            'weapons.caliber',
            'ai_reflections.summary',
            'ai_reflections.positives',
            'ai_reflections.improvements',
            'ai_reflections.next_focus',
        ],
        'chat_sessie' => [
            'sessions.date',
            'sessions.range_name',
            'sessions.location',
            'sessions.notes_raw',
            'sessions.manual_reflection',
            'session_shots.score',
            'session_weapons.distance_m',
            'session_weapons.rounds_fired',
            'session_weapons.deviation',
            'session_weapons.group_quality_text',
            'ammo_types.name',
            'weapons.name',
            'ai_reflections.summary',
            'ai_reflections.next_focus',
        ],
        'chat_wapen' => [
            'sessions.date',
            'session_weapons.distance_m',
            'session_weapons.rounds_fired',
            'session_weapons.deviation',
            'session_weapons.group_quality_text',
            'weapons.name',
            'weapons.weapon_type',
            'weapons.caliber',
            'weapons.serial_number',
            'weapons.storage_location',
            'weapons.is_active',
            'weapons.notes',
            'ai_weapon_insights.summary',
            'ai_weapon_insights.patterns',
            'ai_weapon_insights.suggestions',
        ],
        'chat_scoredrift' => [
            'session_shots.score',
            'session_shots.turn_index',
            'session_shots.shot_index',
        ],
    ];

    /**
     * Welke chat-tool bij welk pad hoort. Een tool zonder pad stuurt geen
     * opgeslagen gegevens mee: het trainingsdoel komt uit het gesprek zelf.
     *
     * @var array<class-string, string|null>
     */
    public const CHAT_TOOLS = [
        ShooterContextTool::class => 'chat_schietcontext',
        SessionLookupTool::class => 'chat_sessie',
        WeaponLookupTool::class => 'chat_wapen',
        ScoreDriftTool::class => 'chat_scoredrift',
        AddTrainingGoalTool::class => null,
    ];

    /**
     * De groepen waarin de velden aan de gebruiker worden getoond.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'sessie' => 'Sessies',
        'wapenregel' => 'Per wapen in een sessie',
        'wapen' => 'Wapens',
        'ai' => 'Eerdere AI-uitkomsten',
        'gesprek' => 'Het chatgesprek',
    ];

    /**
     * Per meegestuurd modelveld de groep en de omschrijving voor de gebruiker.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const LABELS = [
        'sessions.date' => ['sessie', 'datum'],
        'sessions.range_name' => ['sessie', 'baan en locatie'],
        'sessions.location' => ['sessie', 'baan en locatie'],
        'sessions.notes_raw' => ['sessie', 'je ruwe sessienotities'],
        'sessions.manual_reflection' => ['sessie', 'je handmatige reflectie'],
        'session_shots.score' => ['sessie', 'scores en treffpunten van je schoten, als statistiek'],
        'session_shots.ring' => ['sessie', 'scores en treffpunten van je schoten, als statistiek'],
        'session_shots.x_normalized' => ['sessie', 'scores en treffpunten van je schoten, als statistiek'],
        'session_shots.y_normalized' => ['sessie', 'scores en treffpunten van je schoten, als statistiek'],
        'session_shots.turn_index' => ['sessie', 'scores en treffpunten van je schoten, als statistiek'],
        'session_shots.shot_index' => ['sessie', 'scores en treffpunten van je schoten, als statistiek'],
        'session_weapons.distance_m' => ['wapenregel', 'afstand'],
        'session_weapons.rounds_fired' => ['wapenregel', 'aantal patronen'],
        'session_weapons.ammo_type' => ['wapenregel', 'munitiesoort'],
        'ammo_types.name' => ['wapenregel', 'munitiesoort'],
        'session_weapons.deviation' => ['wapenregel', 'afwijking'],
        'session_weapons.group_quality_text' => ['wapenregel', 'je omschrijving van de groepering'],
        'weapons.name' => ['wapen', 'naam'],
        'weapons.weapon_type' => ['wapen', 'type'],
        'weapons.caliber' => ['wapen', 'kaliber'],
        'weapons.serial_number' => ['wapen', 'serienummer'],
        'weapons.storage_location' => ['wapen', 'opslaglocatie'],
        'weapons.is_active' => ['wapen', 'of het wapen actief of uit gebruik is'],
        'weapons.notes' => ['wapen', 'je vrije wapennotities'],
        'ai_reflections.summary' => ['ai', 'eerdere AI-reflecties op je sessies'],
        'ai_reflections.positives' => ['ai', 'eerdere AI-reflecties op je sessies'],
        'ai_reflections.improvements' => ['ai', 'eerdere AI-reflecties op je sessies'],
        'ai_reflections.next_focus' => ['ai', 'eerdere AI-reflecties op je sessies'],
        'ai_weapon_insights.summary' => ['ai', 'eerdere AI-inzichten per wapen'],
        'ai_weapon_insights.patterns' => ['ai', 'eerdere AI-inzichten per wapen'],
        'ai_weapon_insights.suggestions' => ['ai', 'eerdere AI-inzichten per wapen'],
    ];

    /**
     * Wat de chat meestuurt buiten de modelvelden om.
     *
     * @var list<string>
     */
    public const CONVERSATION_LABELS = [
        'je vragen en de eerdere berichten uit hetzelfde gesprek',
        'wat de coach over je heeft onthouden (herinneringen)',
    ];

    /**
     * De modelvelden die een pad meestuurt.
     *
     * @return list<string>
     */
    public static function sentAttributes(string $path): array
    {
        return self::PATHS[$path] ?? [];
    }

    /**
     * Alle modelvelden die via welk pad dan ook naar Anthropic gaan.
     *
     * @return list<string>
     */
    public static function allSentAttributes(): array
    {
        return collect(self::PATHS)->flatten()->unique()->sort()->values()->all();
    }

    /**
     * De meegestuurde gegevens per groep, in de volgorde van {@see self::GROUPS}.
     *
     * @return array<string, list<string>>
     */
    public static function sharedFields(): array
    {
        $fields = array_fill_keys(array_keys(self::GROUPS), []);

        foreach (self::allSentAttributesInDeclaredOrder() as $attribute) {
            [$group, $label] = self::LABELS[$attribute];

            if (! in_array($label, $fields[$group], true)) {
                $fields[$group][] = $label;
            }
        }

        $fields['gesprek'] = self::CONVERSATION_LABELS;

        return array_filter($fields);
    }

    /**
     * Alle omschrijvingen die de tekst moet noemen.
     *
     * @return list<string>
     */
    public static function labels(): array
    {
        return collect(self::sharedFields())->flatten()->values()->all();
    }

    /**
     * De partij die de AI-antwoorden genereert, met de hosts waar AimTrack
     * volgens de configuratie naartoe verbindt.
     */
    public static function provider(): string
    {
        $hosts = collect([
            config('ai.base_url'),
            config('ai.providers.anthropic.url'),
        ])
            ->filter()
            ->map(fn (string $url): string => parse_url($url, PHP_URL_HOST) ?: $url)
            ->unique()
            ->implode(', ');

        return $hosts === '' ? 'Anthropic' : "Anthropic ({$hosts})";
    }

    /**
     * Korte intro boven het coachgesprek: waar draait wat, en waar gaat het heen.
     */
    public static function intro(): string
    {
        return 'De app en je database draaien op je eigen server. Voor een antwoord gaan je vraag, het gesprek tot nu toe en de sessie- en wapengegevens die de coach erbij haalt naar '.self::provider().'. Zonder API-key doet AimTrack geen enkele AI-call.';
    }

    /**
     * De volledige uitleg voor het privacy-blok op de coachpagina.
     */
    public static function panel(): string
    {
        return 'De app en de database draaien op je eigen server. Voor elk AI-antwoord, in de chat en bij een reflectie of wapeninzicht, gaat je vraag naar '.self::provider().', samen met '.self::sharedDataSentence().' Dat loopt op jouw eigen Claude-key of op de gedeelde key van je vereniging, dus onder dat account wordt het verwerkt. Zonder key doet AimTrack geen enkele AI-call. Een beheerder zet de AI-functie uit met '.self::FEATURE_FLAG.'=false; voor wie de AI al gebruikte geldt dat pas na '.self::PURGE_COMMAND.'.';
    }

    /**
     * Checklist-regel in het AI-blok op de landingspagina.
     */
    public static function landingCheckItem(): string
    {
        return 'AI is optioneel: zonder eigen Claude-key geen enkele AI-call, met key gaan je vraag en je sessie- en wapengegevens naar '.self::provider();
    }

    /**
     * Wat er feitelijk meegaat, als lopende zin per groep.
     */
    public static function sharedDataSentence(): string
    {
        $parts = collect(self::sharedFields())
            ->map(fn (array $labels, string $group): string => mb_strtolower(self::GROUPS[$group]).': '.implode(', ', $labels))
            ->implode('; ');

        return 'wat daarvoor nodig is uit je logboek. Dat kan zijn, per onderdeel: '.$parts.'.';
    }

    /**
     * @return list<string>
     */
    private static function allSentAttributesInDeclaredOrder(): array
    {
        return collect(self::LABELS)
            ->keys()
            ->intersect(self::allSentAttributes())
            ->values()
            ->all();
    }
}
