# Foto-naar-schoten conversie

Deze gids legt uit hoe u foto's van schotborden kunt uploaden en automatisch kunt omzetten naar gedetecteerde schoten in AimTrack.

## Overzicht

Met de foto-analyse functie kunt u:
- Foto's uploaden van schotborden per beurt
- Automatisch kogel-inslagen detecteren met AI
- Gedetecteerde schotten reviewen en corrigeren
- Tijd besparen bij het registreren van sessies

## Vereisten

1. **Python Image Processing Service**: De microservice moet draaien op `http://localhost:8000` (of de URL geconfigureerd in `IMAGE_PROCESSOR_URL`)
2. **Queue Worker**: De Laravel queue worker moet actief zijn voor asynchrone verwerking
3. **Permissies**: U moet permissies hebben om de sessie te bewerken

## Installatie

### Optie 1: Lokale ontwikkeling

```bash
# Navigeer naar de Python service directory
cd python-service

# Installeer dependencies
pip install -r requirements.txt

# Start de service
uvicorn main:app --reload
```

### Optie 2: Docker

```bash
# Build de image
docker build -t aimtrack-image-service python-service/

# Start de container
docker run -p 8000:8000 aimtrack-image-service
```

## Gebruik

### Stap 1: Navigeer naar schotregistratie

1. Ga naar een sessie in AimTrack
2. Klik op de "Schotten registreren" tab/knop
3. Selecteer de beurt waarvoor u een foto wilt uploaden

### Stap 2: Upload foto

1. Klik op de "Foto uploaden" knop (groen met camera icoon)
2. Selecteer een foto van uw schotbord
3. Optioneel: gebruik de ingebouwde image editor om de foto bij te snijden
4. Klik op "Opslaan"

**Belangrijk**: De foto upload is alleen beschikbaar wanneer u een specifieke beurt heeft geselecteerd, niet "Alle beurten".

### Stap 3: Verwerking

Na het uploaden:
- De foto wordt naar de Python service gestuurd
- OpenCV analyseert de foto en detecteert kogel-inslagen
- Gedetecteerde schoten worden automatisch toegevoegd aan de huidige beurt
- U ontvangt een notificatie wanneer de verwerking voltooid is

### Stap 4: Review en corrigeer

1. Controleer de gedetecteerde schoten in de tabel
2. Gebruik de "Bron" filter om alleen foto-gedetecteerde schoten te zien
3. Verwijder onjuiste schotten met de "Verwijderen" actie
4. Voeg gemiste schotten handmatig toe via de interactieve roos

## Features

### Bron indicatie

Schotten hebben een bron-indicatie:
- **Handmatig** (blauw): Manueel ingevoerd via de roos
- **Foto** (groen): Gedetecteerd uit foto
- **Foto+** (oranje): Foto-gedetecteerd en handmatig gecorrigeerd

### Zekerheidsscore

Gedetecteerde schoten bevatten een zekerheidsscore in de metadata:
- 90-100%: Hoge zekerheid, waarschijnlijk correct
- 70-89%: Redelijke zekerheid, controleren aanbevolen
- <70%: Lagere zekerheid, handmatige controle nodig

### Filters

Gebruik filters om specifieke schotten te tonen:
- **Beurt filter**: Toon schotten per beurt
- **Bron filter**: Filter op handmatig vs foto-gedetecteerd

## Tips voor beste resultaten

### Foto kwaliteit

1. **Goede belichting**: Zorg voor voldoende licht zonder reflecties
2. **Scherp focus**: Zorg dat het schotbord scherp is
3. **Geheel in beeld**: Het complete schotbord moet zichtbaar zijn
4. **Minimale schaduwen**: Vermijd schaduwen over de inslagen

### Schotbord types

De service werkt het beste met:
- ISSF standaard schotborden
- NRA schotborden
- Papieren schotborden met duidelijke ringen

### Wat niet werkt

- Zeer donkere of overbelichte foto's
- Schotborden met zware beschadigingen
- Foto's vanuit een scherpe hoek
- Meerdere schotborden in één foto

## Troubleshooting

### Foto wordt niet verwerkt

1. Controleer of de Python service draait: `curl http://localhost:8000/api/v1/health`
2. Controleer de queue worker: `php artisan queue:work`
3. Bekijk de Laravel logs voor foutmeldingen

### Geen schotten gedetecteerd

1. Controleer de foto kwaliteit
2. Probeer een andere hoek of belichting
3. Controleer of het een ondersteund schotbord type is

### Onjuiste detecties

1. Gebruik de "Bron" filter om foto-gedetecteerde schotten te vinden
2. Verwijder onjuiste schotten
3. Voeg correcte schotten handmatig toe

## Privacy en opslag

- Foto's worden opgeslagen op de `private` disk
- Originele foto's worden 30 dagen bewaard
- Alleen geëxtraheerde coördinaten en metadata blijven permanent
- Foto's worden niet gedeeld met externe diensten (tenzij u de Python service extern host)

## Technische details

### API endpoint

```
POST /api/v1/analyze-target
Content-Type: multipart/form-data
```

### Response formaat

```json
{
  "success": true,
  "shots": [
    {
      "x": -0.234,
      "y": 0.456,
      "confidence": 0.892
    }
  ],
  "total_detected": 1
}
```

### Coördinaten

- Coördinaten zijn genormaliseerd (-1 tot 1)
- (0,0) is het centrum van het schotbord
- Y-as is omgekeerd (negatief = bovenaan)

## Ondersteuning

Voor problemen of vragen:
1. Controleer de Laravel logs in `storage/logs/laravel.log`
2. Controleer de Python service logs
3. Neem contact op via de gebruikelijke kanalen
