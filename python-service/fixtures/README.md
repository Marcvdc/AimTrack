# Eval-fixtures (GH-55)

De harness (`tools/validate_detection.py`) leest foto's uit deze map.

## Foto's hierheen kopieren (niet in git)

```bash
# vanuit de repo-root, kopieer de eval-foto's uit IMAGES/ hierheen:
cp ../IMAGES/IMG_5672.jpg ../IMAGES/IMG_6277.jpg ../IMAGES/IMG_6826.jpg \
   ../IMAGES/IMG_5700.jpg ../IMAGES/IMG_6415.jpg ../IMAGES/IMG_7381.jpg \
   python-service/fixtures/
```

## Labelen + draaien

1. Corrigeer `LABELS-REVIEW.md` -> pas `manifest.provisional.json` aan -> hernoem naar `manifest.json`.
2. Zet `ANTHROPIC_API_KEY` in de omgeving van de python-service.
3. Draai in de container:
   ```bash
   python tools/validate_detection.py fixtures/manifest.json
   ```

De foto's zelf staan in `.gitignore` (te groot / niet nodig in git); alleen manifest + labels worden gecommit.
