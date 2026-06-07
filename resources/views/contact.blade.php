<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact — AimTrack</title>
    <meta name="description" content="Neem contact op met AimTrack: stel je vraag via het formulier of mail rechtstreeks naar {{ config('landing.contact_to') }}.">

    {{-- Zelfde design-tokens + webfonts als de landingspagina (gedeelde bron). --}}
    <x-aimtrack.head-assets />
    @livewireStyles

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        .mkc-body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--at-bg);
            color: var(--at-text);
            font-family: var(--at-font-body);
            font-size: 15px;
            line-height: 1.6;
        }

        .mkc-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--at-space-5) var(--at-space-6);
            border-bottom: 1px solid var(--at-line);
        }

        .mkc-back {
            display: inline-flex;
            align-items: center;
            gap: var(--at-space-2);
            color: var(--at-muted);
            text-decoration: none;
            font-size: 13px;
        }

        .mkc-back:hover { color: var(--at-accent); }

        .mkc-main {
            flex: 1;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            padding: var(--at-space-7) var(--at-space-5);
        }

        .mkc-label-eyebrow {
            font-family: var(--at-font-mono);
            font-size: var(--at-label);
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--at-accent);
        }

        .mkc-h1 {
            font-family: var(--at-font-display);
            font-size: var(--at-display-md);
            font-weight: 700;
            margin: var(--at-space-2) 0 var(--at-space-3);
        }

        .mkc-intro { color: var(--at-muted); margin: 0 0 var(--at-space-6); }

        .mkc-intro a { color: var(--at-accent); text-decoration: none; }
        .mkc-intro a:hover { text-decoration: underline; }

        .mkc-form-wrap {
            background: var(--at-panel);
            border: 1px solid var(--at-line);
            border-radius: var(--at-r-xl);
            padding: var(--at-space-6);
        }

        .mkc-form { display: flex; flex-direction: column; gap: var(--at-space-4); }

        .mkc-field { display: flex; flex-direction: column; gap: var(--at-space-2); }

        .mkc-label {
            font-family: var(--at-font-mono);
            font-size: var(--at-text-small);
            letter-spacing: 0.06em;
            color: var(--at-muted);
        }

        .mkc-input {
            width: 100%;
            background: var(--at-bg);
            border: 1px solid var(--at-line);
            border-radius: var(--at-r-md);
            color: var(--at-text);
            font-family: var(--at-font-body);
            font-size: 15px;
            padding: var(--at-space-3);
        }

        .mkc-input:focus {
            outline: none;
            border-color: var(--at-accent);
            box-shadow: 0 0 0 3px var(--at-accent-25);
        }

        .mkc-textarea { resize: vertical; min-height: 140px; }

        .mkc-error { color: var(--at-warn); font-size: var(--at-text-small); }

        .mkc-alert {
            background: rgba(248, 113, 113, 0.12);
            border: 1px solid var(--at-warn);
            border-radius: var(--at-r-md);
            color: var(--at-text);
            padding: var(--at-space-3);
            margin: 0;
            font-size: 14px;
        }

        .mkc-btn {
            align-self: flex-start;
            background: var(--at-accent);
            color: var(--at-cta-text);
            border: none;
            border-radius: var(--at-r-pill);
            font-family: var(--at-font-display);
            font-weight: 600;
            font-size: 15px;
            padding: var(--at-space-3) var(--at-space-5);
            cursor: pointer;
        }

        .mkc-btn:hover { filter: brightness(1.05); }
        .mkc-btn:disabled { opacity: 0.6; cursor: progress; }

        .mkc-btn-ghost {
            background: transparent;
            color: var(--at-accent);
            border: 1px solid var(--at-line);
            border-radius: var(--at-r-pill);
            padding: var(--at-space-2) var(--at-space-4);
            cursor: pointer;
            font-family: var(--at-font-display);
            font-size: 14px;
            margin-top: var(--at-space-3);
        }

        .mkc-success h2 {
            font-family: var(--at-font-display);
            color: var(--at-accent);
            margin: 0 0 var(--at-space-2);
        }

        .mkc-success p { color: var(--at-muted); margin: 0; }

        /* Honeypot — visueel verborgen, maar niet display:none zodat bots 'm vinden. */
        .mkc-hp {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .mkc-footer {
            padding: var(--at-space-5) var(--at-space-6);
            border-top: 1px solid var(--at-line);
            color: var(--at-muted);
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body class="mkc-body">
    <nav class="mkc-nav">
        <a class="mkc-back" href="{{ route('welcome') }}">
            <span aria-hidden="true">←</span> Terug naar home
        </a>
        <a href="{{ route('welcome') }}" aria-label="AimTrack — naar de homepage">
            <x-aimtrack.wordmark :size="22" />
        </a>
    </nav>

    <main class="mkc-main">
        <div class="mkc-label-eyebrow"><span aria-hidden="true">●</span> Contact</div>
        <h1 class="mkc-h1">Vragen of feedback?</h1>
        <p class="mkc-intro">
            Stuur een bericht via het formulier hieronder, of mail rechtstreeks naar
            <a href="mailto:{{ config('landing.contact_to') }}">{{ config('landing.contact_to') }}</a>. We reageren zo snel mogelijk.
        </p>

        <livewire:contact-form />
    </main>

    <footer class="mkc-footer">
        AimTrack · MIT · open-source · NL
    </footer>

    @livewireScripts
</body>
</html>
