<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function discordAuthResponse(array $payload): void
{
    if (!persistSecureSession()) {
        http_response_code(500);
        exit;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (DISCORD_CLIENT_ID === '' || DISCORD_CLIENT_SECRET === '') {
    discordAuthResponse([
        'success' => false,
        'message' => 'Configuracao Discord em falta.',
    ]);
}

$mode = ($_GET['mode'] ?? '') === 'link' ? 'link' : 'login';
$userId = currentUserId();

if ($mode === 'link' && !$userId) {
    discordAuthResponse([
        'success' => false,
        'message' => 'Precisas de iniciar sessao antes de associares o Discord.',
    ]);
}

$state = bin2hex(random_bytes(16)) . ':discord';
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_meta'] = [
    'provider' => 'discord',
    'mode' => $mode,
    'user_id' => $mode === 'link' ? (int)$userId : 0,
];

$params = [
    'client_id' => DISCORD_CLIENT_ID,
    'redirect_uri' => DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'identify email',
    'state' => $state,
    'prompt' => 'consent',
];

discordAuthResponse([
    'success' => true,
    'url' => 'https://discord.com/oauth2/authorize?' . app_http_build_query($params),
    'redirect_uri' => DISCORD_REDIRECT_URI,
    'mode' => $mode,
]);
