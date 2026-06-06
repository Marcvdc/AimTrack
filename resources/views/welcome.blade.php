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
        .mk-callout-start {
            bottom: 8px; left: 50%; transform: translateX(-50%);
            text-align: center; white-space: nowrap;
            background: color-mix(in srgb, var(--at-accent) 8%, transparent);
            border: 1px solid color-mix(in srgb, var(--at-accent) 25%, transparent);
        }
        .mk-callout-start .mk-callout-label { color: var(--at-accent); }

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
        .mk-trust-logos .mk-club { color: var(--at-text); opacity: 1; font-weight: 600; }

        /* ── Section heading (gedeeld) ──────────────────────────────── */
        .mk-h2 {
            margin: 12px 0 0;
            font-family: var(--at-font-display);
            font-size: 44px;
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.1;
            color: var(--at-text);
            max-width: 720px;
        }

        /* ── Features ───────────────────────────────────────────────── */
        .mk-features { position: relative; padding: 88px 64px; }
        .mk-feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 44px;
        }
        .mk-feature-card {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 24px;
            border-radius: 14px;
            background: var(--at-panel);
            border: 1px solid var(--at-line);
        }
        .mk-feature-corner {
            position: absolute;
            top: -1px;
            right: -1px;
            width: 18px;
            height: 18px;
            border-top: 1.5px solid var(--at-accent);
            border-right: 1.5px solid var(--at-accent);
            pointer-events: none;
        }
        .mk-feature-head { display: flex; align-items: center; gap: 10px; }
        .mk-kicker {
            font-family: var(--at-font-mono);
            font-size: 10px;
            letter-spacing: 0.18em;
            color: var(--at-muted);
        }
        .mk-feature-title {
            margin: 0;
            font-family: var(--at-font-display);
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.015em;
            line-height: 1.2;
            color: var(--at-text);
        }
        .mk-feature-body { margin: 0; font-size: 14px; line-height: 1.55; color: var(--at-muted); }
        .mk-feature-demo { margin-top: auto; padding-top: 10px; }

        /* Demo · sessie-rijen (live data) */
        .mk-demo-rows { display: flex; flex-direction: column; gap: 6px; font-family: var(--at-font-mono); font-size: 11px; }
        .mk-demo-row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: var(--at-bg); border: 1px solid var(--at-line); border-radius: 6px; }
        .mk-demo-row-date { color: var(--at-muted); }
        .mk-demo-row-name { flex: 1; color: var(--at-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mk-demo-row-val { color: var(--at-accent); font-weight: 600; }
        .mk-demo-empty { padding: 10px 12px; background: var(--at-bg); border: 1px dashed var(--at-line); border-radius: 6px; font-size: 12px; color: var(--at-muted); }

        /* Demo · AI-reflectie (illustratief) */
        .mk-demo-ai { padding: 10px; background: color-mix(in srgb, var(--at-accent) 10%, transparent); border: 1px solid color-mix(in srgb, var(--at-accent) 33%, transparent); border-radius: 8px; font-size: 12px; color: var(--at-text); line-height: 1.5; }
        .mk-demo-ai-tag { font-family: var(--at-font-mono); font-size: 9px; color: var(--at-accent); letter-spacing: 0.14em; margin-bottom: 6px; }

        /* Demo · trends (live data) */
        .mk-demo-trend { padding: 10px; background: var(--at-bg); border: 1px solid var(--at-line); border-radius: 8px; }
        .mk-demo-trend-label { font-family: var(--at-font-mono); font-size: 9px; color: var(--at-muted); letter-spacing: 0.14em; margin-bottom: 4px; }

        /* Demo · WM-4 (illustratief, met T4 monogram-stempel) */
        .mk-demo-wm4 { position: relative; display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--at-bg); border: 1px solid var(--at-line); border-radius: 8px; }
        .mk-demo-wm4-body { flex: 1; }
        .mk-demo-wm4-title { font-size: 12px; font-weight: 600; color: var(--at-text); }
        .mk-demo-wm4-sub { font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.08em; }

        /* Demo · privacy (illustratief) */
        .mk-demo-privacy { display: flex; gap: 8px; }
        .mk-demo-privacy-card { flex: 1; padding: 10px 12px; background: var(--at-bg); border: 1px solid var(--at-line); border-radius: 8px; }
        .mk-demo-privacy-card.is-active { border-color: color-mix(in srgb, var(--at-accent) 40%, transparent); }
        .mk-demo-privacy-tag { font-family: var(--at-font-mono); font-size: 9px; letter-spacing: 0.14em; color: var(--at-muted); }
        .mk-demo-privacy-card.is-active .mk-demo-privacy-tag { color: var(--at-accent); }
        .mk-demo-privacy-name { font-size: 12px; font-weight: 600; margin-top: 4px; color: var(--at-text); }

        /* Demo · wapens (illustratief) */
        .mk-demo-weapons { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .mk-demo-weapon { padding: 8px 10px; background: var(--at-bg); border: 1px solid var(--at-line); border-radius: 6px; font-size: 11px; }
        .mk-demo-weapon-id { font-family: var(--at-font-mono); font-size: 9px; color: var(--at-muted); letter-spacing: 0.12em; }
        .mk-demo-weapon-name { color: var(--at-text); font-weight: 600; margin-top: 2px; }

        /* ── AI-coach deep-dive ─────────────────────────────────────── */
        .mk-ai { position: relative; padding: 96px 64px; border-top: 1px solid var(--at-line); }
        .mk-ai-grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: 64px; align-items: center; }
        .mk-ai .mk-h2 { margin-bottom: 16px; }
        .mk-ai-lead { margin: 0; max-width: 480px; font-size: 16px; line-height: 1.6; color: var(--at-muted); }
        .mk-checklist { display: flex; flex-direction: column; gap: 10px; margin-top: 28px; }
        .mk-check-item { display: flex; align-items: flex-start; gap: 10px; }
        .mk-check-badge {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--at-accent) 12%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 20px;
            margin-top: 1px;
        }
        .mk-check-text { font-size: 14px; color: var(--at-text); }

        /* Mock-chat (T3 bracket-frame) */
        .mk-chat { display: flex; flex-direction: column; gap: 14px; overflow: hidden; }
        .mk-chat-head { display: flex; align-items: center; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid var(--at-line); }
        .mk-chat-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--at-accent); flex: 0 0 auto; }
        .mk-chat-tag { font-family: var(--at-font-mono); font-size: 10px; letter-spacing: 0.14em; color: var(--at-muted); }
        .mk-chat-meta { margin-left: auto; font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); }
        .mk-bubble { font-size: 13px; line-height: 1.55; color: var(--at-text); border-radius: 12px; }
        .mk-bubble-user { align-self: flex-end; max-width: 85%; padding: 10px 14px; background: color-mix(in srgb, var(--at-accent) 12%, transparent); border: 1px solid color-mix(in srgb, var(--at-accent) 33%, transparent); }
        .mk-bubble-ai { align-self: flex-start; max-width: 90%; padding: 12px 14px; background: var(--at-bg); border: 1px solid var(--at-line); }
        .mk-bubble-accent { color: var(--at-accent); font-family: var(--at-font-mono); }
        .mk-drift { align-self: flex-start; width: 90%; padding: 12px; background: var(--at-bg); border: 1px solid var(--at-line); border-radius: 12px; }
        .mk-drift-label { font-family: var(--at-font-mono); font-size: 9px; color: var(--at-muted); letter-spacing: 0.14em; margin-bottom: 6px; }

        /* ── Self-hosted strook ─────────────────────────────────────── */
        .mk-selfhost {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
            padding: 64px;
            border-top: 1px solid var(--at-line);
            border-bottom: 1px solid var(--at-line);
            background: var(--at-bg);
        }
        .mk-selfhost .mk-h2 { font-size: 38px; margin: 10px 0 14px; }
        .mk-selfhost-lead { margin: 0; max-width: 480px; font-size: 15px; line-height: 1.6; color: var(--at-muted); }
        .mk-selfhost-pills { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }

        .mk-terminal {
            padding: 20px;
            background: var(--at-panel);
            border: 1px solid var(--at-line);
            border-radius: 12px;
            font-family: var(--at-font-mono);
            font-size: 13px;
            color: var(--at-text);
        }
        .mk-terminal-bar { display: flex; align-items: center; gap: 6px; margin-bottom: 14px; }
        .mk-terminal-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; }
        .mk-terminal-dot.is-warn { background: color-mix(in srgb, var(--at-warn) 53%, transparent); }
        .mk-terminal-dot.is-accent { background: color-mix(in srgb, var(--at-accent) 53%, transparent); }
        .mk-terminal-dot.is-muted { background: color-mix(in srgb, var(--at-muted) 33%, transparent); }
        .mk-terminal-path { margin-left: auto; font-size: 10px; color: var(--at-muted); letter-spacing: 0.12em; }
        .mk-terminal-line { color: var(--at-muted); word-break: break-all; }
        .mk-terminal-line .mk-prompt { color: var(--at-accent); }
        .mk-terminal-ok { color: var(--at-muted); margin-top: 6px; font-size: 11px; }
        .mk-terminal-ok .mk-accent { color: var(--at-accent); }

        /* ── Footer ─────────────────────────────────────────────────── */
        .mk-footer {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            padding: 40px 64px;
            border-top: 1px solid var(--at-line);
        }
        .mk-footer-meta { font-family: var(--at-font-mono); font-size: 11px; color: var(--at-muted); letter-spacing: 0.08em; }
        .mk-footer-links { margin-left: auto; display: flex; flex-wrap: wrap; gap: 22px; font-size: 12px; }
        .mk-footer-link { color: var(--at-muted); text-decoration: none; transition: color .15s ease; }
        .mk-footer-link:hover { color: var(--at-text); }
        .mk-footer-muted { color: var(--at-muted); }

        /* ── Responsive ─────────────────────────────────────────────── */
        @media (max-width: 1024px) {
            .mk-hero { grid-template-columns: 1fr; padding: 64px 32px 48px; }
            .mk-nav, .mk-trust, .mk-features, .mk-ai, .mk-selfhost, .mk-footer { padding-left: 32px; padding-right: 32px; }
            .mk-feature-grid { grid-template-columns: repeat(2, 1fr); }
            .mk-ai-grid { grid-template-columns: 1fr; gap: 40px; }
            .mk-selfhost { grid-template-columns: 1fr; gap: 32px; }
        }
        @media (max-width: 768px) {
            .mk-nav-links { display: none; }
            .mk-h1 { font-size: 44px; }
            .mk-h2 { font-size: 34px; }
            .mk-lead { font-size: 16px; }
            .mk-trust { gap: 20px; }
            .mk-trust-logos { gap: 22px; font-size: 14px; }
            .mk-reticle-deco { right: -40px; opacity: .6; }
            .mk-features, .mk-ai { padding-top: 56px; padding-bottom: 56px; }
            .mk-feature-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
            .mk-nav { padding: 14px 18px; gap: 12px; }
            .mk-hero { padding: 44px 18px 36px; }
            .mk-hero-stage { transform: scale(.74); margin: -55px 0; }
            .mk-cta-row .mk-btn { flex: 1 1 auto; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after { transition-duration: .001ms !important; animation-duration: .001ms !important; }
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
            <a class="mk-nav-link" href="https://github.com/marcvdc/AimTrack" target="_blank" rel="noopener noreferrer">GitHub</a>
        </div>
        <div class="mk-nav-actions">
            <a class="mk-login-link" href="/admin/login">Inloggen</a>
            <a class="mk-btn-nav" href="/admin/login">Aan de slag</a>
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
                    Gratis aan de slag
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
                    @php
                        // Voorbeeld-groepering voor de hero (prototype RING_HITS uit shared.jsx).
                        $heroHits = [
                            ['x' => 0.05, 'y' => -0.06, 'r' => 10], ['x' => -0.10, 'y' => 0.04, 'r' => 9.7],
                            ['x' => -0.02, 'y' => 0.12, 'r' => 9.5], ['x' => 0.18, 'y' => -0.04, 'r' => 9.0],
                            ['x' => 0.08, 'y' => 0.02, 'r' => 9.9], ['x' => -0.06, 'y' => -0.14, 'r' => 9.3],
                            ['x' => 0.22, 'y' => 0.10, 'r' => 8.6], ['x' => -0.18, 'y' => 0.06, 'r' => 8.9],
                            ['x' => 0.04, 'y' => -0.08, 'r' => 9.8], ['x' => 0.12, 'y' => 0.14, 'r' => 9.1],
                        ];
                    @endphp
                    <x-aimtrack.target-rings :size="220" accent="var(--at-accent)" dim="var(--at-text)" :ring-stroke="1" :hits="$heroHits" />
                </div>

                @php
                    // Echte instance-aggregaten (zie LandingPageController + LandingPageData).
                    $hasData = (bool) data_get($stats ?? [], 'has_data', false);
                    $totalSessions = (int) data_get($stats ?? [], 'total_sessions', 0);
                    $totalRounds = (int) data_get($stats ?? [], 'total_rounds', 0);
                    $aiReflections = (int) data_get($stats ?? [], 'ai_reflections', 0);
                    $fmtNum = static fn (int $n): string => number_format($n, 0, ',', '.');
                @endphp
                @if ($hasData)
                    <div class="mk-callout mk-callout-score">
                        <div class="mk-callout-label">SESSIES</div>
                        <div class="mk-callout-value">{{ $fmtNum($totalSessions) }}</div>
                    </div>
                    <div class="mk-callout mk-callout-group">
                        <div class="mk-callout-label">SCHOTEN</div>
                        <div class="mk-callout-value">{{ $fmtNum($totalRounds) }}</div>
                    </div>
                    <div class="mk-callout mk-callout-ai">
                        <div class="mk-callout-label">● AI-REFLECTIES</div>
                        <div class="mk-callout-value">{{ $fmtNum($aiReflections) }}</div>
                    </div>
                @else
                    {{-- Verse instance: nodig uit i.p.v. nullen of neppe cijfers tonen. --}}
                    <div class="mk-callout mk-callout-start">
                        <div class="mk-callout-label">● START</div>
                        <div class="mk-callout-text">Begin je eerste sessie —<br>je cijfers verschijnen hier.</div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ── Trust strip ──────────────────────────────────────────────── --}}
    <section class="mk-trust">
        <div class="mk-trust-label">Gebruikt door sportschutters bij</div>
        <div class="mk-trust-logos">
            <span class="mk-club">{{ $club ?? config('landing.club') }}</span>
            @foreach (($partnerClubs ?? []) as $partnerClub)
                <span>{{ $partnerClub }}</span>
            @endforeach
        </div>
    </section>

    {{-- ── Features ─────────────────────────────────────────────────── --}}
    <section class="mk-features" id="features">
        <div class="mk-section-label"><span class="mk-dot" aria-hidden="true"></span> Wat je krijgt</div>
        <h2 class="mk-h2">Een logboek dat <span class="mk-accent">meedenkt</span>, niet alleen onthoudt.</h2>

        <div class="mk-feature-grid">
            {{-- 01 · Sessies (live data) --}}
            <article class="mk-feature-card">
                <span class="mk-feature-corner" aria-hidden="true"></span>
                <div class="mk-feature-head">
                    <x-aimtrack.icon name="session" :size="18" color="var(--at-accent)" />
                    <div class="mk-kicker">01 · SESSIES</div>
                </div>
                <h3 class="mk-feature-title">Log één sessie in 30 seconden</h3>
                <p class="mk-feature-body">Discipline, wapen, baan en score in een paar tikken. Notities en foto's desgewenst. Daarna gaat AimTrack ermee aan de slag.</p>
                <div class="mk-feature-demo">
                    <div class="mk-demo-rows">
                        @forelse (($recentSessions ?? []) as $recentSession)
                            <div class="mk-demo-row">
                                <span class="mk-demo-row-date">{{ $recentSession['date'] }}</span>
                                <span class="mk-demo-row-name">{{ $recentSession['range'] }}</span>
                                <span class="mk-demo-row-val">{{ number_format((int) $recentSession['rounds'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="mk-demo-empty">Nog geen sessies — jouw logboek begint hier.</div>
                        @endforelse
                    </div>
                </div>
            </article>

            {{-- 02 · AI-coach (illustratief) --}}
            <article class="mk-feature-card">
                <span class="mk-feature-corner" aria-hidden="true"></span>
                <div class="mk-feature-head">
                    <x-aimtrack.icon name="ai" :size="18" color="var(--at-accent)" />
                    <div class="mk-kicker">02 · AI-COACH</div>
                </div>
                <h3 class="mk-feature-title">Reflectie per sessie, zonder typen</h3>
                <p class="mk-feature-body">Sterke punten, verbeterpunten, en concrete oefenadviezen. Vraag follow-ups in normaal Nederlands.</p>
                <div class="mk-feature-demo">
                    <div class="mk-demo-ai">
                        <div class="mk-demo-ai-tag">● AI · VOORBEELD</div>
                        "Vanaf schot 35 zakt het gemiddelde 0.4 — concentratiedipje rond minuut 22."
                    </div>
                </div>
            </article>

            {{-- 03 · Trends (live data: rounds_fired-tijdreeks) --}}
            <article class="mk-feature-card">
                <span class="mk-feature-corner" aria-hidden="true"></span>
                <div class="mk-feature-head">
                    <x-aimtrack.icon name="spark" :size="18" color="var(--at-accent)" />
                    <div class="mk-kicker">03 · TRENDS</div>
                </div>
                <h3 class="mk-feature-title">Zie progressie per wapen, niet per gevoel</h3>
                <p class="mk-feature-body">Score-trend, groepering, schot-voor-schot — voor elk wapen apart. Vergelijk maanden of disciplines naast elkaar.</p>
                <div class="mk-feature-demo">
                    <div class="mk-demo-trend">
                        <div class="mk-demo-trend-label">SCHOTEN · PER SESSIE</div>
                        <x-aimtrack.sparkline :data="$trendSeries ?? []" :width="250" :height="50" color="var(--at-accent)" :stroke-width="1.8" :fill="true" />
                    </div>
                </div>
            </article>

            {{-- 04 · WM-4 (illustratief, T4 monogram-stempel) --}}
            <article class="mk-feature-card">
                <span class="mk-feature-corner" aria-hidden="true"></span>
                <div class="mk-feature-head">
                    <x-aimtrack.icon name="export" :size="18" color="var(--at-accent)" />
                    <div class="mk-kicker">04 · WM-4</div>
                </div>
                <h3 class="mk-feature-title">Wet-conforme administratie</h3>
                <p class="mk-feature-body">Genereer een WM-4 register-export voor je vereniging. Compleet, gefilterd, en klaar voor inlevering.</p>
                <div class="mk-feature-demo">
                    <div class="mk-demo-wm4">
                        <x-aimtrack.monogram-stamp label="WM-4 OK" corner="top-right" />
                        <x-aimtrack.at-mark :size="20" color="var(--at-accent)" />
                        <div class="mk-demo-wm4-body">
                            <div class="mk-demo-wm4-title">WM-4 · register</div>
                            <div class="mk-demo-wm4-sub">EXPORT · GEREED</div>
                        </div>
                        <x-aimtrack.icon name="export" :size="16" color="var(--at-accent)" />
                    </div>
                </div>
            </article>

            {{-- 05 · Privacy (illustratief) --}}
            <article class="mk-feature-card">
                <span class="mk-feature-corner" aria-hidden="true"></span>
                <div class="mk-feature-head">
                    <x-aimtrack.icon name="shield" :size="18" color="var(--at-accent)" />
                    <div class="mk-kicker">05 · PRIVACY</div>
                </div>
                <h3 class="mk-feature-title">Self-hosted of bij ons — jij kiest</h3>
                <p class="mk-feature-body">Draai AimTrack op je eigen server (Docker, 5 min setup) of gebruik onze NL-cloud. Je data is van jou, altijd.</p>
                <div class="mk-feature-demo">
                    <div class="mk-demo-privacy">
                        <div class="mk-demo-privacy-card is-active">
                            <div class="mk-demo-privacy-tag">● ACTIEF</div>
                            <div class="mk-demo-privacy-name">Self-hosted</div>
                        </div>
                        <div class="mk-demo-privacy-card">
                            <div class="mk-demo-privacy-tag">OFF</div>
                            <div class="mk-demo-privacy-name">NL-cloud</div>
                        </div>
                    </div>
                </div>
            </article>

            {{-- 06 · Wapens (illustratief) --}}
            <article class="mk-feature-card">
                <span class="mk-feature-corner" aria-hidden="true"></span>
                <div class="mk-feature-head">
                    <x-aimtrack.icon name="weapon" :size="18" color="var(--at-accent)" />
                    <div class="mk-kicker">06 · WAPENS</div>
                </div>
                <h3 class="mk-feature-title">Eén overzicht per wapen</h3>
                <p class="mk-feature-body">Schotaantal, onderhoud, kalibratie, gem. score. Alles wat je nodig hebt voor de keuringsbrief en je eigen ritueel.</p>
                <div class="mk-feature-demo">
                    <div class="mk-demo-weapons">
                        @foreach (['Walther LP500', 'CZ Shadow 2', 'Pardini K22'] as $i => $weaponName)
                            <div class="mk-demo-weapon">
                                <div class="mk-demo-weapon-id">W-00{{ $i + 1 }}</div>
                                <div class="mk-demo-weapon-name">{{ $weaponName }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- ── AI-coach deep-dive ───────────────────────────────────────── --}}
    <section class="mk-ai" id="ai-coach">
        <div class="mk-ai-grid">
            <div class="mk-ai-copy">
                <div class="mk-section-label"><span class="mk-dot" aria-hidden="true"></span> AI-coach</div>
                <h2 class="mk-h2">Een coach die <br><span class="mk-accent">jouw cijfers</span> leest.</h2>
                <p class="mk-ai-lead">
                    AimTrack analyseert je sessies in context: welke serie zakt, hoe je
                    ademritme zich verhoudt tot je groepering, wat goed werkt met welk
                    wapen. Stel vragen, krijg antwoorden onderbouwd met je eigen data.
                </p>
                <div class="mk-checklist">
                    @foreach ([
                        'Per-sessie reflectie zonder dat je iets hoeft te typen',
                        'Trainingsdoelen automatisch voorgesteld, jij kiest',
                        'Vergelijk wapens, disciplines, of periodes naast elkaar',
                        'Alles draait lokaal — je data verlaat de server niet',
                    ] as $checkItem)
                        <div class="mk-check-item">
                            <span class="mk-check-badge" aria-hidden="true">
                                <x-aimtrack.icon name="check" :size="12" color="var(--at-accent)" :stroke="2.2" />
                            </span>
                            <span class="mk-check-text">{{ $checkItem }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Mock-chat in een T3 bracket-frame --}}
            <x-aimtrack.bracket-frame class="mk-chat" :corner-size="18" :rounded="16" :padding="24">
                <div class="mk-chat-head">
                    <span class="mk-chat-dot" aria-hidden="true"></span>
                    <span class="mk-chat-tag">AI-COACH · VOORBEELD</span>
                    <span class="mk-chat-meta">LP500</span>
                </div>
                <div class="mk-bubble mk-bubble-user">Waarom zakt mijn score rond schot 35?</div>
                <div class="mk-bubble mk-bubble-ai">
                    In je laatste 6 LP10-sessies daalt het gemiddelde tussen schot 30–40 met
                    <span class="mk-bubble-accent">0.3–0.5</span>. Het tijdstip valt rond minuut 20 van je sessie — een typisch concentratiedipje.
                </div>
                <div class="mk-drift">
                    <div class="mk-drift-label">SCORE-DRIFT · SCHOT 30–40</div>
                    <x-aimtrack.sparkline :data="[9.6, 9.5, 9.4, 9.2, 9.0, 8.9, 9.0, 9.1, 9.3, 9.4, 9.4]" :width="380" :height="50" color="var(--at-warn)" :stroke-width="1.8" />
                </div>
                <div class="mk-bubble mk-bubble-user">Wat raad je aan?</div>
                <div class="mk-bubble mk-bubble-ai">
                    Micro-pauze van 30s na schot 30, cadans rond 30s aanhouden, en 2× 10 min droogoefenen per week. Zal ik dit als doel voor deze maand toevoegen?
                </div>
            </x-aimtrack.bracket-frame>
        </div>
    </section>

    {{-- ── Self-hosted strook (vervangt de pricing-sectie — app is gratis) ─ --}}
    <section class="mk-selfhost" id="self-hosted">
        <div class="mk-selfhost-copy">
            <div class="mk-section-label"><span class="mk-dot" aria-hidden="true"></span> Self-hosted · open-source</div>
            <h2 class="mk-h2">Eén command, eigen instance.</h2>
            <p class="mk-selfhost-lead">
                Docker compose up. Klaar. AimTrack is open-source onder MIT-licentie,
                werkt op een Raspberry Pi, en synchroniseert nergens heen. Gratis,
                voor altijd — geen abonnement, geen addertjes.
            </p>
            <div class="mk-selfhost-pills">
                <span class="mk-pill">● Gratis · voor altijd</span>
                <span class="mk-pill">● Open-source</span>
                <span class="mk-pill">● MIT-licentie</span>
            </div>
        </div>
        <div class="mk-terminal">
            <div class="mk-terminal-bar">
                <span class="mk-terminal-dot is-warn" aria-hidden="true"></span>
                <span class="mk-terminal-dot is-accent" aria-hidden="true"></span>
                <span class="mk-terminal-dot is-muted" aria-hidden="true"></span>
                <span class="mk-terminal-path">~/aimtrack</span>
            </div>
            <div class="mk-terminal-line"><span class="mk-prompt">$</span> git clone github.com/marcvdc/AimTrack</div>
            <div class="mk-terminal-line"><span class="mk-prompt">$</span> cd AimTrack && docker compose up -d</div>
            <div class="mk-terminal-ok">✓ AimTrack draait op <span class="mk-accent">http://localhost:8000</span></div>
            <div class="mk-terminal-ok">✓ Klaar voor je eerste sessie.</div>
        </div>
    </section>

    {{-- ── Footer ───────────────────────────────────────────────────── --}}
    <footer class="mk-footer">
        <x-aimtrack.wordmark :size="22" color="var(--at-muted)" />
        <div class="mk-footer-meta">MIT · open-source · NL</div>
        <div class="mk-footer-links">
            {{-- Docs/Changelog/Contact bestaan nog niet als route → bewust geen
                 href="#"-link (navigatieval), maar muted placeholders. --}}
            <span class="mk-footer-muted">Docs</span>
            <span class="mk-footer-muted">Changelog</span>
            <a class="mk-footer-link" href="https://github.com/marcvdc/AimTrack" target="_blank" rel="noopener noreferrer">GitHub</a>
            <span class="mk-footer-muted">Contact</span>
        </div>
    </footer>

</body>
</html>
