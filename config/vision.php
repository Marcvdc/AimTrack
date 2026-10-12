<?php

return [
    /*
     * Het vision-model dat de kogelgaten op een roosfoto aanwijst. Dit staat los van
     * config('ai.model'): de coach mag een klein tekstmodel zijn, de detectie niet.
     */
    'model' => env('VISION_MODEL', 'claude-opus-5'),

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    'anthropic_version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    'max_tokens' => (int) env('VISION_MAX_TOKENS', 16000),

    /*
     * Terugvalsleutel voor de eval-harness op de commandline. In de applicatie zelf
     * komt de sleutel altijd per gebruiker uit AiKeyResolver; deze wordt daar nooit
     * voor gebruikt.
     */
    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
     * Langste zijde waarnaar een foto wordt verkleind voor verzending. De posities
     * komen genormaliseerd terug (centrum = 0, rand van ring 1 = 1), dus verkleinen
     * raakt de meetwaarden niet; het begrenst alleen de tokenkosten.
     */
    'max_image_dimension' => (int) env('VISION_MAX_IMAGE_DIMENSION', 1500),
    'jpeg_quality' => (int) env('VISION_JPEG_QUALITY', 90),

    /*
     * Geheugenlimiet voor de eval-harness op de commandline. Alleen nog nodig voor
     * de GD-terugval: die pakt een 12MP-foto uit tot ruim 48 MB aan bitmap plus de
     * verkleinde kopie, met een gemeten piek van 139 MB tegen een container die op
     * 128 MB staat. Met ImageMagick gebeurt het uitpakken in een apart proces en
     * blijft de gemeten piek op 42 MB, ruim binnen de standaardlimiet.
     */
    'cli_memory_limit' => env('VISION_CLI_MEMORY_LIMIT', '512M'),

    /*
     * Hoe diep het model mag nadenken. Dit is de grootste kostenknop die er is:
     * denk-tokens tellen als output en zijn dus het duurst. Verlagen maakt een run
     * goedkoper, maar verandert de uitkomst, dus niet halverwege een meetreeks
     * aanpassen.
     */
    'effort' => env('VISION_EFFORT', 'high'),

    /*
     * Prijs per miljoen tokens, om de kosten van een meting te kunnen rapporteren.
     * Standaard de tarieven van Opus 5. Dit is een schatting voor in het rapport,
     * geen factuur.
     */
    'price_input_per_million' => (float) env('VISION_PRICE_INPUT', 5.0),
    'price_output_per_million' => (float) env('VISION_PRICE_OUTPUT', 25.0),

    'connect_timeout' => (int) env('VISION_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('VISION_TIMEOUT', 180),

    /*
     * Schoten onder deze zekerheid worden niet als schot geteld. Overgenomen uit
     * settings.min_shot_confidence van de Python-service.
     */
    'min_shot_confidence' => (float) env('VISION_MIN_SHOT_CONFIDENCE', 0.25),
];
