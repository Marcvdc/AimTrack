<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Schietsessies export</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { margin-bottom: 4px; }
        h2 { margin-top: 24px; margin-bottom: 4px; }
        .small { font-size: 11px; color: #444; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d6d6d6; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f4f6fb; font-weight: 600; }
        th.w-20 { width: 20%; }
        th.w-15, td.w-15 { width: 15%; }
        th.w-10, td.w-10 { width: 10%; }
        th.w-8, td.w-8 { width: 8%; }
        .text-right { text-align: right; }
        .muted { color: #555; }
        .disclaimer { border: 1px dashed #999; padding: 10px; margin-top: 24px; background: #f9fafb; }
        .session-block { margin-top: 18px; padding: 16px; border: 1px solid #ececec; border-radius: 4px; break-inside: avoid; page-break-inside: avoid; }
        .session-header { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; font-size: 11px; }
        .session-header strong { display: block; font-size: 12px; margin-bottom: 2px; }
        .session-notes { margin-top: 8px; font-size: 11px; }
        .session-shots { margin-top: 6px; font-size: 11px; font-style: italic; color: #1f2937; }
        .session-table { margin-top: 12px; font-size: 11px; }
        .page-break { page-break-before: always; }
        .wrap { word-break: break-word; }
    </style>
</head>
<body>
    <h1>Overzicht schietsessies</h1>
    <p class="small">Periode: {{ $from->toFormattedDateString() }} t/m {{ $to->toFormattedDateString() }}</p>

    <h2>Samenvatting per wapen/kaliber</h2>
    <table>
        <thead>
            <tr>
                <th>Wapen</th>
                <th>Kaliber</th>
                <th>Aantal sessies</th>
                <th>Afgevuurde rondes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($totals as $total)
                <tr>
                    <td>{{ $total['weapon'] }}</td>
                    <td>{{ $total['caliber'] }}</td>
                    <td>{{ $total['sessions'] }}</td>
                    <td>{{ $total['rounds'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Geen sessies in deze periode.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Sessies</h2>
    @forelse ($sessionExports as $session)
        <div class="session-block">
            <div class="session-header">
                <div>
                    <strong>Datum</strong>
                    {{ optional($session['date'])?->format('Y-m-d') ?? 'Onbekend' }}
                </div>
                <div>
                    <strong>Baan</strong>
                    {{ $session['range_name'] ?? 'Onbekend' }}
                </div>
                <div>
                    <strong>Locatie</strong>
                    {{ $session['location_name'] ?? 'Onbekend' }}
                </div>
                <div>
                    <strong>Shot samenvatting</strong>
                    {{ $session['shots_summary_text'] ?? 'n.v.t.' }}
                </div>
            </div>

            <div class="session-notes">
                <strong>Notities</strong><br>
                {{ $session['notes'] ?: 'n.v.t.' }}
            </div>

            @php($entries = $session['entries'])
            <table class="session-table">
                <thead>
                    <tr>
                        <th class="w-15">Wapen</th>
                        <th class="w-8">Kaliber</th>
                        <th class="w-10 text-right">Afstand (m)</th>
                        <th class="w-10 text-right">Rondes</th>
                        <th class="w-15">Munitietype</th>
                        <th class="w-15">Groepering</th>
                        <th class="w-10">Afwijking</th>
                        <th class="w-8 text-right">Flyers</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="wrap">{{ $entry['weapon_name'] ?? 'Onbekend' }}</td>
                            <td>{{ $entry['weapon_caliber'] ?? 'n.v.t.' }}</td>
                            <td class="text-right">{{ $entry['distance_m'] ?? 'n.v.t.' }}</td>
                            <td class="text-right">{{ $entry['rounds_fired'] ?? 'n.v.t.' }}</td>
                            <td class="wrap">{{ $entry['ammo_label'] ?? 'n.v.t.' }}</td>
                            <td class="wrap">{{ $entry['grouping'] ?? 'n.v.t.' }}</td>
                            <td>{{ $entry['deviation'] ?? 'n.v.t.' }}</td>
                            <td class="text-right">{{ $entry['flyers_count'] ?? '0' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">Geen wapens geregistreerd voor deze sessie.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p>Geen sessies gevonden.</p>
    @endforelse

    <div class="disclaimer small">
        <strong>Disclaimer:</strong> {{ $disclaimer }}
    </div>
</body>
</html>
