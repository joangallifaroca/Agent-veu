<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agent de veu - Oient</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 700px; margin: 2rem auto; padding: 0 1rem; }
  input, button { padding: .5rem .8rem; font-size: 1rem; }
  #estat { color: #555; margin: .5rem 0; }
  #dades { border: 1px solid #ccc; border-radius: .5rem; padding: .8rem; margin-top: 1rem; display: none; }
  #dades div { margin: .2rem 0; }
</style>
</head>
<body>
<h1>Agent de veu — Oient</h1>
<div>
  <input id="nom" placeholder="El teu nom (opcional)">
  <button id="entra">Entra</button>
  <button id="surt" disabled>Surt</button>
</div>
<div id="estat">Desconnectat</div>
<div id="audios"></div>
<div id="dades"></div>

<script src="https://cdn.jsdelivr.net/npm/livekit-client@2.5.7/dist/livekit-client.umd.js"></script>
<script>
let room = null;
const $ = id => document.getElementById(id);
const estat = t => $('estat').textContent = t;
const escapa = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

async function connecta() {
  $('entra').disabled = true;

  const fd = new FormData();
  fd.append('rol', 'oient');
  fd.append('nom', $('nom').value);
  const r = await (await fetch('token.php', { method: 'POST', body: fd })).json();
  if (!r.success) { estat(r.message); $('entra').disabled = false; return; }

  room = new LivekitClient.Room();

  room.on(LivekitClient.RoomEvent.TrackSubscribed, (track, publication, participant) => {
    if (track.kind === 'audio') {
      const nom = participant.name || participant.identity;
      const bloc = document.createElement('div');
      bloc.dataset.sid = track.sid;
      bloc.innerHTML = '<strong>' + escapa(nom) + ':</strong> ';
      bloc.appendChild(track.attach());
      $('audios').appendChild(bloc);
    }
    estat('Escoltant...');
  });
  room.on(LivekitClient.RoomEvent.TrackUnsubscribed, (track) => {
    track.detach();
    const bloc = $('audios').querySelector('[data-sid="' + track.sid + '"]');
    if (bloc) bloc.remove();
  });
  room.on(LivekitClient.RoomEvent.Disconnected, () => {
    estat('Desconnectat');
    $('entra').disabled = false;
    $('surt').disabled = true;
  });
  room.on(LivekitClient.RoomEvent.AudioPlaybackStatusChanged, () => {
    if (!room.canPlaybackAudio) estat('Prem a qualsevol lloc per activar el so...');
  });
  room.on(LivekitClient.RoomEvent.DataReceived, (payload, participant) => {
    let dades;
    try {
      dades = JSON.parse(new TextDecoder().decode(payload));
    } catch {
      return;
    }
    const nom = dades.nom || (participant ? (participant.name || participant.identity) : '');
    const camps = dades.camps && typeof dades.camps === 'object' ? dades.camps : {};
    let html = '<div><strong>De:</strong> ' + escapa(nom) + '</div>';
    for (const clau in camps) {
      html += '<div><strong>' + escapa(clau) + ':</strong> ' + escapa(camps[clau]) + '</div>';
    }
    $('dades').style.display = 'block';
    $('dades').innerHTML = html;
  });

  await room.connect(r.url, r.token);
  await room.startAudio();
  estat('Connectat a la sala "' + r.room + '". Esperant que l\'agent parli...');
  $('surt').disabled = false;
}

document.body.addEventListener('click', () => { if (room && !room.canPlaybackAudio) room.startAudio(); });

$('entra').onclick = connecta;

$('surt').onclick = () => {
  if (room) room.disconnect();
  $('entra').disabled = false;
  $('surt').disabled = true;
};

connecta();
</script>
</body>
</html>
