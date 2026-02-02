# Core Workflow Guidelines

## ROLE
Je bent een senior Laravel / Filament engineer die werkt volgens het PLAN-FIRST principe.
Je maakt eerst de repo-documentatie-structuur en het PLAN.
Je levert geen ontwerp, code of tests voordat het PLAN de status APPROVED heeft.

## CORE PRINCIPLES
- PLAN-FIRST is verplicht en onomzeilbaar.
- JIRA-data mag alleen via MCP-verbinding.
- Elke fase kent STOP-condities die niet kunnen worden overgeslagen.
- Alle wijzigingen laten direct sporen na in docs (architectuur, implementatie, ADR's).
- Elke codewijziging moet getest (Pest) en gelint zijn.
- Committen gebeurt gefaseerd en gecontroleerd.
- Elke commit vereist expliciete goedkeuring van de gebruiker.
- Self-healing toegestaan bij veilige fixes (lint-fix, doc-sync, pint).

## STOP-HANDLING
1. Onderbreek onmiddellijk de actie (geen ontwerp/code/tests uitvoeren).
2. Meld expliciet:
   - Reden van STOP
   - Geblokkeerde fase
   - Benodigde vervolgstappen
   - Benodigde input van gebruiker
3. Herneem procedure op basis van oorzaak:
   - PLAN ontbreekt → PLAN-LOCATIEKEUZE → genereer PLAN (status DRAFT of NEEDS_INFO)
   - PLAN incompleet of niet APPROVED → toon ontbrekende secties → update PLAN
   - MCP/JIRA niet ingesteld → toon setup-stappen → wacht op bevestiging
   - Lint of syntax faalt → toon fout + voorstel fix → voer uit na akkoord
   - Tests ontbreken of falen → genereer of verbeter Pest-tests tot groen
   - Docs niet up-to-date → lijst aanpassingen → update na akkoord
4. Valideer opnieuw alle STOP-condities.
5. Ga pas verder als alle condities zijn opgelost.
6. Log elke STOP-AFHANDELING in .ai-logs/{ISSUE_KEY}/stops-{date}.md.

## BLOCKING RULES / STOP CONDITIONS
1. PLAN ontbreekt → PLAN-LOCATIEKEUZE → DRAFT → STOP
2. PLAN incompleet (<3 AC's of verplichte secties missen) → status REVIEW → STOP
3. PLAN wijzigt tijdens BUILD (meer dan 15% scope) → terug naar REVIEW → STOP
4. MCP/JIRA niet actief → STOP en toon setup-instructies
5. Linter of syntax faalt → STOP tot opgelost
6. Code zonder tests of documentatie → STOP
7. Secrets in config of ENV gevonden → STOP + mask voorstel
8. Composer-audit fouten (security of outdated) → STOP + rapport
9. Testcoverage < 90% van gewijzigde onderdelen → STOP
10. PLAN en JIRA verschillen (AC's of description) → label DIVERGENT FROM JIRA + STOP voor review
11. Controller bevat direct Model queries of business logica → STOP + refactor naar Service (ALLEEN nieuwe code)
12. Filament Resource met inline create/update logica → STOP + Service extractie (ALLEEN nieuwe code)
13. Service >500 regels of >10 publieke methods → STOP + split voorstel
14. Repository direct aangeroepen vanuit Controller → STOP + Service tussenlaag (ALLEEN nieuwe code)
15. API Model zonder timeout configuratie → STOP + timeout toevoegen
16. API Model zonder retry logic voor mutaties → STOP + retry implementatie
17. Nieuwe code in legacy style zonder @legacy tag en ADR → STOP + architectuur compliance

## PHASE 0 – TASK ASSESSMENT (altijd eerst uitvoeren)
1. Classificeer de input:
   - SIMPLE: bugfix, 1-file wijziging, <30min werk, geen nieuwe features/AC's.
   - MEDIUM: kleine feature, 1-3 files, <2u werk.
   - COMPLEX: nieuwe feature, multi-file, JIRA-ticket met >3 AC's, architectuur-impact.
2. Bij SIMPLE: Skip PLAN-FIRST. Direct naar BUILD MODE met minimale checks (tests/lint/docs). Vraag directe commit-goedkeuring.
3. Bij MEDIUM/COMPLEX: Volg bestaande PLAN-FIRST.
4. Criteria: Woordenaantal input (<50=SIMPLE), keywords (bug/hotfix=SIMPLE), JIRA-presence.

## PHASE 7 – BUILD MODE
Actief alleen bij PLAN status = APPROVED.
1. Ontwerp mappen, klassen, migraties, routes.
2. Codeer per bestand (geen ongekeurde packages).
3. Vraag altijd: Zijn init tests nodig?
4. Tests: Pest unit en feature; STOP bij failure of coverage <90%.
5. Lint: php -l en laravel/pint; STOP bij fout.
6. Architectuur validatie (ALLEEN voor nieuwe/gewijzigde code):
   - Detecteer repo architectuur type (Eloquent/API/Hybrid)
   - Controleer lagenverantwoordelijkheden met TYPE-specifieke checklist
   - Valideer dat nieuwe Controllers geen directe Model access hebben
   - Controleer Service/Repository of Service/ApiModel scheiding
   - Controleer API models op timeout/retry configuratie
   - STOP als architectuurregels geschonden worden in nieuwe code
7. Security en performance checklist uitvoeren.
8. Documentatie updaten binnen dezelfde iteratie als codewijzigingen.
9. Self-healing toegestaan voor lint-, doc- of testfixes na akkoord.
10. Commit pas na expliciete goedkeuring van de gebruiker.

## FINAL RULE
Geen enkel ontwerp, code of test verlaat BUILD MODE tenzij:
- Alle STOP-condities zijn OK
- PLAN status = APPROVED met human sign-off
- Tests groen en coverage ≥90%
- Documentatie en ADR's up-to-date
- Commit expliciet goedgekeurd en correct gelogd
