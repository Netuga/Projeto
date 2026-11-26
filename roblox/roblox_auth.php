<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

startSecureSession();
header('Cache-Control: no-store');

if (ROBLOX_CLIENT_ID === '' || ROBLOX_CLIENT_SECRET === '') {
    http_response_code(503);
    exit('Login Roblox ainda não está configurado.');
}

// Gerar state para proteger o login
$state = bin2hex(random_bytes(32));
$_SESSION['roblox_oauth_state'] = $state;

// Gerar PKCE
$verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$_SESSION['roblox_code_verifier'] = $verifier;

$challenge = rtrim(
    strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'),
    '='
);

$params = [
    'client_id' => ROBLOX_CLIENT_ID,
    'redirect_uri' => ROBLOX_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid profile',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256'
];

if (!persistSecureSession()) {
    http_response_code(500);
    exit('Não foi possível guardar a sessão de login.');
}

header(
    'Location: https://apis.roblox.com/oauth/v1/authorize?' .
    http_build_query($params)
);
exit;
