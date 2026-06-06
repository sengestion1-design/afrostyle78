<?php
function verifyTurnstile(string $token): bool {
    if (empty($token)) return false;
    $response = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => TURNSTILE_SECRET_KEY,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
        ],
    ]));
    if (!$response) return false;
    $data = json_decode($response, true);
    return !empty($data['success']);
}
