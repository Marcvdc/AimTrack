<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AimTrack — Je schietsessies, scherp in beeld.</title>
    <meta name="description" content="AimTrack logt je schietsessies, herkent patronen in je groepering en geeft per sessie een AI-reflectie. Self-hosted, WM-4 conform, open-source.">

    {{-- Webfonts (Inter + JetBrains Mono) + Signal Mint design tokens — één bron
         van waarheid, gedeeld met het Filament-panel via AdminPanelProvider. --}}
    <x-aimtrack.head-assets />

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        .mk-body {
            margin: 0;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            color: var(--at-text);
            font-family: var(--at-font-body);
            background: linear-gradient(to bottom, var(--at-bg), var(--at-panel) 60%, var(--at-bg));
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .mk-body a:focus-visible,
        .mk-body button:focus-visible {
            outline: 2px solid var(--at-accent);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* ── Decorative reticle, top-right ──────────────────────────── */
        .mk-reticle-deco {
            position: absolute;
            top: 40px;
            right: 60px;
            z-index: 0;
            pointer-events: none;
        }

        /* ── Shared bits ────────────────────────────────────────────── */
        .mk-section-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: var(--at-font-mono);
            font-size: 10px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--at-accent);
        }
        .mk-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--at-accent);
            display: inline-block;
            flex: 0 0 auto;
        }

        .mk-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: var(--at-font-body);
            transition: transform .15s ease, opacity .15s ease, background .15s ease;
        }
        .mk-btn:hover { transform: translateY(-1px); }
        .mk-btn-primary {
            padding: 14px 22px;
            border-radius: 10px;
            background: var(--at-accent);
            color: var(--at-cta-text);
            font-weight: 600;
            font-size: 15px;
        }
        .mk-btn-outline {
            padding: 14px 22px;
            border-radius: 10px;
            border: 1px solid var(--at-line);
            background: transparent;
            color: var(--at-text);
            font-size: 15px;
        }
        .mk-btn-outline:hover { border-color: var(--at-accent-50, var(--at-accent)); }

        /* ── Nav ────────────────────────────────────────────────────── */
        .mk-nav {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 28px;
            padding: 18px 64px;
            background: color-mix(in srgb, var(--at-bg) 80%, transparent);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--at-line);
        }
        .mk-brand { display: inline-flex; text-decoration: none; }
        .mk-nav-links {
            display: flex;
            gap: 24px;
            margin-left: 24px;
            font-size: 13px;
        }
        .mk-nav-link { color: var(--at-muted); text-decoration: none; transition: color .15s ease; }
        .mk-nav-link:hover, .mk-nav-link.is-active { color: var(--at-text); }
        .mk-nav-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mk-login-link { font-size: 13px; color: var(--at-muted); text-decoration: none; transition: color .15s ease; }
        .mk-login-link:hover { color: var(--at-text); }
        .mk-btn-nav {
            padding: 8px 14px;
            border-radius: 8px;
            border: none;
            background: var(--at-accent);
            color: var(--at-cta-text);
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        /* ── Hero ───────────────────────────────────────────────────── */
        .mk-hero {
            position: relative;
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 60px;
            align-items: center;
            padding: 88px 64px 64px;
        }
        .mk-hero .mk-section-label { margin-bottom: 18px; }
        .mk-h1 {
            margin: 0;
            font-family: var(--at-font-display);
            font-size: 64px;
            font-weight: 600;
            letter-spacing: -0.03em;
            line-height: 1.02;
            color: var(--at-text);
        }
        .mk-accent { color: var(--at-accent); }
        .mk-lead {
            margin: 22px 0 0;
            max-width: 520px;
            font-size: 18px;
            line-height: 1.55;
            color: var(--at-muted);
        }
        .mk-cta-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
        .mk-pills { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        .mk-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border: 1px solid var(--at-line);
            border-radius: 999px;
            font-family: var(--at-font-mono);
            font-size: 11px;
            letter-spacing: 0.04em;
            color: var(--at-muted);
        }

        .mk-hero-visual { display: flex; align-items: center; justify-content: center; }
        .mk-hero-stage { position: relative; width: 420px; height: 420px; }
        .mk-hero-stage .mk-reticle-frame { position: absolute; inset: 0; }
        .mk-rings-center { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }

        .mk-callout { position: absolute; padding: 8px 12px; border-radius: 8px; }
        .mk-callout-score {
            top: 12px; left: -36px;
            background: var(--at-panel);
            border: 1px solid var(--at-line);
            box-shadow: 0 8px 24px color-mix(in srgb, var(--at-bg) 50%, transparent);
        }
        .mk-callout-group {
            bottom: 16px; right: -28px;
            background: var(--at-panel);
            border: 1px solid var(--at-line);
        }
        .mk-callout-ai {
            top: 52%; right: -56px;
            background: color-mix(in srgb, var(--at-accent) 8%, transparent);
            border: 1px solid color-mix(in srgb, var(--at-accent) 25%, transparent);
        }
        .mk-callout-label {
            font-family: var(--at-font-mono);
            font-size: 9px;
            letter-spacing: 0.16em;
            color: var(--at-muted);
        }
        .mk-callout-value {
            font-family: var(--at-font-mono);
            font-size: 22px;
            font-weight: 600;
            color: var(--at-accent);
        }
        .mk-callout-group .mk-callout-value { color: var(--at-text); }
        .mk-callout-unit { font-size: 12px; color: var(--at-muted); margin-left: 4px; }
        .mk-callout-ai .mk-callout-label { color: var(--at-accent); }
        .mk-callout-text { font-size: 11px; color: var(--at-text); margin-top: 2px; }

        /* ── Trust strip ────────────────────────────────────────────── */
        .mk-trust {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 40px;
            padding: 24px 64px;
            border-top: 1px solid var(--at-line);
            border-bottom: 1px solid var(--at-line);
        }
        .mk-trust-label {
            font-family: var(--at-font-mono);
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--at-muted);
        }
        .mk-trust-logos {
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            align-items: center;
            gap: 40px;
            font-family: var(--at-font-display);
            font-size: 16px;
            color: var(--at-muted);
            opacity: 0.65;
        }

        /* ── Responsive ─────────────────────────────────────────────── */
        @media (max-width: 1024px) {
            .mk-hero { grid-template-columns: 1fr; padding: 64px 32px 48px; }
            .mk-nav, .mk-trust { padding-left: 32px; padding-right: 32px; }
        }
        @media (max-width: 768px) {
            .mk-nav-links { display: none; }
            .mk-h1 { font-size: 44px; }
            .mk-lead { font-size: 16px; }
            .mk-trust { gap: 20px; }
            .mk-trust-logos { gap: 22px; font-size: 14px; }
            .mk-reticle-deco { right: -40px; opacity: .6; }
        }
        @media (max-width: 520px) {
            .mk-nav { padding: 14px 18px; gap: 12px; }
            .mk-hero { padding: 44px 18px 36px; }
            .mk-hero-stage { transform: scale(.74); margin: -55px 0; }
            .mk-cta-row .mk-btn { flex: 1 1 auto; }
        }
    </style>
