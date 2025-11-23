# Wapens

Handleiding voor het beheren van wapens en AI-trends.

## Wapen toevoegen
1. Ga naar **Wapens** → **Nieuw wapen**.
2. Vul naam, type, kaliber, serienummer, opslaglocatie en optioneel aankoopdatum/notities.
3. Opslaan. Het wapen is nu beschikbaar in sessie-formulieren.

## Wapen bewerken of archiveren
- Open het wapen en pas velden aan. Je kunt een **retired** datum of actief-toggle gebruiken om het uit sessies te houden.
- Sessies behouden hun koppeling; historiek blijft intact.

## Relatie met sessies
- Elke sessie kan meerdere wapens bevatten via `SessionWeaponEntry` records.
- In de wapen-detail zie je een lijst van gekoppelde sessies (relation manager).

## AI-trends genereren
1. Open het wapen-detail.
2. Klik **Genereer AI-trends**.
3. Queue-job analyseert historische sessies voor dit wapen en vult `ai_weapon_insights` met trends, typische problemen en aanbevelingen.
4. Status en eventuele foutmelding zijn zichtbaar; je kunt opnieuw genereren bij fouten.

## Tips
- Houd kaliber en munitietype consistent per sessie-entry voor betere trendanalyse.
- Gebruik notities om problemen te markeren (bijv. “trekkerfout links”), zodat AI hierop kan rapporteren.
