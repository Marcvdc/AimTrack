<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AimTrack • Schiethub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|manrope:400,500,600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bg-start: #040711;
            --bg-end: #0c1428;
            --text: #e3ecff;
            --text-muted: #94a3c5;
            --card: rgba(13, 20, 41, .65);
            --border: rgba(255, 255, 255, .08);
            --mint: #64f4b3;
            --indigo: #7a8cff;
            --danger: #f87171;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Manrope', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--text);
            background: radial-gradient(circle at top, rgba(100, 244, 179, 0.15), transparent 35%) ,
                        radial-gradient(circle at 20% 20%, rgba(122, 140, 255, 0.12), transparent 25%) ,
                        linear-gradient(135deg, var(--bg-start), var(--bg-end));
            min-height: 100vh;
        }
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: url('data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" width=\"160\" height=\"160\" viewBox=\"0 0 160 160\"%3E%3Crect width=\"1\" height=\"1\" fill=\"%230c1224\"/%3E%3C/svg%3E');
            opacity: .3;
            pointer-events: none;
        }
        main {
            position: relative;
            padding: 80px 24px 160px;
            max-width: 1200px;
            margin: 0 auto;
        }
        h1, h2 {
            font-family: 'Space Grotesk', 'Manrope', sans-serif;
            letter-spacing: -0.02em;
        }
        h1 {
            font-size: clamp(2.5rem, 4vw, 4rem);
            margin: 0 0 16px;
        }
        h2 {
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            margin: 0 0 12px;
        }
        p {
            margin: 0 0 1rem;
            line-height: 1.6;
            color: var(--text-muted);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(122, 140, 255, .15);
            color: var(--indigo);
            font-weight: 600;
            font-size: .95rem;
            margin-bottom: 16px;
        }
        .badge svg {
            width: 18px;
            height: 18px;
        }
        .hero {
            display: grid;
            gap: 32px;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            align-items: center;
            margin-bottom: 72px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 40px 90px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(18px);
        }
        .cta-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 24px;
        }
        .btn {
            border: none;
            border-radius: 999px;
            font-size: 1.05rem;
            padding: 16px 28px;
            font-weight: 600;
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
            text-decoration: none;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(120deg, var(--mint), #44d89b);
            color: #051810;
            box-shadow: 0 15px 40px rgba(100, 244, 179, 0.35);
        }
        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, .25);
            color: var(--text);
        }
        .btn:hover, .btn:focus-visible {
            transform: translateY(-2px);
        }
        .stats-card {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
        }
        .stat {
            background: rgba(4, 7, 17, .55);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid var(--border);
        }
        .stat span {
            display: block;
            font-size: .85rem;
            color: var(--text-muted);
        }
        .stat strong {
            font-size: 1.6rem;
        }
        .heatmap {
            height: 180px;
            border-radius: 18px;
            border: 1px dashed rgba(255, 255, 255, .15);
            background: radial-gradient(circle at 60% 40%, rgba(255, 255, 255, .4), transparent 45%),
                        radial-gradient(circle at 40% 60%, rgba(100, 244, 179, .5), transparent 55%),
                        rgba(9, 13, 25, .7);
            position: relative;
        }
        .heatmap::after {
            content: 'Sessies heatmap (demo)';
            position: absolute;
            bottom: 12px;
            right: 16px;
            font-size: .85rem;
            color: var(--text-muted);
        }
        .section {
            margin-bottom: 72px;
        }
        .section-header {
            margin-bottom: 24px;
        }
        .section p {
            max-width: 640px;
        }
        .grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .list-card h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        .list-card ul {
            padding-left: 20px;
            color: var(--text-muted);
            margin: 12px 0 0;
            line-height: 1.5;
        }
        .resource-card a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--mint);
            text-decoration: none;
            font-weight: 600;
            margin-top: 12px;
        }
        .resource-card small {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: .75rem;
            color: var(--text-muted);
        }
        .quickstart-card {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .quickstart-card span {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: rgba(100, 244, 179, .18);
            display: grid;
            place-items: center;
            font-weight: 700;
            color: var(--mint);
        }
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .option-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .option-item p {
            margin: 4px 0 0;
        }
        .sticky-cta {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: rgba(13, 20, 41, .8);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 8px;
            display: none;
            z-index: 50;
        }
        .sticky-cta a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        footer {
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: center;
            color: var(--text-muted);
        }
        footer a {
            color: var(--indigo);
            text-decoration: none;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            main { padding-top: 60px; }
            .card { padding: 24px; }
        }
        @media (max-width: 640px) {
            .cta-group { flex-direction: column; }
            .sticky-cta { display: inline-flex; left: 24px; right: 24px; }
        }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <div>
            <span class="badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="12" r="10" opacity=".4"></circle>
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v4M12 18v4M2 12h4M18 12h4"></path>
                </svg>
                AimTrack Schiethub
            </span>
            <h1>Log je sessies, blijf veilig en leer van elke serie.</h1>
            <p>Een persoonlijke hub voor sportschutters: sessies registreren, wapens beheren en direct inzichten krijgen van een AI-coach. Geïnspireerd op de KNSA-veiligheidsrichtlijnen.</p>
            <div class="cta-group">
                <a class="btn btn-primary" href="/admin/login">Inloggen</a>
                <a class="btn btn-secondary" href="#handleiding">Handleiding &amp; quickstart</a>
            </div>
        </div>
        <div class="card stats-card">
            <div class="heatmap"></div>
            <div class="stats-grid">
                <div class="stat">
                    <span>Sessies deze maand</span>
                    <strong>48</strong>
                </div>
                <div class="stat">
                    <span>Gem. score</span>
                    <strong>92.4</strong>
                </div>
                <div class="stat">
                    <span>AI reflecties</span>
                    <strong>17</strong>
                </div>
            </div>
            <p style="margin:0;color:var(--text-muted);font-size:.9rem;">Live data vanuit AimTrack • Dashboard preview</p>
        </div>
    </section>

    <section class="section" aria-labelledby="features">
        <div class="section-header">
            <h2 id="features">Waarom AimTrack?</h2>
            <p>Sessies, wapens, reflecties en exports samen in één hub – ontworpen voor gebruik op de baan én thuis.</p>
        </div>
        <div class="grid">
            <div class="card list-card">
                <h3>Interactieve sessies &amp; roos</h3>
                <ul>
                    <li>Per schot coördinaten en ring-score</li>
                    <li>Totaaloverzicht per beurt</li>
                </ul>
            </div>
            <div class="card list-card">
                <h3>Wapenbeheer</h3>
                <ul>
                    <li>Bijhouden van wapentypes, onderhoud en gebruik</li>
                    <li>Opslagnotities en serienummers veilig</li>
                </ul>
            </div>
            <div class="card list-card">
                <h3>AI-coach</h3>
                <ul>
                    <li>Reflectie op je sessie met concrete tips</li>
                    <li>Vrije vragen zoals trainingsfocus</li>
                </ul>
            </div>
            <div class="card list-card">
                <h3>Export &amp; rapportage</h3>
                <ul>
                    <li>CSV / PDF voor trainers of WM-4 aanvragen</li>
                    <li>Filters op periode en wapen</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="knsa">
        <div class="section-header">
            <h2 id="knsa">KNSA referenties</h2>
            <p>Belangrijkste artikelen om veilig en up-to-date te blijven. Links openen in een nieuw tabblad.</p>
        </div>
        <div class="grid">
            <article class="card resource-card">
                <small>Bron: KNSA</small>
                <h3>Veilig wapenbezit</h3>
                <p>De 10 basisregels voor verantwoord omgaan met je wapen, thuis en op de baan.</p>
                <a href="https://www.knsa.nl/veiligheid/veilig-wapenbezit" target="_blank" rel="noreferrer noopener">Lees de regels →</a>
            </article>
            <article class="card resource-card">
                <small>Bron: KNSA</small>
                <h3>Checklist baanveiligheid</h3>
                <p>Gebruik de checklist vóór elke sessie om baancondities en procedures te verifiëren.</p>
                <a href="https://www.knsa.nl/veiligheid/checklist-baanveiligheid" target="_blank" rel="noreferrer noopener">Open checklist →</a>
            </article>
            <article class="card resource-card">
                <small>Bron: KNSA</small>
                <h3>Wedstrijdkalender &amp; reglementen</h3>
                <p>Bereid trainingen voor volgens actuele wedstrijdregels en plan je agenda.</p>
                <a href="https://www.knsa.nl/wedstrijden/reglementen" target="_blank" rel="noreferrer noopener">Bekijk reglementen →</a>
            </article>
        </div>
    </section>

    <section class="section" id="handleiding" aria-labelledby="quickstart">
        <div class="section-header">
            <h2 id="quickstart">Snelle handleiding</h2>
            <p>Drie mini-kaarten laten zien hoe je binnen vijf minuten start en veilig logt.</p>
        </div>
        <div class="grid">
            <article class="card quickstart-card">
                <span>01</span>
                <h3>Start je eerste sessie</h3>
                <p>Log in → voeg wapen en baan toe → start sessie. Leg elke serie vast via de schietroos.</p>
            </article>
            <article class="card quickstart-card">
                <span>02</span>
                <h3>Veiligheidscheck</h3>
                <p>Klik door naar de KNSA-checklist en noteer baancondities &amp; eventuele deviatie voordat je schiet.</p>
            </article>
            <article class="card quickstart-card">
                <span>03</span>
                <h3>Ontdek AI-coach</h3>
                <p>Voorbeeldvraag: “Hoe verbeter ik mijn 25m vrij pistool groep?” Krijg passende reflecties en focuspunten.</p>
            </article>
        </div>
    </section>

    <section class="section" aria-labelledby="addons">
        <div class="section-header">
            <h2 id="addons">Uitbreidingsopties</h2>
            <p>Kies wat je zichtbaar wilt maken op de landing. Blokken zijn optioneel en kunnen later worden geactiveerd.</p>
        </div>
        <div class="card options-list">
            <div class="option-item">
                <div>
                    <strong>Live statistieken</strong>
                    <p>Totalen, accuracy-trends en wapenfrequentie uit jouw database.</p>
                </div>
                <span style="color:var(--mint);font-weight:600;">Aanbevolen</span>
            </div>
            <div class="option-item">
                <div>
                    <strong>Community &amp; verhalen</strong>
                    <p>Laat quotes of successen van schutters zien, met link naar blog of nieuwsbrief.</p>
                </div>
                <span style="color:var(--indigo);font-weight:600;">Optioneel</span>
            </div>
            <div class="option-item">
                <div>
                    <strong>Roadmap &amp; partners</strong>
                    <p>Highlight toekomstige features en eventuele KNSA- of baanpartners.</p>
                </div>
                <span style="color:var(--text-muted);font-weight:600;">Placeholder</span>
            </div>
        </div>
    </section>

    <footer>
        <p>Direct inloggen op AimTrack Filament admin.</p>
        <a href="/admin/login">Ga naar /admin/login</a>
        <p>&copy; <span id="year"></span> AimTrack — gebouwd voor sportschutters.</p>
    </footer>
</main>

<div class="sticky-cta">
    <a class="btn btn-primary" href="/admin/login">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#051810" stroke-width="1.8">
            <path d="M5 12h14"></path>
            <path d="M13 6l6 6-6 6"></path>
            <path d="M7 5V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v-1"></path>
        </svg>
        Inloggen
    </a>
</div>

<script>
    document.getElementById('year').textContent = new Date().getFullYear();
</script>
</body>
</html>
