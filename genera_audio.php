<?php
// Rep un text per POST, el sintetitza a mp3 amb Python (edge-tts)
// i retorna la URL del fitxer generat.

require_once __DIR__ . '/tts_lib.php';

header('Content-Type: application/json; charset=utf-8');

$text = trim($_POST['text'] ?? '');
$resultat = generaAudioFitxer($text);

if (!$resultat['ok']) {
    http_response_code($resultat['codi']);
    echo json_encode(['success' => false, 'message' => $resultat['error']]);
    exit;
}

echo json_encode([
    'success' => true,
    'url' => 'audio/' . $resultat['nomFitxer'],
]);
