<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agent de veu - Emissor</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 700px; margin: 2rem auto; padding: 0 1rem; }
  textarea { width: 100%; height: 6rem; font-size: 1rem; padding: .5rem; box-sizing: border-box; }
  input, button { padding: .5rem .8rem; font-size: 1rem; }
  button { margin: .5rem .5rem .5rem 0; }
  #estat { color: #555; margin: .5rem 0; white-space: pre-wrap; }
  .parell { display: flex; gap: .5rem; margin-bottom: .4rem; }
  .parell input { flex: 1; }
</style>
</head>
<body>
<h1>Agent de veu — Emissor</h1>
<div>
  <input id="nom" placeholder="Nom (opcional)">
</div>
<div>
  <div id="parells"></div>
  <button type="button" id="afegeix-parell">+ Afegeix camp</button>
</div>
<textarea id="text" placeholder="Escriu el text que vols que es senti..."></textarea>
<div>
  <button id="connecta">Connecta a la sala</button>
  <button id="emet" disabled>Emet aquest text</button>
</div>
<div id="estat">Desconnectat</div>
<audio id="player" style="display:none"></audio>

<script src="https://cdn.jsdelivr.net/npm/livekit-client@2.5.7/dist/livekit-client.umd.js"></script>
<script>
let room = null;
let trackPublicat = null;
const $ = id => document.getElementById(id);
const estat = t => $('estat').textContent = t;

function afegeixParell(clau = '', valor = '') {
  const fila = document.createElement('div');
  fila.className = 'parell';
  fila.innerHTML =
    '<input class="clau" placeholder="Clau">' +
    '<input class="valor" placeholder="Valor">' +
    '<button type="button" class="treu">Treu</button>';
  fila.querySelector('.clau').value = clau;
  fila.querySelector('.valor').value = valor;
  fila.querySelector('.treu').onclick = () => fila.remove();
  $('parells').appendChild(fila);
}

function recullParells() {
  const dades = {};
  for (const fila of $('parells').querySelectorAll('.parell')) {
    const clau = fila.querySelector('.clau').value.trim();
    const valor = fila.querySelector('.valor').value.trim();
    if (clau !== '') dades[clau] = valor;
  }
  return dades;
}

$('afegeix-parell').onclick = () => afegeixParell();
afegeixParell();

$('connecta').onclick = async () => {
  const fd = new FormData();
  fd.append('rol', 'emissor');
  fd.append('nom', $('nom').value);
  const r = await (await fetch('token.php', { method: 'POST', body: fd })).json();
  if (!r.success) { estat(r.message); return; }

  room = new LivekitClient.Room();
  room.on(LivekitClient.RoomEvent.Disconnected, () => estat('Desconnectat'));

  await room.connect(r.url, r.token);

  estat('Connectat a la sala "' + r.room + '"');
  $('connecta').disabled = true;
  $('emet').disabled = false;
};

$('emet').onclick = async () => {
  const text = $('text').value.trim();
  if (!text || !room) return;

  $('emet').disabled = true;
  estat('Generant audio...');

  const fd = new FormData();
  fd.append('text', text);
  const r = await (await fetch('genera_audio.php', { method: 'POST', body: fd })).json();
  if (!r.success) { estat(r.message); $('emet').disabled = false; return; }

  const player = $('player');
  player.srcObject = null;
  player.src = r.url + '?t=' + Date.now();

  player.oncanplaythrough = async () => {
    player.oncanplaythrough = null;

    const stream = player.captureStream ? player.captureStream() : player.mozCaptureStream();
    const [pistaAudio] = stream.getAudioTracks();

    if (trackPublicat) {
      await room.localParticipant.unpublishTrack(trackPublicat);
      trackPublicat.stop();
    }

    trackPublicat = new LivekitClient.LocalAudioTrack(pistaAudio);
    await room.localParticipant.publishTrack(trackPublicat);

    const dades = { text, camps: recullParells() };
    await room.localParticipant.publishData(
      new TextEncoder().encode(JSON.stringify(dades)),
      { reliable: true }
    );

    await player.play();
    estat('Emetent: "' + text + '"');
  };

  player.onended = () => {
    estat('Connectat a la sala "' + room.name + '". Preparat per al seguent text.');
    $('emet').disabled = false;
  };
};
</script>
</body>
</html>
