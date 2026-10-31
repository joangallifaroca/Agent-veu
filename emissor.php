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
  <button id="emet">Emet aquest text</button>
</div>
<div id="estat">Preparat</div>

<script>
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

$('emet').onclick = async () => {
  const text = $('text').value.trim();
  if (!text) return;

  $('emet').disabled = true;
  estat('Emetent...');

  try {
    const r = await (await fetch('api_emet.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text, nom: $('nom').value.trim(), camps: recullParells() }),
    })).json();

    estat(r.success ? 'Emes correctament.' : r.message);
  } catch (e) {
    estat('Error de connexio: ' + e.message);
  }

  $('emet').disabled = false;
};
</script>
</body>
</html>