</head>
<body class="mk-body">

    <div class="mk-reticle-deco">
        <x-aimtrack.reticle :size="420" color="var(--at-accent)" :stroke="1" :opacity="0.07" :dot="true" aria-hidden="true" />
    </div>

    {{-- ── Nav ──────────────────────────────────────────────────────── --}}
    <nav class="mk-nav">
        <a class="mk-brand" href="{{ route('welcome') }}" aria-label="AimTrack — naar boven">
            <x-aimtrack.wordmark :size="26" />
        </a>
        <div class="mk-nav-links">
            <a class="mk-nav-link is-active" href="#features">Functies</a>
            <a class="mk-nav-link" href="#ai-coach">AI-coach</a>
            <a class="mk-nav-link" href="#self-hosted">Self-hosted</a>
            <a class="mk-nav-link" href="#pricing">Prijzen</a>
            <a class="mk-nav-link" href="https://github.com/marcvdc/AimTrack" target="_blank" rel="noopener noreferrer">GitHub</a>
        </div>
        <div class="mk-nav-actions">
            <a class="mk-login-link" href="/admin/login">Inloggen</a>
            <a class="mk-btn-nav" href="/admin/login">Probeer gratis</a>
        </div>
    </nav>

    {{-- ── Hero ─────────────────────────────────────────────────────── --}}
    <section class="mk-hero">
        <div class="mk-hero-copy">
            <div class="mk-section-label"><span class="mk-dot" aria-hidden="true"></span> Voor sportschutters · NL</div>
            <h1 class="mk-h1">Je schietsessies,<br><span class="mk-accent">scherp in beeld.</span></h1>
            <p class="mk-lead">
                AimTrack logt je trainingen, herkent patronen in je groepering en
                geeft per sessie een AI-reflectie. Self-hosted, WM-4 conform,
                zonder gedoe.
            </p>
            <div class="mk-cta-row">
                <a class="mk-btn mk-btn-primary" href="/admin/login">
                    30 dagen gratis proberen
                    <x-aimtrack.icon name="arrow" :size="14" color="var(--at-cta-text)" :stroke="2" />
                </a>
                <a class="mk-btn mk-btn-outline" href="https://github.com/marcvdc/AimTrack" target="_blank" rel="noopener noreferrer">
                    <x-aimtrack.icon name="shield" :size="14" />
                    Bekijk op GitHub
                </a>
            </div>
            <div class="mk-pills">
                <span class="mk-pill">● Self-hosted</span>
                <span class="mk-pill">● WM-4 export</span>
                <span class="mk-pill">● AI-reflectie</span>
                <span class="mk-pill">● Open-source</span>
            </div>
        </div>

        <div class="mk-hero-visual">
            <div class="mk-hero-stage">
                <div class="mk-reticle-frame">
                    <x-aimtrack.reticle :size="420" color="var(--at-accent)" :stroke="1.5" :opacity="0.85" aria-hidden="true" />
                </div>
                <div class="mk-rings-center">
                    <x-aimtrack.target-rings :size="220" accent="var(--at-accent)" dim="var(--at-text)" :ring-stroke="1" />
                </div>

                <div class="mk-callout mk-callout-score">
                    <div class="mk-callout-label">SCORE</div>
                    <div class="mk-callout-value">547</div>
                </div>
                <div class="mk-callout mk-callout-group">
                    <div class="mk-callout-label">GROEP</div>
                    <div class="mk-callout-value">22<span class="mk-callout-unit">mm</span></div>
                </div>
                <div class="mk-callout mk-callout-ai">
                    <div class="mk-callout-label">● AI</div>
                    <div class="mk-callout-text">Sterke opening,<br>dip schot 35</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Trust strip ──────────────────────────────────────────────── --}}
    <section class="mk-trust">
        <div class="mk-trust-label">Gebruikt door sportschutters bij</div>
        <div class="mk-trust-logos">
            <span>SV Diemen</span>
            <span>Schuttersgilde Hilversum</span>
            <span>KNSA · regio West</span>
            <span>SV De Adelaar</span>
            <span>Pistoolclub Utrecht</span>
        </div>
    </section>

    {{-- Stap 3 (features + AI-coach deep-dive) en Stap 4 (self-hosted +
         pricing + footer) worden hier ingevoegd. --}}

</body>
</html>
