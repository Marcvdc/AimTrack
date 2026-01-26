<?php

namespace App\Services\Export;

use App\Models\Session;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionExportService
{
    /**
     * Exporteer sessies van een gebruiker binnen een periode en optionele wapenfilter.
     */
    public function exportSessions(User $user, Carbon $from, Carbon $to, ?array $weaponIds, string $format): Response|StreamedResponse
    {
        $sessions = Session::query()
            ->with([
                'sessionWeapons.weapon',
                'sessionWeapons.ammoType',
                'locationRef',
                'rangeLocationRef',
                'shots',
            ])
            ->where('user_id', $user->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($weaponIds, fn ($query) => $query->whereHas(
                'sessionWeapons',
                fn ($subQuery) => $subQuery->whereIn('weapon_id', $weaponIds)
            ))
            ->orderBy('date')
            ->get();

        $sessionData = $this->prepareSessionsData($sessions);

        return $format === 'pdf'
            ? $this->exportPdf($sessionData, $from, $to)
            : $this->exportCsv($sessionData, $from, $to);
    }

    protected function exportCsv(Collection $sessions, Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = sprintf('sessions_%s_%s.csv', $from->format('Ymd'), $to->format('Ymd'));
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $columns = [
            'Datum',
            'Baan',
            'Locatie',
            'Wapen',
            'Kaliber',
            'Afstand (m)',
            'Rondes',
            'Munitietype',
            'Groepering',
            'Afwijking',
            'Flyers',
            'Notities',
            'Shot samenvatting',
        ];

        return response()->streamDownload(function () use ($sessions, $columns): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($sessions as $session) {
                /** @var \Illuminate\Support\Collection $entries */
                $entries = $session['entries'];
                $shotsSummary = $session['shots_summary_text'];

                if ($entries->isEmpty()) {
                    fputcsv($handle, [
                        optional($session['date'])?->format('Y-m-d'),
                        $session['range_name'],
                        $session['location_name'],
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        $session['notes'],
                        $shotsSummary,
                    ]);

                    continue;
                }

                foreach ($entries as $entry) {
                    fputcsv($handle, [
                        optional($session['date'])?->format('Y-m-d'),
                        $session['range_name'],
                        $session['location_name'],
                        $entry['weapon_name'],
                        $entry['weapon_caliber'],
                        $entry['distance_m'],
                        $entry['rounds_fired'],
                        $entry['ammo_label'],
                        $entry['grouping'],
                        $entry['deviation'],
                        $entry['flyers_count'],
                        $session['notes'],
                        $shotsSummary,
                    ]);
                }
            }

            fclose($handle);
        }, $filename, $headers);
    }

    protected function exportPdf(Collection $sessions, Carbon $from, Carbon $to): Response
    {
        $totals = $this->aggregateTotals($sessions);

        $pdf = Pdf::loadView('exports.sessions', [
            'sessionExports' => $sessions,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'disclaimer' => 'Let op: dit document is een hulpmiddel; controleer altijd zelf of dit voldoet aan de actuele eisen van de politie / korpschef voor een WM-4 aanvraag.',
        ]);

        $filename = sprintf('sessions_%s_%s.pdf', $from->format('Ymd'), $to->format('Ymd'));

        return $pdf->download($filename);
    }

    protected function aggregateTotals(Collection $sessions): Collection
    {
        return $sessions
            ->flatMap(fn ($session) => $session['entries'])
            ->groupBy(function ($entry) {
                $weaponName = $entry['weapon_name'] ?? 'Onbekend wapen';
                $caliber = $entry['weapon_caliber'] ?? 'onbekend';

                return $weaponName.'|'.$caliber;
            })
            ->map(function ($entries, $key) {
                [$weaponName, $caliber] = explode('|', $key);

                return [
                    'weapon' => $weaponName ?: 'Onbekend wapen',
                    'caliber' => $caliber,
                    'sessions' => $entries->pluck('session_id')->unique()->count(),
                    'rounds' => $entries->sum('rounds_fired'),
                ];
            })
            ->values();
    }

    protected function prepareSessionsData(Collection $sessions): Collection
    {
        return $sessions->map(function (Session $session) {
            $rangeName = $session->rangeLocationRef?->name ?? $session->range_name;
            $locationName = $session->locationRef?->name ?? $session->location;
            $shotsSummary = $this->summarizeShots($session->shots);

            return [
                'id' => $session->getKey(),
                'date' => $session->date?->clone(),
                'range_name' => $rangeName,
                'location_name' => $locationName,
                'notes' => $session->notes_raw,
                'entries' => $session->sessionWeapons->map(fn ($entry) => [
                    'session_id' => $session->getKey(),
                    'weapon_name' => $entry->weapon?->name,
                    'weapon_caliber' => $entry->weapon?->caliber,
                    'distance_m' => $entry->distance_m,
                    'rounds_fired' => $entry->rounds_fired,
                    'ammo_label' => $entry->ammoType?->name ?? $entry->ammo_type,
                    'grouping' => $entry->group_quality_text,
                    'deviation' => $entry->deviation?->value ?? $entry->deviation,
                    'flyers_count' => $entry->flyers_count,
                ])->values(),
                'shots_summary' => $shotsSummary,
                'shots_summary_text' => $this->formatShotsSummary($shotsSummary),
            ];
        });
    }

    protected function summarizeShots(Collection $shots): array
    {
        if ($shots->isEmpty()) {
            return [
                'total' => 0,
                'average_score' => null,
                'average_ring' => null,
                'best_score' => null,
                'best_ring' => null,
            ];
        }

        $averageScore = $shots->avg('score');
        $averageRing = $shots->avg('ring');

        return [
            'total' => $shots->count(),
            'average_score' => $averageScore !== null ? round($averageScore, 1) : null,
            'average_ring' => $averageRing !== null ? round($averageRing, 1) : null,
            'best_score' => $shots->max('score'),
            'best_ring' => $shots->max('ring'),
        ];
    }

    protected function formatShotsSummary(array $summary): string
    {
        if ($summary['total'] === 0) {
            return 'n.v.t.';
        }

        $averageScore = $summary['average_score'] !== null
            ? number_format($summary['average_score'], 1, ',', '.')
            : 'n.v.t.';
        $bestScore = $summary['best_score'] ?? 'n.v.t.';
        $averageRing = $summary['average_ring'] !== null
            ? number_format($summary['average_ring'], 1, ',', '.')
            : 'n.v.t.';

        return sprintf(
            '%d schoten | gem. score %s | beste score %s | gem. ring %s',
            $summary['total'],
            $averageScore,
            $bestScore,
            $averageRing,
        );
    }
}
