<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */

    'provider' => env('COPILOT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Default AI Model
    |--------------------------------------------------------------------------
    */

    'model' => env('COPILOT_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Agent Behavior
    |--------------------------------------------------------------------------
    */

    'agent' => [
        'timeout' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat History
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'title_auto_generate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'enabled' => false,
        'max_messages_per_hour' => 60,
        'max_messages_per_day' => 500,
        'max_tokens_per_hour' => 100000,
        'max_tokens_per_day' => 1000000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Budget
    |--------------------------------------------------------------------------
    */

    'token_budget' => [
        'enabled' => false,
        'warn_at_percentage' => 80,
        'daily_budget' => null,
        'monthly_budget' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'enabled' => true,
        'log_messages' => true,
        'log_tool_calls' => true,
        'log_record_access' => true,
        'log_navigation' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Memory
    |--------------------------------------------------------------------------
    */

    'memory' => [
        'enabled' => true,
        'max_memories_per_user' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Integration
    |--------------------------------------------------------------------------
    */

    'respect_authorization' => true,

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Management UI
    |--------------------------------------------------------------------------
    */

    'management' => [
        'enabled' => false,
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Actions / Canned Prompts
    |--------------------------------------------------------------------------
    */

    'quick_actions' => [],

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    | Basis-identiteit van de copilot. Vervangt de generieke "Filament admin
    | panel assistant"-prompt van het package, zodat de assistent zich als de
    | AimTrack AI-Coach voorstelt. De operationele richtlijnen (tools,
    | bevestiging, geheugen) zijn behouden zodat de copilot blijft werken.
    */

    'system_prompt' => <<<'PROMPT'
        Je bent de AimTrack AI-Coach: een behulpzame coach-assistent voor schutters en coaches binnen AimTrack. Je helpt gebruikers hun trainingsdata begrijpen, doelen stellen en gerichte feedback krijgen over hun sessies, wapens en techniek.

        Stel jezelf altijd voor als "AimTrack AI-Coach" — nooit als een admin panel-assistent. Antwoord standaard in het Nederlands.

        ## Richtlijnen
        - Respecteer altijd de rechten van de gebruiker. Voer nooit acties uit waarvoor de gebruiker geen autorisatie heeft.
        - Bevestig wijzigingen voordat je opslaat, tenzij de gebruiker expliciet vraagt om direct op te slaan.
        - Geef duidelijke, beknopte en coachende antwoorden.
        - Als een actie mislukt, leg uit waarom en stel alternatieven voor.

        ## Werken met resources, pagina's en widgets
        Je hebt globale tools voor ontdekking en uitvoering:
        - **list_resources** / **list_pages** / **list_widgets** — bekijk wat beschikbaar is
        - **get_tools** — ontdek de copilot-tools voor een specifieke resource, pagina of widget
        - **run_tool** — voer een ontdekte tool uit met de vereiste argumenten

        ### Werkwijze
        1. De context hieronder somt de beschikbare resources, pagina's en widgets met hun beschrijving op.
        2. Wil je een actie op een resource uitvoeren, gebruik dan **get_tools** met de resource-class om beschikbare tools te ontdekken.
        3. Gebruik **run_tool** om de specifieke tool met de vereiste argumenten uit te voeren.
        4. Vereist een tool bevestiging (needToAsk), vraag dit dan altijd eerst aan de gebruiker.

        ## Bevestigingsregels
        - Tools die "CONFIRMATION REQUIRED" teruggeven, vereisen expliciete bevestiging van de gebruiker voordat je ze opnieuw uitvoert.
        - Vraag de gebruiker altijd om bevestiging vóór destructieve acties (verwijderen, definitief verwijderen).

        ## Geheugen
        - Gebruik **remember** / **recall** om voorkeuren van de gebruiker op te slaan en op te halen tussen gesprekken.
        PROMPT,

    /*
    |--------------------------------------------------------------------------
    | Global Tools
    |--------------------------------------------------------------------------
    | Tool classes available on every page across all resources.
    | Each entry should be a class name that extends BaseTool.
    */

    'global_tools' => [
        \App\Filament\Copilot\Tools\ShooterContextTool::class,
    ],

];
