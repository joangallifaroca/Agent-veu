"""
Sintetitza un text a audio (mp3) amb edge-tts.
Us: python tts.py <fitxer_sortida.mp3> [veu]
El text s'ha de passar per stdin (UTF-8), per evitar problemes
d'escapament/codificacio en passar-lo com a argument de linia d'ordres.
"""
import sys
import asyncio
import edge_tts


async def principal() -> None:
    if len(sys.argv) < 2:
        print("Us: python tts.py <fitxer_sortida.mp3> [veu]", file=sys.stderr)
        sys.exit(1)

    fitxer_sortida = sys.argv[1]
    veu = sys.argv[2] if len(sys.argv) > 2 else "ca-ES-JoanaNeural"

    text = sys.stdin.buffer.read().decode("utf-8").strip()
    if not text:
        print("El text es buit", file=sys.stderr)
        sys.exit(1)

    comunicacio = edge_tts.Communicate(text, veu)
    await comunicacio.save(fitxer_sortida)


if __name__ == "__main__":
    asyncio.run(principal())
