# Agent de veu (text → parla) amb LiveKit Cloud

Sistema que converteix text a veu (català inclòs) i l'emet en directe a
una sala de [LiveKit Cloud](https://cloud.livekit.io), on qualsevol
"oient" connectat el sent en temps real. No hi ha vídeo.

Hi ha dues maneres d'emetre:

- **Des del navegador**, amb la pàgina `emissor.php`.
- **Com a API**, cridant `api_emet.php` amb un JSON — sense navegador.

## Arquitectura

```
                 ┌──────────────┐
  navegador ───► │ emissor.php  │──► genera_audio.php ──► tts.py (edge-tts)
                 └──────────────┘        │
                        │ publica l'àudio (WebRTC) i les dades (publishData)
                        ▼
                 ┌──────────────────────┐
  app/servidor ─►│    api_emet.php      │──► emet.py (edge-tts + ffmpeg + LiveKit SDK)
                 └──────────────────────┘        │
                                                  │ publica l'àudio i les dades
                                                  ▼
                                     ┌─────────────────────────┐
                                     │   Sala LiveKit Cloud     │
                                     └─────────────────────────┘
                                                  │
                                                  ▼
                                          index.php (oient)
```

### Camí 1: navegador (`emissor.php` → `index.php`)

1. L'usuari escriu un text i, opcionalment, parelles clau-valor a
   `emissor.php`.
2. El navegador demana el text sintetitzat a `genera_audio.php`, que
   crida `tts.py` (Python + `edge-tts`) i genera un fitxer `.mp3` dins
   de `audio/`.
3. El navegador carrega aquest mp3 en un `<audio>` ocult i, amb
   `captureStream()`, en treu una pista d'àudio que publica a la sala
   amb `livekit-client`. Les parelles clau-valor es publiquen pel canal
   de dades (`publishData`).
4. `index.php` (l'oient) es connecta automàticament a la sala en
   carregar la pàgina, reprodueix qualsevol pista d'àudio que arribi i
   mostra el nom de qui l'ha enviat junt amb les dades rebudes.

### Camí 2: API (`api_emet.php`), sense navegador

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
sense passar per PHP:

```
echo '{"text":"Hola","nom":"bot","camps":{"tiquet":"1"}}' | \
  python emet.py "wss://el-teu-projecte.livekit.cloud" "<API_KEY>" "<API_SECRET>" "agent-veu" "ca-ES-JoanaNeural"
```

## Fitxers

| Fitxer | Funció |
|---|---|
| `emissor.php` | Pàgina web per emetre text manualment (navegador). |
| `index.php` | Pàgina web de l'oient: es connecta sol i reprodueix l'àudio i les dades. |
| `api_emet.php` | API HTTP per emetre sense navegador. |
| `token.php` | Genera tokens LiveKit per a `emissor.php`/`index.php`; els reutilitza via `$_SESSION` mentre siguin vàlids. |
| `jwt.php` | Generació de tokens JWT HS256 per a LiveKit (usat per `token.php`). |
| `genera_audio.php` / `tts_lib.php` | Sintetitzen text a `.mp3` cridant `tts.py` (usat pel camí del navegador). |
| `tts.py` | Sintetitza un text a `.mp3` amb `edge-tts` (escriu a disc). |
| `emet.py` | Sintetitza (en memòria), descodifica i publica l'àudio i les dades a LiveKit; autònom (genera el seu propi token). |
| `config.php` | Carrega la configuració de `.env`. |

## Requisits

- PHP amb `proc_open` habilitat (ho està per defecte).
- Python 3 amb els paquets de `requirements.txt` (`edge-tts`, `livekit`):
  ```
  pip install -r requirements.txt
  ```
- `ffmpeg` instal·lat i accessible al `PATH` (usat per `emet.py` per
  descodificar l'mp3 a PCM).
- Un projecte creat a [cloud.livekit.io](https://cloud.livekit.io).
- Navegador amb suport de `HTMLMediaElement.captureStream()` (Chrome/Edge
  van bé; Firefox fa servir `mozCaptureStream()`, ja gestionat al codi)
  — només necessari per a `emissor.php`, no per a l'API.

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

- Obre `http://localhost:8000/emissor.php`, prem "Connecta a la sala",
  afegeix parelles clau-valor si cal i prem "Emet aquest text".
- Obre `http://localhost:8000/index.php` en una altra pestanya/dispositiu
  — es connecta sol i mostra l'àudio i les dades rebudes.
- Per emetre via API:
  ```
  curl -X POST http://localhost:8000/api_emet.php \
    -H "Content-Type: application/json" \
    -d '{"text":"Hola, aixo es una prova","nom":"bot","camps":{"tiquet":"123"}}'
  ```

## Notes

- Aquest projecte és un demo senzill: `token.php` i `api_emet.php` no
  demanen cap contrasenya per emetre. Si l'exposes fora de la teva
  xarxa local, afegeix algun control d'accés abans.
- Els fitxers generats a `audio/` (camí del navegador) no s'esborren
  automàticament; en un entorn de producció caldria netejar-los
  periòdicament.
- `api_emet.php` no genera cap fitxer a `audio/`: tot passa en memòria.
