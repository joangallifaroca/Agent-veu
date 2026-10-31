<?php
// Sintetitza un text a mp3 amb Python (edge-tts) dins la carpeta audio/.

require_once __DIR__ . '/config.php';

function generaAudioFitxer(string $text): array
{
    if ($text === '') {
        return ['ok' => false, 'codi' => 400, 'error' => 'Falta el text'];
    }
    if (mb_strlen($text) > 500) {
        return ['ok' => false, 'codi' => 400, 'error' => 'Text massa llarg (maxim 500 caracters)'];
    }

    $carpetaAudio = __DIR__ . '/audio';
    if (!is_dir($carpetaAudio) && !mkdir($carpetaAudio, 0755, true) && !is_dir($carpetaAudio)) {
        return ['ok' => false, 'codi' => 500, 'error' => 'No es pot crear la carpeta audio/'];
    }

    $nomFitxer = bin2hex(random_bytes(8)) . '.mp3';
    $rutaSortida = $carpetaAudio . '/' . $nomFitxer;
    $scriptPython = __DIR__ . '/tts.py';

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proces = proc_open([PYTHON_BIN, $scriptPython, $rutaSortida, VEU_TTS], $descriptors, $pipes);
    if (!is_resource($proces)) {
        return ['ok' => false, 'codi' => 500, 'error' => 'No s\'ha pogut iniciar Python (revisa PYTHON_BIN al .env)'];
    }

    fwrite($pipes[0], $text);
    fclose($pipes[0]);
    $sortidaError = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $codiSortida = proc_close($proces);

    if ($codiSortida !== 0 || !file_exists($rutaSortida)) {
        return ['ok' => false, 'codi' => 500, 'error' => 'Error generant audio: ' . trim($sortidaError)];
    }

    return ['ok' => true, 'ruta' => $rutaSortida, 'nomFitxer' => $nomFitxer];
}
