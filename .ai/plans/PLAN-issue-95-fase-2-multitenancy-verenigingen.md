# PLAN — Issue #95 Fase 2: Multi-tenancy verenigingen met coach-inzage

- **Status:** APPROVED (human sign-off 2026-06-13, defaults voor open vragen)
- **Epic:** [#95](https://github.com/Marcvdc/AimTrack/issues/95) — Fase 1 (#96) gemerged
- **Worktree:** `aimtrack-ai-verenigingen` (`feature/ai-verenigingen`, basis `87ad068` main)
- **Web:** http://localhost:19084 · DB-poort 15436 · Mailpit 19029
- **Classificatie:** COMPLEX (multi-file, nieuwe feature, >3 AC's, autorisatie-impact)

---

## Doel

Meerdere schietverenigingen ondersteunen. Leden houden eigendom van hun data
(`user_id`-owned blijft ongewijzigd). Coaches/admins binnen dezelfde vereniging
krijgen **read-only** inzage in sessies & voortgang van hun leden. Een gedeelde
verenigings-Claude-key fungeert als fallback onder de user-key:
`user-key → vereniging-key → geen AI`.

## Kernbesluit (uit epic, leidend bij conflict)

- **GÉÉN schema-brede `tenant_id`/`association_id` op `sessions`/`weapons`.**
  Domeindata blijft `user_id`-owned. Coach-inzage = aparte read-only screens die
  scopen op *welke users* tot dezelfde vereniging horen (via pivot), niet op een
  kolom op de domeindata. Dit voorkomt cross-user-lekken bij het oprekken van de
  bestaande `->where('user_id', auth()->id())`.
- Home-grown rollen (pivot-enum + policies/gates), geen spatie/permission.
- v1: één **actieve** vereniging per user (pivot laat later meerdere toe).

---

## Sub-fasering (binnen één PR, gecommit per sub-fase)

### (a) Verenigingen + membership + rollen + key-resolutie

**Datamodel / migraties**
- `create_verenigingen_table`: `id`, `naam`, `slug` (unique), `anthropic_api_key`
  (`text` nullable, `encrypted` cast), `ai_key_verified_at` (timestamp nullable),
  `settings` (`json` nullable), `created_by` (FK users, nullOnDelete), timestamps.
- `create_vereniging_user_table` (pivot): `vereniging_id` (FK cascade),
  `user_id` (FK cascade), `role` (string/enum: `member`/`coach`/`admin`),
  `joined_at` (timestamp nullable), timestamps. Unique op
  `(vereniging_id, user_id)`.
- `add_active_vereniging_id_to_users_table`: `active_vereniging_id`
  (FK verenigingen, nullOnDelete, nullable) — de v1 "één actieve vereniging".

**Models**
- `app/Models/Vereniging.php`: `naam`, `slug`, `anthropic_api_key`,
  `ai_key_verified_at`, `settings`, `created_by` fillable; casts
  `anthropic_api_key => encrypted`, `ai_key_verified_at => datetime`,
  `settings => array`. `$hidden` = `['anthropic_api_key']`. Relaties:
  `members()` belongsToMany(User) `->withPivot('role','joined_at')`,
  `creator()` belongsTo(User, 'created_by'). Helpers: `coaches()`, `admins()`
  scoped op pivot-rol.
- `app/Enums/VerenigingRol.php`: backed enum `Member`/`Coach`/`Admin` (TitleCase),
  met `label(): string` (NL) en helper `canCoach()` (coach|admin),
  `canManage()` (admin).
- `User.php`: relaties `verenigingen()` belongsToMany `->withPivot('role',...)`,
  `activeVereniging()` belongsTo(Vereniging, 'active_vereniging_id'). Helpers:
  `rolInVereniging(Vereniging): ?VerenigingRol`, `isCoachVan(User $lid): bool`
  (zelfde actieve vereniging + rol coach/admin).

**Key-resolutie** (`app/Services/Ai/AiKeyResolver.php`)
- `forVereniging(?Vereniging): ?string` — geeft `anthropic_api_key` of null.
- `forUser(?User)` uitbreiden naar: `user-key ?? vereniging-key (active) ?? null`.
- `ShooterCoach` (jobs) blijft `forUser($session->user)` aanroepen — de fallback
  zit nu ín de resolver, dus jobs hoeven niet te wijzigen. Key wordt nog steeds
  runtime in `handle()` geresolved; nooit in payload. ✅ AC.
- Factories: `VerenigingFactory`, pivot-states (`asCoach`, `asAdmin`).

### (b) Coach-inzage-schermen (read-only)

- **Aparte, read-only Filament resource(s)** — NIET de bestaande
  `SessionResource`/`WeaponResource` oprekken. Bijv.
  `app/Filament/Resources/Coaching/LidSessieResource.php` (read-only:
  `canCreate/canEdit/canDelete = false`), zichtbaar via `canViewAny()` =
  `auth()->user()` heeft coach/admin-rol in actieve vereniging.
- `getEloquentQuery()`: `Session::whereIn('user_id', <member-ids van zelfde
  actieve vereniging, excl. niet-coach>)`. Member-ids via
  `auth()->user()->activeVereniging?->members()->pluck('users.id')`.
- Idem read-only voortgang/wapen-inzicht voor leden binnen de vereniging.
- **Autorisatie**: `app/Policies/SessionPolicy.php` +
  `app/Policies/WeaponPolicy.php` (geregistreerd in `AuthServiceProvider`):
  `view()` = eigenaar **of** coach/admin in dezelfde actieve vereniging als de
  eigenaar; `update()/delete()/create()` = uitsluitend eigenaar. Coach krijgt
  nooit write. Cross-vereniging altijd `false`.
- Bestaande schutter-schermen blijven volledig ongewijzigd.

### (c) Ledenbeheer-UI (admin)

- `VerenigingResource` (of pagina) zichtbaar voor admins van hun eigen
  vereniging: naam/instellingen + gedeelde key beheren (zelfde masking/test-actie
  als `AiSettingsPage`: gemaskeerd `sk-ant-…1234`, test-call naar Anthropic
  `/v1/models`, wissen).
- Ledenbeheer: leden toevoegen/uitnodigen op e-mail, rol toewijzen/wijzigen,
  verwijderen. Uitnodiging v1: bestaande user op e-mail koppelen; niet-bestaande
  e-mail → nette melding (volwaardige invite-flow buiten scope, optioneel later).
- Guard: admin kan alleen leden van de **eigen** vereniging beheren; laatste
  admin niet verwijderbaar/degradeerbaar.

---

## Geraakte / nieuwe bestanden

| Laag | Bestand | Actie |
|---|---|---|
| Migratie | `database/migrations/*_create_verenigingen_table.php` | nieuw |
| Migratie | `*_create_vereniging_user_table.php` | nieuw |
| Migratie | `*_add_active_vereniging_id_to_users_table.php` | nieuw |
| Model | `app/Models/Vereniging.php` | nieuw |
| Model | `app/Models/User.php` | wijzig (relaties/helpers) |
| Enum | `app/Enums/VerenigingRol.php` | nieuw |
| Service | `app/Services/Ai/AiKeyResolver.php` | wijzig (fallback) |
| Policy | `app/Policies/SessionPolicy.php`, `WeaponPolicy.php` | nieuw |
| Provider | `app/Providers/AuthServiceProvider.php` | wijzig (register) |
| Filament | `app/Filament/Resources/Coaching/...` (read-only) | nieuw |
| Filament | `VerenigingResource` + ledenbeheer | nieuw |
| Factory | `database/factories/VerenigingFactory.php` | nieuw |
| Seeder | demo-vereniging in bestaande seeder | wijzig (optioneel) |

## Architectuur-compliance (STOP-condities)

- Type A (Eloquent). Filament-resources zonder inline create/update business
  logica — ledenbeheer/key-beheer via een `VerenigingService`
  (`app/Services/Vereniging/`) voor toevoegen/rol-wijzigen/key-test, niet inline
  in de resource (cond. #12). Service blijft <500 regels / <10 methods (#13).
- Geen secrets in config/env hardcoded; key encrypted + gemaskeerd (#7).
- Alle nieuwe code getest, Pint-clean (#5, #6).

---

## Tests (Pest, ≥90% van gewijzigde code)

**Unit**
- `AiKeyResolverTest` uitbreiden: user-key wint van vereniging-key; geen user-key
  → vereniging-key; geen van beide → null; vereniging zonder key → null.
- `VerenigingRol` enum: `canCoach()`/`canManage()` per rol.
- `User::rolInVereniging` / `isCoachVan` happy + cross-vereniging false.

**Feature**
- Membership: user lid maken met rol; pivot unique.
- Policy: eigenaar ziet eigen sessie; coach ziet sessie van lid zelfde
  vereniging (read-only); coach ziet GEEN sessie van andere vereniging;
  member ziet GEEN peer-data; coach kan niet editen/deleten.
- Filament coach-resource: coach ziet leden-records, member niet; smoke
  `assertCanSeeTableRecords` / `assertCanNotSeeTableRecords`.
- Ledenbeheer: admin voegt lid toe / wijzigt rol; niet-admin geblokkeerd;
  cross-vereniging beheer geblokkeerd; laatste admin beschermd.
- Verenigings-key: opslaan encrypted + gemaskeerd; test-actie (Anthropic
  `Http::fake()`); wissen.
- Key-precedentie e2e: sessie-reflectie-job van lid zónder eigen key gebruikt
  vereniging-key (ShooterCoach `Http::fake()`).

---

## Acceptatiecriteria (uit epic)

- [ ] Vereniging aanmaakbaar met naam en (optioneel) eigen Claude-key.
- [ ] Gebruikers worden lid met een rol (member/coach/admin).
- [ ] Coach ziet read-only sessies & voortgang van leden binnen dezelfde vereniging.
- [ ] Coach ziet géén data van andere verenigingen; member ziet géén peer-data.
- [ ] Key-resolutie volgt `user-key → vereniging-key → geen AI`.
- [ ] Club-admin beheert leden (toevoegen/uitnodigen, rollen) en de gedeelde key.
- [ ] Pest dekt membership/rollen, coach-inzage-grenzen, cross-tenant-isolatie,
      key-precedentie.

## Open vragen (NEEDS_INFO)

1. **Invite-flow**: v1 alleen bestaande users op e-mail koppelen, of volledige
   mail-uitnodiging met registratie? (default: koppelen, mail later)
2. **Coach-inzage scope**: alleen sessies/voortgang, of ook wapen-inzichten en
   AI-reflecties van leden? (default: sessies + voortgang + reflecties read-only)
3. **Feature-flag scope**: `aimtrack-ai` vereniging-scoped maken via Pennant nu,
   of als latere enhancement laten (epic noemt het enhancement)? (default: later)
4. **Demo-seeder**: één demo-vereniging met coach+leden toevoegen? (default: ja)

---

## Volgorde van BUILD (na APPROVED)

1. (a) migraties + models + enum + resolver-fallback + tests → commit
2. (b) policies + read-only coach-resources + tests → commit
3. (c) verenigingsbeheer + ledenbeheer + key-beheer + tests → commit
4. Pint + volledige relevante test-run → PR naar develop/main, gekoppeld aan #95.
