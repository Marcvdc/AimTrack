# AimTrack Issue 0055 – Foto-naar-schoten conversie Plan
Status: DRAFT
Auteur: Cascade AI
Datum: 2026-02-14
Issue: GH-55 — "Via foto schoten bepalen op schotbord"

## 1. Context & Aanleiding
- Gebruikers moeten momenteel alle schoten handmatig registreren in het systeem via de interactieve schotroos of handmatige invoer.
- Dit is tijdrovend en foutgevoelig, vooral bij sessies met veel schoten.
- Veel schutters maken al foto's van hun schotborden na elke beurt voor eigen administratie.
- Door foto's automatisch om te zetten naar schotposities kunnen gebruikers sneller en nauwkeuriger hun sessies registreren.

## 2. Doelen
1. Gebruikers kunnen per beurt een foto uploaden van hun schotbord.
2. Het systeem analyseert de foto en detecteert kogel/schot-inslag posities.
3. Gedetecteerde schoten worden automatisch omgezet naar SessionShot records met genormaliseerde x/y coördinaten.
4. Gebruikers kunnen de gedetecteerde schoten reviewen en handmatig bijwerken indien nodig.
5. Integratie met bestaande Session en SessionShot workflow.

## 3. Scope & Grenzen

### In scope
- **Upload functionaliteit**: Foto upload per beurt binnen een sessie (via Filament FileUpload component).
- **Image processing**: Detectie van kogel-inslagen op een schotbord foto.
- **Conversie naar coördinaten**: Transformatie van pixel-posities naar genormaliseerde x/y coördinaten (-1 tot 1).
- **Turn-based opslag**: Koppeling van gedetecteerde schoten aan `turn_index` in `session_shots` tabel.
- **Review interface**: Filament interface om gedetecteerde schoten te reviewen en aan te passen.
- **Documentatie**: User guide update + technische documentatie van image processing pipeline.

