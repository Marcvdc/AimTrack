<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximale leeftijd van de oudste wachtende queue-job
    |--------------------------------------------------------------------------
    |
    | Is de oudste job die al uitgevoerd had kunnen worden ouder dan dit aantal
    | seconden, dan meldt /health `queue_stalled`: er draait dan waarschijnlijk
    | geen worker meer. Alleen van toepassing op de database-queue.
    |
    */

    'queue_max_age_seconds' => (int) env('HEALTH_QUEUE_MAX_AGE_SECONDS', 300),

];
