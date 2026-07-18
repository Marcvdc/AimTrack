# Eval-labels — PROVISIONEEL (corrigeer wat fout is)

Deze labels zijn automatisch voor-ingevuld door Claude-vision op de rúwe foto's.
**Ze zijn NIET geverifieerd tegen de werkelijkheid.** Jij weet de echte schoten — corrigeer per foto:

1. **`target_type`** — bevestig de discipline (jij weet welke roos je schoot). Geldig: `kkp_25m`, `gkp_25m`, `kkg_50m`, `kkg_100m`, `gkg_100m`.
2. **`expected_shot_count`** — het echte aantal schoten van die beurt.
3. **`truth`** — de echte ringwaarde per schot.

Pas daarna `fixtures/manifest.provisional.json` aan en hernoem naar `fixtures/manifest.json`.

| Foto | discipline (GOK — check) | aantal (prov.) | ringen (prov.) |
|---|---|---|---|
| IMG_5672.jpg | `kkp_25m` | 5 | 8, 8, 7, 7, 6 |
| IMG_5700.jpg | `gkg_100m` | 0 |  |
| IMG_6277.jpg | `kkg_100m` | 4 | 9, 8, 6, 6 |
| IMG_6415.jpg | `kkp_25m` | 15 | 9, 9, 8, 8, 8, 7, 7, 7, 7, 6, 6, 6, 6, 6, 3 |
| IMG_6826.jpg | `kkp_25m` | 5 | 8, 7, 7, 6, 5 |
| IMG_7381.jpg | `kkp_25m` | 6 | 8, 8, 8, 7, 6, 6 |

> Tip: begin met deze 6 en breid uit met 2-3 foto's per discipline voor een gebalanceerde eval-set.