# Agent de veu (text → parla) amb LiveKit Cloud

Sistema que converteix text a veu (català inclòs) i l'emet en directe a
una sala de [LiveKit Cloud](https://cloud.livekit.io), on qualsevol
"oient" connectat el sent en temps real. No hi ha vídeo.

Tot l'àudio es genera i es publica en un únic lloc: `emet.py`. No hi ha
cap pas intermedi ni cap fitxer `.mp3` guardat al disc — l'emissor
(navegador o qualsevol altre client) només crida l'API.

## Arquitectura

```
                 ┌──────────────┐
  navegador ───► │ emissor.php  │──┐
                 └──────────────┘  │  POST JSON { text, nom, camps }
                                    ▼
                           ┌──────────────┐
  qualsevol client HTTP ─► │ api_emet.php │──► emet.py (edge-tts + ffmpeg + LiveKit SDK)
                           └──────────────┘        │
                                                    │ publica l'audio (WebRTC)
                                                    │ i les dades (publishData)
                                                    ▼
                                     ┌─────────────────────────┐
                                     │   Sala LiveKit Cloud     │
                                     └─────────────────────────┘
                                                    │
                                                    ▼
                                            index.php (oient)
```

### Emetre

`emissor.php` és només una interfície senzilla (nom, text, parelles
clau-valor) que fa una crida HTTP — no es connecta mai a LiveKit ni fa
servir `livekit-client`:

```
POST /api_emet.php
Content-Type: application/json

{
  "text": "Hola, aixo es una prova",
  "nom": "bot",
  "camps": { "tiquet": "123", "codi": "ABC" }
}
```

`api_emet.php` valida el JSON i crida `emet.py`, que en un **sol procés
Python** fa tota la feina sense cap fitxer intermedi:

1. Sintetitza el text amb `edge-tts` (en memòria).
2. El descodifica a PCM amb `ffmpeg`.
3. Genera ell mateix el token d'accés de LiveKit (JWT HS256, sense
   dependre de PHP ni de cap altre servei).
4. Es connecta a la sala com a participant del servidor (SDK `livekit`,
   sense WebRTC al navegador) i publica la pista d'àudio en directe.
5. Publica `{"nom": ..., "camps": {...}}` pel canal de dades.

La crida HTTP respon quan l'àudio ja s'ha emès sencer (mode síncron).
`emet.py` també es pot cridar directament des de la línia de comandes,
sense passar per PHP ni per cap client web:

```
echo '{"text":"Hola","nom":"bot","camps":{"tiquet":"1"}}' | \
  python emet.py "wss://el-teu-projecte.livekit.cloud" "<API_KEY>" "<API_SECRET>" "agent-veu" "ca-ES-JoanaNeural"
```

### Escoltar

`index.php` (l'oient) es connecta automàticament a la sala en carregar
la pàgina (token obtingut via `token.php`), reprodueix qualsevol pista
d'àudio que arribi i mostra el nom de qui l'ha enviat junt amb les
dades rebudes pel canal de dades.

## Fitxers

| Fitxer | Funció |
|---|---|
| `emissor.php` | Pàgina web senzilla per emetre text; crida `api_emet.php`. |
| `index.php` | Pàgina web de l'oient: es connecta sol i reprodueix l'àudio i les dades. |
| `api_emet.php` | API HTTP: valida la peticio i crida `emet.py`. |
| `emet.py` | Unic lloc on es genera i es publica l'audio: sintetitza (edge-tts), descodifica (ffmpeg) i publica a LiveKit; autonom (genera el seu propi token). |
| `token.php` | Genera tokens LiveKit nomes per a l'oient (`index.php`); els reutilitza via `$_SESSION` mentre siguin valids. |
| `jwt.php` | Generacio de tokens JWT HS256 per a LiveKit (usat per `token.php`). |
| `config.php` | Carrega la configuracio de `.env`. |

## Requisits

- PHP amb `proc_open` habilitat (ho està per defecte).
- Python 3 amb els paquets de `requirements.txt` (`edge-tts`, `livekit`):
  ```
  pip install -r requirements.txt
  ```
- `ffmpeg` instal·lat i accessible al `PATH` (usat per `emet.py` per
  descodificar l'mp3 a PCM).
- Un projecte creat a [cloud.livekit.io](https://cloud.livekit.io).

## Configuració

Copia `.env.example` a `.env` i omple:

- `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET` — del teu
  projecte de LiveKit Cloud.
- `LIVEKIT_ROOM` — nom de la sala (per defecte `agent-veu`).
- `PYTHON_BIN` — ruta o nom de l'executable de Python (per defecte
  `python`).
- `VEU_TTS` — veu d'`edge-tts` a fer servir (per defecte
  `ca-ES-JoanaNeural`).

## Execució

```
php -S localhost:8000
```

- Obre `http://localhost:8000/emissor.php`, afegeix parelles clau-valor
  si cal i prem "Emet aquest text".
- Obre `http://localhost:8000/index.php` en una altra pestanya/dispositiu
  — es connecta sol i mostra l'àudio i les dades rebudes.
- Per emetre via API des de qualsevol altre client:
  ```
  curl -X POST http://localhost:8000/api_emet.php \
    -H "Content-Type: application/json" \
    -d '{"text":"Hola, aixo es una prova","nom":"bot","camps":{"tiquet":"123"}}'
  ```

## Notes

- Aquest projecte és un demo senzill: `api_emet.php` i `token.php` no
  demanen cap contrasenya. Si l'exposes fora de la teva xarxa local,
  afegeix algun control d'accés abans.
- No es genera ni es desa cap fitxer d'àudio al disc: tot passa en
  memòria dins `emet.py`.
