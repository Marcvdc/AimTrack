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
            ])
            ->where('user_id', $user->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($weaponIds, fn ($query) => $query->whereHas(
                'sessionWeapons',
                fn ($subQuery) => $subQuery->whereIn('weapon_id', $weaponIds)
            ))
            ->orderBy('date')
            ->get();

        return $format === 'pdf'
            ? $this->exportPdf($sessions, $from, $to)
            : $this->exportCsv($sessions, $from, $to);
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
        ];

        return response()->streamDownload(function () use ($sessions, $columns): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($sessions as $session) {
                $entries = $session->sessionWeapons;

                if ($entries->isEmpty()) {
                    $rangeName = $session->rangeLocationRef?->name ?? $session->range_name;
                    $locationName = $session->locationRef?->name ?? $session->location;

                    fputcsv($handle, [
                        $session->date?->format('Y-m-d'),
                        $rangeName,
                        $locationName,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        $session->notes_raw,
                    ]);

                    continue;
                }

                foreach ($entries as $entry) {
                    $weapon = $entry->weapon;
                    $rangeName = $session->rangeLocationRef?->name ?? $session->range_name;
                    $locationName = $session->locationRef?->name ?? $session->location;
                    $ammoLabel = $entry->ammoType?->name ?? $entry->ammo_type;

                    fputcsv($handle, [
                        $session->date?->format('Y-m-d'),
                        $rangeName,
                        $locationName,
                        $weapon?->name,
                        $weapon?->caliber,
                        $entry->distance_m,
                        $entry->rounds_fired,
                        $ammoLabel,
                        $entry->group_quality_text,
                        $entry->deviation?->value ?? $entry->deviation,
                        $entry->flyers_count,
                        $session->notes_raw,
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
            'sessions' => $sessions,
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
            ->flatMap(fn ($session) => $session->sessionWeapons)
            ->groupBy(function ($entry) {
                $weapon = $entry->weapon;

                return $weapon?->name.'|'.($weapon?->caliber ?? 'onbekend');
            })
            ->map(function ($entries, $key) {
                [$weaponName, $caliber] = explode('|', $key);

                return [
                    'weapon' => $weaponName ?: 'Onbekend wapen',
                    'caliber' => $caliber,
                    'sessions' => $entries->unique('session_id')->count(),
                    'rounds' => $entries->sum('rounds_fired'),
                ];
            })
            ->values();
    }
}