### Out of scope
- Automatische detectie van schotbord type/kaliber (fase 1 focust op standaard papieren schotborden).
- Real-time camera capture via web/mobile app (enkel upload van bestaande foto's).
- Geavanceerde AI training op custom schotbord types (gebruik maken van bestaande oplossingen).
- Automatische kalibratie voor verschillende lichtomstandigheden (gebruiker moet redelijk scherpe foto's maken).
- Video-naar-schoten conversie.

## 4. Constraints & Assumpties

### Constraints
- **C-PII-Logging**: Geüploade foto's kunnen potentieel persoonsgegevens bevatten (achtergrond, metagegevens). Privacy afwegingen nodig.
- **C-API-Timeouts**: Image processing moet binnen redelijke tijd (< 30s per foto) voltooien om timeout te voorkomen.
- **C-Storage**: Foto's opslaan verhoogt storage kosten. Overwegen of originele foto's behouden blijven of alleen geëxtraheerde data.
- **C-Accuracy**: Detectie kan niet 100% accuraat zijn; review-stap is cruciaal voor data kwaliteit.

### Assumpties
- Gebruikers uploaden foto's van standaard concentrische schotborden (ISSF, NRA, etc.).
- Foto's zijn gemaakt onder redelijke lichtomstandigheden en zijn voldoende scherp.
- Er is budget/bereidheid voor externe API kosten (Google Vision, AWS Rekognition) of server resources voor OpenCV.
- `session_shots` tabel wordt reeds gebruikt voor opslag van individuele schoten met turn_index.

## 5. Technische Oplossingen – Analyse

### Optie A: Cloud Vision API (Google Cloud Vision / AWS Rekognition)
**Voordelen:**
- Geen complexe server-side setup nodig.
- Snelle integratie via Laravel HTTP client.
- Schaalbaar en goed onderhouden.

**Nadelen:**
- **Kosten per API call** (kan oplopen bij veel foto's).
- Generieke object detection - **niet specifiek getraind op kogel-inslagen**.
- Privacy concerns: foto's worden naar third-party cloud gestuurd.
- Vereist custom post-processing om "holes" te identificeren.

**Conclusie:** Minder geschikt voor deze use case zonder custom training.

---

### Optie B: OpenCV via PHP bindings (php-opencv)
**Voordelen:**
- **Open source** en geen API kosten.
- **Privacy-vriendelijk**: alle processing blijft on-premise.
- Bewezen techniek voor bullet hole detection (97% success rate in research).
- Full control over detection algoritme.

**Nadelen:**
- **Complex om op te zetten**: vereist PHP OpenCV extensie compilatie/installatie.
- **Server resources**: CPU-intensief, kan slow zijn zonder GPU.
- Onderhoudsrisico: php-opencv is minder mainstream dan Python/C++ OpenCV.

**Aanpak:**
1. Installeer php-opencv extensie (OpenCV 4.5+).
2. Implementeer blob detection algoritme:
   - Convert to grayscale
   - Apply threshold to find dark spots (bullet holes)
   - Use SimpleBlobDetector or contour detection
   - Calculate centroid van elke hole
3. Transformeer pixel coördinaten naar normalized (-1, 1) coordinaten.
4. Schrijf Service class voor image processing logica.

**Conclusie:** Technisch haalbaar maar vereist server configuratie en onderhoud.

---

### Optie C: Python microservice met OpenCV (aanbevolen)
**Voordelen:**
- **Best practice**: Python is de standaard voor computer vision (OpenCV, NumPy).
- **Rijke ecosystem**: Veel voorbeelden en libraries voor bullet hole detection.
- **Performance**: Sneller dan PHP bindings, eenvoudiger te optimaliseren.
- **Modulair**: Microservice kan los draaien en schalen.
- Pre-trained models beschikbaar (Roboflow dataset met 1243 images).

**Nadelen:**
- Introduceert extra infrastructuur component (Python service).
- Inter-process communication overhead (HTTP/queue).
- Deployment complexity stijgt licht.

**Aanpak:**
1. Creëer Python FastAPI microservice voor image processing.
2. Endpoint: `POST /analyze-target` met multipart image upload.
3. Gebruik OpenCV + pre-trained model (TensorFlow/YOLO) voor bullet hole detection.
4. Return JSON met detected shot coordinates.
5. Laravel queue job stuurt foto naar Python service en verwerkt response.
6. Deploy Python service als Docker container naast Laravel app.

**Conclusie:** **Aanbevolen voor productie** - beste balans tussen performance, maintainability en accuracy.

---

### Optie D: Browser-based detection (TensorFlow.js)
**Voordelen:**
- Client-side processing (geen server load).
- Privacy-vriendelijk (foto blijft lokaal).
- Instant feedback voor gebruiker.

**Nadelen:**
- **Browser performance varieert** sterk (mobiel vs desktop).
- Complex om model te trainen en deployen.
- Vereist JavaScript expertise + ML model training.
- Moeilijker te testen en debuggen.

**Conclusie:** Interessant voor toekomstige iteratie maar te complex voor MVP.

---

## 6. Gekozen Aanpak: Python Microservice (Optie C)

### Architectuur
```
┌─────────────┐       ┌──────────────┐       ┌─────────────────┐
│  Filament   │──1──▶ │   Laravel    │──2──▶ │  Python CV      │
│  Upload     │       │   Queue Job  │       │  Microservice   │
└─────────────┘       └──────────────┘       └─────────────────┘
                             │                        │
                             │                        │
                             │◀───────3───────────────┘
                             │  JSON: [{x, y}, ...]
                             │
                             ▼
                      ┌──────────────┐
                      │  SessionShot │
                      │   Records    │
                      └──────────────┘
```

### Stappen
1. **Filament Upload Component**: Voeg FileUpload toe aan SessionResource voor turn-based foto upload.
2. **Storage**: Sla foto op in Laravel storage (private disk voor privacy).
3. **Queue Job**: `AnalyzeTurnPhotoJob` die foto naar Python service stuurt.
4. **Python Service**:
   - Endpoint: `POST /api/v1/analyze-target`
   - Input: multipart image file
   - Processing: OpenCV blob detection + coordinate normalization
   - Output: JSON met `[{x: -0.5, y: 0.3, confidence: 0.95}, ...]`
5. **Response Processing**: Laravel job ontvangt coordinates en creëert SessionShot records.
6. **Review Interface**: Filament infolist/form om gedetecteerde schoten te tonen en aan te passen.

### Database Schema Aanpassingen
Huidige `session_shots` tabel ondersteunt al:
- `turn_index` (voor groepering per beurt)
- `x_normalized`, `y_normalized` (coördinaten)
- `metadata` (JSON - kan confidence score bevatten)

**Nieuwe kolom toevoegen:**
```php
$table->string('source')->default('manual'); // 'manual', 'photo_detected', 'photo_corrected'
```

Dit helpt onderscheid maken tussen handmatige en gedetecteerde schoten.

## 7. Acceptatiecriteria
1. **Upload**: Gebruiker kan via Filament een foto uploaden bij een beurt binnen een sessie.
2. **Processing**: Geüploade foto wordt binnen 30 seconden geanalyseerd en schoten worden gedetecteerd.
3. **Accuracy**: Minimaal 90% van duidelijk zichtbare kogel-inslagen wordt correct gedetecteerd (te valideren met test dataset).
4. **Review**: Gedetecteerde schoten worden getoond in een overzicht waar gebruiker kan:
   - Schoten verwijderen (false positives)
   - Handmatig schoten toevoegen (missed detections)
   - Posities aanpassen
5. **Integratie**: Gedetecteerde schoten verschijnen in bestaande SessionShot lijst en worden meegenomen in statistieken/AI reflectie.
6. **Documentatie**:
   - User guide update met instructies voor foto upload en review.
   - Tech docs voor Python service deployment en API specificatie.
7. **Tests**:
   - Feature test voor upload + processing workflow.
   - Unit test voor coordinate normalization logic.
   - Integration test met mock Python service response.

## 8. Afhankelijkheden & Open Vragen

### Afhankelijkheden
- Python/FastAPI deployment infrastructure (Docker).
- OpenCV + dependencies in Python environment.
- Laravel queue worker active (voor async processing).
- Storage capacity voor foto's (beslissen of originelen behouden worden).

### Open Vragen
1. **Privacy**: Mogen we foto's bewaren of alleen extracted data? → GDPR compliance check.
2. **Schotbord types**: Welke standaard schotborden moeten we ondersteunen in MVP? (ISSF, NRA, 25m pistool, etc.)
3. **Kaliber detectie**: Moet systeem automatisch detecteren of vraagt gebruiker dit vooraf aan? → Voorlopig **handmatig** aangeven.
4. **Foto retentie**: Hoelang bewaren we originele foto's? → Voorstel: 30 dagen, daarna alleen metadata.
5. **Fallback**: Wat bij service downtime? → Graceful degradation: gebruiker kan altijd handmatig invoeren.

## 9. Fasering

### Fase 1 (MVP - deze PLAN): Photo Upload + Detection
- Python CV microservice opzetten.
- Filament upload + review interface.
- Basis OpenCV blob detection.
- Support voor 1-2 standaard schotbord types.

### Fase 2 (Toekomstig): Enhanced Detection
- Pre-trained ML model (YOLO/TensorFlow) voor hogere accuracy.
- Support voor meer schotbord types.
- Auto-kalibratie voor verschillende lighting.

### Fase 3 (Toekomstig): Mobile Integration
- Native mobile app met camera capture.
- Real-time preview van detectie.

## 10. Risico's & Mitigaties

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| Lage detection accuracy | Hoog | Implementeer uitgebreide review interface; start met kleine test groep gebruikers |
| Python service downtime | Middel | Graceful degradation naar manual input; monitoring/alerts |
| Storage kosten te hoog | Middel | Auto-delete originele foto's na 30 dagen; comprimeer bij opslag |
| Privacy compliance issues | Hoog | Legal review GDPR; opt-in voor foto upload; duidelijke data retention policy |
| Complex deployment | Middel | Docker Compose setup; gedetailleerde deployment docs |

## 11. Timeline Schatting (rough)
- **Python service setup**: 2-3 dagen
- **Laravel integration (upload/queue/storage)**: 2 dagen
- **Filament review interface**: 2 dagen
- **Testing + bugfixes**: 2 dagen
- **Documentation**: 1 dag
- **Total**: ~9-10 werkdagen (MVP)

---

**Status wijzigingen:**
- [ ] DRAFT → REVIEW (na eerste feedback)
- [ ] REVIEW → APPROVED (na stakeholder goedkeuring)
- [ ] APPROVED → IN PROGRESS (start implementatie)
