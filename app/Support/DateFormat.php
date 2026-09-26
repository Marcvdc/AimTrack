<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * De datumnotatie van AimTrack, op één plek.
 *
 * Vóór deze klasse toonde dezelfde applicatie een datum op acht manieren
 * (d/m/Y, d-m-Y, Y-m-d, d M, d M Y, M Y, l d F Y) plus de Amerikaanse
 * Filament-default "M j, Y" bij kale ->date()-aanroepen. De notatie is nu
 * de Nederlandse: dag-maand-jaar met streepjes.
 *
 * Twee gedocumenteerde uitzonderingen:
 *  - MONTH voor trendassen die op maandbuckets draaien. Een maandbucket is
 *    geen datum; er in een dagnummer bij verzinnen zou liegen.
 *  - MACHINE_DATE (ISO 8601) voor output die door een machine gelezen wordt:
 *    de CSV-export en de payloads naar het AI-model. Die willen een
 *    sorteerbare, eenduidige datum, geen leesbare.
 *
 * Tijdzone: de database staat in UTC (config app.timezone), de gebruiker
 * leest Europe/Amsterdam (config app.display_timezone). De datetime-helpers
 * hieronder schuiven daarom naar de weergave-tijdzone. date() en month()
 * doen dat bewust niet: een datumkolom draagt geen tijdstip, dus converteren
 * zou een sessie van 1 januari op 31 december kunnen laten landen.
 */
final class DateFormat
{
    public const DATE = 'd-m-Y';

    public const DATE_TIME = 'd-m-Y H:i';

    public const TIME = 'H:i';

    /** Maand-granulariteit, uitsluitend voor trendassen op maandbuckets. */
    public const MONTH = 'M Y';

    /** ISO 8601, uitsluitend voor machine-leesbare output (CSV, AI-payloads). */
    public const MACHINE_DATE = 'Y-m-d';

    public static function displayTimezone(): string
    {
        return (string) config('app.display_timezone', 'UTC');
    }

    public static function date(?DateTimeInterface $value): ?string
    {
        return $value?->format(self::DATE);
    }

    public static function dateTime(?DateTimeInterface $value): ?string
    {
        return self::inDisplayTimezone($value)?->format(self::DATE_TIME);
    }

    public static function time(?DateTimeInterface $value): ?string
    {
        return self::inDisplayTimezone($value)?->format(self::TIME);
    }

    public static function month(?DateTimeInterface $value): ?string
    {
        return $value === null
            ? null
            : Carbon::instance($value)->translatedFormat(self::MONTH);
    }

    public static function machineDate(?DateTimeInterface $value): ?string
    {
        return $value?->format(self::MACHINE_DATE);
    }

    private static function inDisplayTimezone(?DateTimeInterface $value): ?Carbon
    {
        return $value === null
            ? null
            : Carbon::instance($value)->setTimezone(self::displayTimezone());
    }
}
