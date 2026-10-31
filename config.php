<?php
// Carrega les variables del fitxer .env (sense dependencies externes)

function carregaEnv(string $fitxer): array
{
    $env = [];
    if (!is_readable($fitxer)) {
        return $env;
    }
    foreach (file($fitxer, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linia) {
        $linia = trim($linia);
        if ($linia === '' || $linia[0] === '#' || strpos($linia, '=') === false) {
            continue;
        }
        [$clau, $valor] = explode('=', $linia, 2);
        $env[trim($clau)] = trim(trim($valor), "\"'");
    }
    return $env;
}

$ENV = carregaEnv(__DIR__ . '/.env');

define('LIVEKIT_URL', $ENV['LIVEKIT_URL'] ?? '');
define('LIVEKIT_API_KEY', $ENV['LIVEKIT_API_KEY'] ?? '');
define('LIVEKIT_API_SECRET', $ENV['LIVEKIT_API_SECRET'] ?? '');
define('LIVEKIT_ROOM', $ENV['LIVEKIT_ROOM'] ?? 'agent-veu');
define('PYTHON_BIN', $ENV['PYTHON_BIN'] ?? 'python');
define('VEU_TTS', $ENV['VEU_TTS'] ?? 'ca-ES-JoanaNeural');
