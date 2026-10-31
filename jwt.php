<?php
// Genera tokens d'acces (JWT HS256) per a LiveKit Cloud.

require_once __DIR__ . '/config.php';

function b64url(string $d): string
{
    return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
}

function creaTokenLiveKit(string $identity, string $nom, bool $canPublish, bool $canPublishData, bool $canSubscribe): array
{
    $ara = time();
    $exp = $ara + 3600;
    $payload = [
        'iss' => LIVEKIT_API_KEY,
        'sub' => $identity,
        'name' => $nom !== '' ? $nom : $identity,
        'nbf' => $ara,
        'exp' => $exp,
        'video' => [
            'room' => LIVEKIT_ROOM,
            'roomJoin' => true,
            'canPublish' => $canPublish,
            'canPublishData' => $canPublishData,
            'canSubscribe' => $canSubscribe,
        ],
    ];

    $cap = b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $cos = b64url(json_encode($payload));
    $sig = b64url(hash_hmac('sha256', "$cap.$cos", LIVEKIT_API_SECRET, true));

    return ['token' => "$cap.$cos.$sig", 'exp' => $exp];
}
