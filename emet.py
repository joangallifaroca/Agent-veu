"""
Sintetitza un text a veu (edge-tts) i el publica en directe a una sala
de LiveKit com a participant (sense navegador), junt amb unes dades
(JSON) pel canal de dades de la sala. Tot en memoria, sense fitxers
intermedis.

Es connecta directament a LiveKit Cloud: genera ell mateix el token
d'acces (JWT HS256), no cal cap PHP ni cap altre servei pel mig.

Us: python emet.py <url_livekit> <api_key> <api_secret> <room> <veu>
L'entrada s'ha de passar per stdin com a JSON UTF-8:
  {"text": "...", "nom": "...", "camps": {"clau": "valor", ...}}
"""
import sys
import io
import json
import time
import base64
import hmac
import hashlib
import secrets
import asyncio
import subprocess
import edge_tts
from livekit import rtc

SAMPLE_RATE = 48000
CANALS = 1
DURADA_FRAME_MS = 20
MOSTRES_PER_FRAME = int(SAMPLE_RATE * DURADA_FRAME_MS / 1000)
BYTES_PER_FRAME = MOSTRES_PER_FRAME * CANALS * 2  # PCM s16le


def b64url(dades: bytes) -> str:
    return base64.urlsafe_b64encode(dades).rstrip(b"=").decode()


def crea_token_livekit(api_key: str, api_secret: str, room: str, identity: str, nom: str) -> str:
    ara = int(time.time())
    payload = {
        "iss": api_key,
        "sub": identity,
        "name": nom or identity,
        "nbf": ara,
        "exp": ara + 3600,
        "video": {
            "room": room,
            "roomJoin": True,
            "canPublish": True,
            "canPublishData": True,
            "canSubscribe": False,
        },
    }
    cap = b64url(json.dumps({"alg": "HS256", "typ": "JWT"}).encode())
    cos = b64url(json.dumps(payload).encode())
    signatura = hmac.new(api_secret.encode(), f"{cap}.{cos}".encode(), hashlib.sha256).digest()
    return f"{cap}.{cos}.{b64url(signatura)}"


async def sintetitza(text: str, veu: str) -> bytes:
    comunicacio = edge_tts.Communicate(text, veu)
    mp3 = io.BytesIO()
    async for tros in comunicacio.stream():
        if tros["type"] == "audio":
            mp3.write(tros["data"])
    return mp3.getvalue()


def descodifica_a_pcm(dades_mp3: bytes) -> bytes:
    resultat = subprocess.run(
        [
            "ffmpeg", "-v", "error", "-f", "mp3", "-i", "-",
            "-f", "s16le", "-ar", str(SAMPLE_RATE), "-ac", str(CANALS), "-",
        ],
        input=dades_mp3,
        capture_output=True,
        check=True,
    )
    return resultat.stdout


async def principal() -> None:
    if len(sys.argv) < 6:
        print("Us: python emet.py <url> <api_key> <api_secret> <room> <veu>", file=sys.stderr)
        sys.exit(1)

    url, api_key, api_secret, room_nom, veu = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5]

    entrada = json.loads(sys.stdin.buffer.read().decode("utf-8"))
    text = (entrada.get("text") or "").strip()
    nom = entrada.get("nom") or ""
    camps = entrada.get("camps") or {}

    if not text:
        print("El text es buit", file=sys.stderr)
        sys.exit(1)

    identity = "agent-api-" + secrets.token_hex(4) + (f"-{nom}" if nom else "")
    token = crea_token_livekit(api_key, api_secret, room_nom, identity, nom)

    mp3 = await sintetitza(text, veu)
    pcm = descodifica_a_pcm(mp3)

    room = rtc.Room()
    try:
        await room.connect(url, token)

        font = rtc.AudioSource(SAMPLE_RATE, CANALS)
        pista = rtc.LocalAudioTrack.create_audio_track("tts", font)
        opcions = rtc.TrackPublishOptions(source=rtc.TrackSource.SOURCE_MICROPHONE)
        await room.local_participant.publish_track(pista, opcions)

        await room.local_participant.publish_data(
            json.dumps({"nom": nom, "camps": camps}).encode("utf-8"), reliable=True
        )

        total = len(pcm)
        i = 0
        while i < total:
            tros = pcm[i:i + BYTES_PER_FRAME]
            mostres_tros = len(tros) // 2
            if len(tros) < BYTES_PER_FRAME:
                tros = tros + b"\x00" * (BYTES_PER_FRAME - len(tros))
                mostres_tros = MOSTRES_PER_FRAME
            frame = rtc.AudioFrame(
                data=tros,
                sample_rate=SAMPLE_RATE,
                num_channels=CANALS,
                samples_per_channel=mostres_tros,
            )
            await font.capture_frame(frame)
            i += BYTES_PER_FRAME

        # Dona marge perque s'acabi de reproduir l'audio en cua abans de desconnectar.
        await asyncio.sleep(1.0)
    finally:
        await room.disconnect()

    print(json.dumps({"identity": identity}))


if __name__ == "__main__":
    asyncio.run(principal())
