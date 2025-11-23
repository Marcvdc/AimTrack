<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Schietsessies export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1, h2 { margin-bottom: 0; }
        .small { font-size: 11px; color: #444; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f7f7f7; }
        .muted { color: #555; }
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
    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Baan</th>
                <th>Locatie</th>
                <th>Wapen</th>
                <th>Kaliber</th>
                <th>Afstand (m)</th>
                <th>Rondes</th>
                <th>Munitietype</th>
                <th>Groepering</th>
                <th>Afwijking</th>
                <th>Flyers</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                @php $entries = $session->sessionWeapons; @endphp
                @if ($entries->isEmpty())
                    <tr>
                        <td>{{ $session->date?->format('Y-m-d') }}</td>
                        <td>{{ $session->range_name }}</td>
                        <td>{{ $session->location }}</td>
                        <td colspan="8" class="muted">Geen wapens geregistreerd</td>
                    </tr>
                @else
                    @foreach ($entries as $entry)
                        <tr>
                            <td>{{ $session->date?->format('Y-m-d') }}</td>
                            <td>{{ $session->range_name }}</td>
                            <td>{{ $session->location }}</td>
                            <td>{{ $entry->weapon?->name }}</td>
                            <td>{{ $entry->weapon?->caliber }}</td>
                            <td>{{ $entry->distance_m }}</td>
                            <td>{{ $entry->rounds_fired }}</td>
                            <td>{{ $entry->ammo_type }}</td>
                            <td>{{ $entry->group_quality_text }}</td>
                            <td>{{ $entry->deviation?->value ?? $entry->deviation }}</td>
                            <td>{{ $entry->flyers_count }}</td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr>
                    <td colspan="11">Geen sessies gevonden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="small" style="margin-top: 14px;">{{ $disclaimer }}</p>
</body>
</html>
