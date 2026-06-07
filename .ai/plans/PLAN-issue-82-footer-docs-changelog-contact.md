# PLAN — Footer-links levend maken: Docs, Changelog, Contact (#82, fase 3 afronding)

- **Status:** APPROVED (ontwerp goedgekeurd door gebruiker, 2026-06-07)
- **Classificatie (Phase 0):** MEDIUM — kleine feature, multi-file, geen architectuur-impact
- **Issue:** #82 — Marketing landing, fase 3 (footer-placeholders waren bewust dood)
- **Branch / worktree:** `feature/marketing` (in place; EnterWorktree zou vers van `origin/main`
  vertakken en mist de fase-3 landing)

## Aanleiding

De footer van de marketing-landing (`welcome.blade.php`, regels 799–804) toont
`Docs`, `Changelog` en `Contact` als bewust dode `mk-footer-muted` spans omdat er
nog geen route/bestemming bestond. `GitHub` is al een werkende link. We maken de
drie placeholders levend.

## Beslissingen (met gebruiker afgestemd)

- **Docs** → `https://github.com/Marcvdc/AimTrack/tree/main/docs/user` (extern, nieuw tab).
- **Changelog** → `https://github.com/Marcvdc/AimTrack/releases` (extern, nieuw tab).
- **GitHub** → blijft; casing `marcvdc` → canoniek `Marcvdc` (3 links + terminal `git clone`).
- **Contact** → eigen `/contact`-pagina met een Livewire-formulier dat mailt naar
  `support@aimtrack.nl` (configureerbaar).
- E-mails vindbaar in de docs → `docs/user/README.md` met "Contact & support"-sectie.

## Acceptatiecriteria

1. De footer toont Docs/Changelog/Contact als echte links (geen `mk-footer-muted` meer);
   Docs/Changelog wijzen extern (`Marcvdc/AimTrack`), Contact naar `route('contact')`.
2. Alle GitHub-verwijzingen gebruiken de canonieke casing `Marcvdc`.
3. `GET /contact` rendert een pagina (200) in de Signal-Mint-stijl met het contactformulier.
4. Het contactformulier valideert (naam, e-mail, bericht; NL-foutmeldingen) en stuurt bij
   succes een mail naar `config('landing.contact_to')` met reply-to = de afzender.
5. Spambescherming: honeypot (stil laten vallen) + rate limit (3 verzendingen/min per IP).
6. `docs/user/README.md` bevat `support@aimtrack.nl` zodat het adres via de Docs-link
   vindbaar is.
7. Tests (Pest, TDD) dekken footer-links, `/contact`-render, validatie, happy-path-mail,
   honeypot en rate-limit; Pint schoon; ≥90% van gewijzigde code gedekt.

## Architectuur (Type A — Eloquent; KJ layer-rules)

- `routes/web.php`: `Route::get('/contact', ContactController::class)->name('contact')`.
- `App\Http\Controllers\ContactController` — invokable, retourneert `view('contact')`,
  geen business-logica (spiegelt `LandingPageController`).
- `resources/views/contact.blade.php` — standalone HTML-doc met `<x-aimtrack.head-assets />`
  + lean contact-specifieke `<style>` (design-tokens), host `<livewire:contact-form />`.
- `App\Livewire\ContactForm` — plain Livewire v4: velden naam/e-mail/bericht + honeypot;
  validatie + UI-state; delegeert verzenden aan de service (geen mail-logica in de component).
- `App\Services\Contact\ContactService::send(...)` — bouwt + verstuurt de Mailable.
- `App\Mail\ContactMessageMail` — markdown-view `resources/views/mail/contact-message.blade.php`;
  reply-to = afzender; `to` via `Mail::to(config('landing.contact_to'))` in de service.
- `config/landing.php`: nieuwe key `contact_to` (default `support@aimtrack.nl`, env
  `CONTACT_TO_ADDRESS`).

## Geraakte/nieuwe bestanden

| Bestand | Actie |
|---|---|
| `resources/views/welcome.blade.php` | footer-links + GitHub-casing |
| `config/landing.php` | `contact_to` toevoegen |
| `routes/web.php` | `/contact`-route |
| `app/Http/Controllers/ContactController.php` | nieuw (invokable) |
| `resources/views/contact.blade.php` | nieuw |
| `app/Livewire/ContactForm.php` | nieuw |
| `resources/views/livewire/contact-form.blade.php` | nieuw |
| `app/Services/Contact/ContactService.php` | nieuw |
| `app/Mail/ContactMessageMail.php` | nieuw |
| `resources/views/mail/contact-message.blade.php` | nieuw |
| `docs/user/README.md` | nieuw (docs-index + support-mail) |
| `tests/Feature/LandingPageTest.php` | footer-link-asserts uitbreiden |
| `tests/Feature/ContactPageTest.php` | nieuw |
| `tests/Feature/ContactFormTest.php` | nieuw (Livewire-component) |

## Tests

- `LandingPageTest`: footer toont Docs/Changelog/Contact als links met juiste hrefs +
  `Marcvdc`-casing; geen `mk-footer-muted` meer.
- `ContactPageTest`: `GET /contact` → 200; rendert de Livewire-component.
- `ContactFormTest` (`Livewire::test`): required/email-validatie; happy path (`Mail::fake`,
  assertSent naar `support@aimtrack.nl` met reply-to = afzender); honeypot gevuld → geen mail;
  rate-limit overschreden → geblokkeerd.

## Buiten scope

- Volledige nav/footer-extractie naar gedeelde Blade-componenten (contactpagina blijft
  self-contained).
- Fix van de `.env.local`-typfout `support@aimrack.nl` (lokale env van de gebruiker; gemeld).
- Een aparte top-nav "Contact"-link (niet gevraagd).
