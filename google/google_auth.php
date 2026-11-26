<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    persistSecureSession();
    echo json_encode([
        'success' => false,
        'message' => 'Configuracao Google em falta.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$state = bin2hex(random_bytes(16)) . ':google';
$_SESSION['oauth_state'] = $state;
if (!persistSecureSession()) {
    http_response_code(500);
    exit;
}

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account',
];

echo json_encode([
    'success' => true,
    'url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . app_http_build_query($params),
    'redirect_uri' => GOOGLE_REDIRECT_URI,
], JSON_UNESCAPED_UNICODE);
