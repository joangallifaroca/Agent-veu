<?php
// Genera un token d'acces (JWT HS256) per a LiveKit Cloud per a l'oient
// (nomes subscriu, no publica), i el reutilitza (via sessio) mentre
// segueixi sent valid, en lloc de crear-ne un nou a cada peticio.
// POST/GET: nom

require_once __DIR__ . '/jwt.php';

session_start();
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

$nom = trim($_REQUEST['nom'] ?? '');
$nom = mb_substr(preg_replace('/[^\p{L}\p{N} ._-]/u', '', $nom), 0, 40);

$marge = 60; // segons de marge abans de caducar per no donar un token al limit
$desat = $_SESSION['livekit_oient'] ?? null;

if ($desat && $desat['nom'] === $nom && $desat['exp'] > time() + $marge) {
    $token = $desat['token'];
} else {
    $identity = 'oient-' . bin2hex(random_bytes(4)) . ($nom !== '' ? '-' . $nom : '');
    $resultat = creaTokenLiveKit($identity, $nom, false, false, true);
    $token = $resultat['token'];
    $_SESSION['livekit_oient'] = [
        'token' => $token,
        'nom' => $nom,
        'exp' => $resultat['exp'],
    ];
}

echo json_encode([
    'success' => true,
    'url' => LIVEKIT_URL,
    'token' => $token,
    'room' => LIVEKIT_ROOM,
]);
