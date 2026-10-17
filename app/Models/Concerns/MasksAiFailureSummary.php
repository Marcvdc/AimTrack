<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * Tot #186 schreef de AI-laag bij een fout een foutstring als samenvatting weg.
 * Die rijen staan nog in de database; toon in plaats daarvan de nette
 * melding uit de constante FAILED_SUMMARY van het model.
 */
trait MasksAiFailureSummary
{
    /**
     * Begin van de foutstrings die vóór #186 als samenvatting zijn opgeslagen.
     *
     * @var array<int, string>
     */
    private static array $legacyFailurePrefixes = [
        'AI-configuratie ontbreekt.',
        'Geen AI-antwoord beschikbaar (',
    ];

    protected function summary(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => self::isLegacyFailureSummary($value)
            ? static::FAILED_SUMMARY
            : $value);
    }

    private static function isLegacyFailureSummary(?string $value): bool
    {
        return is_string($value) && Str::startsWith($value, self::$legacyFailurePrefixes);
    }
}
