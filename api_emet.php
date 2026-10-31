<?php
// API: rep un JSON {"text": "...", "camps": {"clau": "valor", ...}, "nom": "..."}
// i crida emet.py, que fa ell mateix tota la feina de LiveKit (token,
// connexio, publicacio) a mes de la sintesi de veu. Respon quan l'audio
// ja s'ha emes sencer.

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function error(string $msg, int $codi = 400): void
{
    http_response_code($codi);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if (LIVEKIT_URL === '' || LIVEKIT_API_KEY === '' || LIVEKIT_API_SECRET === '') {
    error('Falta configurar el fitxer .env', 500);
}

$cos = json_decode(file_get_contents('php://input'), true);
if (!is_array($cos)) {
    error('Cal enviar un JSON valid al cos de la peticio');
}

$text = trim($cos['text'] ?? '');
$camps = $cos['camps'] ?? [];
$nom = trim($cos['nom'] ?? '');

if ($text === '') {
    error('Falta el text');
}
if (mb_strlen($text) > 500) {
    error('Text massa llarg (maxim 500 caracters)');
}
if (!is_array($camps)) {
    error('"camps" ha de ser un objecte de parelles clau-valor');
}

$nom = mb_substr(preg_replace('/[^\p{L}\p{N} ._-]/u', '', $nom), 0, 40);

$scriptPython = __DIR__ . '/emet.py';
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proces = proc_open(
    [PYTHON_BIN, $scriptPython, LIVEKIT_URL, LIVEKIT_API_KEY, LIVEKIT_API_SECRET, LIVEKIT_ROOM, VEU_TTS],
    $descriptors,
    $pipes
);
if (!is_resource($proces)) {
    error('No s\'ha pogut iniciar Python (revisa PYTHON_BIN al .env)', 500);
}

fwrite($pipes[0], json_encode(['text' => $text, 'nom' => $nom, 'camps' => $camps]));
fclose($pipes[0]);
$sortidaEstandard = trim(stream_get_contents($pipes[1]));
$sortidaError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$codiSortida = proc_close($proces);

if ($codiSortida !== 0) {
    error('Error emetent audio: ' . trim($sortidaError), 500);
}

$resultatPython = json_decode($sortidaEstandard, true);

echo json_encode([
    'success' => true,
    'identity' => $resultatPython['identity'] ?? null,
    'camps' => $camps,
]);
