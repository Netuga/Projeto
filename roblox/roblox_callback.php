<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
startSecureSession();

function robloxLoginFail(string $message = 'Login Roblox falhou. Tenta novamente.'): void
{
    error_log('Roblox OAuth: ' . $message);
    header('Location: ' . app_url('pages/login.html?error=roblox_fail'));
    exit;
}

function fail($message) {
    robloxLoginFail($message);
}

if (ROBLOX_CLIENT_ID === '' || ROBLOX_CLIENT_SECRET === '') {
    robloxLoginFail('Login Roblox ainda não está configurado no servidor.');
}

// Verificar se a Roblox devolveu um erro
if (isset($_GET['error'])) {
    fail('O login Roblox foi cancelado ou falhou.');
}

// Validar state
if (
    empty($_GET['state']) ||
    empty($_SESSION['roblox_oauth_state']) ||
    !hash_equals($_SESSION['roblox_oauth_state'], $_GET['state'])
) {
    fail('Estado OAuth inválido. Tenta iniciar sessão novamente.');
}

unset($_SESSION['roblox_oauth_state']);

if (empty($_GET['code']) || empty($_SESSION['roblox_code_verifier'])) {
    fail('Não foi recebido o código de autenticação.');
}

$code = $_GET['code'];
$verifier = $_SESSION['roblox_code_verifier'];
unset($_SESSION['roblox_code_verifier']);

// Trocar o código por um token
$ch = curl_init('https://apis.roblox.com/oauth/v1/token');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => ROBLOX_CLIENT_ID,
        'client_secret' => ROBLOX_CLIENT_SECRET,
        'redirect_uri' => ROBLOX_REDIRECT_URI,
        'code_verifier' => $verifier
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $status < 200 || $status >= 300) {
    fail('Não foi possível validar o login com a Roblox.');
}

$tokens = json_decode($response, true);

if (empty($tokens['access_token'])) {
    fail('A Roblox não devolveu um token válido.');
}

// Obter os dados do utilizador autenticado
$ch = curl_init('https://apis.roblox.com/oauth/v1/userinfo');

curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $tokens['access_token']
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15
]);

$userResponse = curl_exec($ch);
$userStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userResponse === false || $userStatus < 200 || $userStatus >= 300) {
    fail('Não foi possível obter o perfil Roblox.');
}

$user = json_decode($userResponse, true);

if (empty($user['sub']) || !ctype_digit((string)$user['sub'])) {
    fail('Não foi possível identificar a conta Roblox.');
}

require_once __DIR__ . '/../includes/db.php';

$column = $conn->query("SHOW COLUMNS FROM users LIKE 'roblox_id'");
if (!$column || $column->num_rows === 0) {
    robloxLoginFail('A base de dados ainda não tem suporte Roblox. Executa admin/setup_tables.php.');
}

$robloxId = (string)$user['sub'];
$stmt = $conn->prepare('SELECT id, username, avatar, is_admin FROM users WHERE roblox_id = ? LIMIT 1');
$stmt->bind_param('s', $robloxId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

if (!$account) {
    $seed = (string)($user['preferred_username'] ?? $user['nickname'] ?? 'RobloxPlayer');
    $base = preg_replace('/[^A-Za-z0-9_.-]/', '', str_replace(' ', '_', $seed)) ?: 'RobloxPlayer';
    $base = substr($base, 0, 24);
    $username = $base;
    for ($suffix = 1; ; $suffix++) {
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $check->bind_param('s', $username);
        $check->execute();
        if ($check->get_result()->num_rows === 0) break;
        $tail = (string)$suffix;
        $username = substr($base, 0, 30 - strlen($tail)) . $tail;
    }

    $email = 'roblox-' . $robloxId . '@users.invalid';
    $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    try {
        $conn->begin_transaction();
        $insert = $conn->prepare('INSERT INTO users (username, email, password, roblox_id, last_login) VALUES (?, ?, ?, ?, NOW())');
        $insert->bind_param('ssss', $username, $email, $password, $robloxId);
        if (!$insert->execute()) throw new RuntimeException('Falha ao criar conta Roblox.');
        $accountId = (int)$conn->insert_id;
        $stats = $conn->prepare('INSERT IGNORE INTO user_stats (user_id, total_visits) VALUES (?, 1)');
        $stats->bind_param('i', $accountId);
        $stats->execute();
        $conn->commit();
        $account = ['id' => $accountId, 'username' => $username, 'avatar' => null, 'is_admin' => 0];
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Roblox account creation failed: ' . $error->getMessage());
        robloxLoginFail('Não foi possível criar a conta do site para este utilizador Roblox.');
    }
} else {
    $update = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $update->bind_param('i', $account['id']);
    $update->execute();
    $stats = $conn->prepare('INSERT IGNORE INTO user_stats (user_id) VALUES (?)');
    $stats->bind_param('i', $account['id']);
    $stats->execute();
    $visit = $conn->prepare('UPDATE user_stats SET total_visits = total_visits + 1 WHERE user_id = ?');
    $visit->bind_param('i', $account['id']);
    $visit->execute();
}

try {
    setAuthenticatedUser($account);
} catch (Throwable $error) {
    error_log('Roblox login session start failed: ' . $error->getMessage());
    robloxLoginFail('Não foi possível iniciar a sessão do site. Tenta novamente.');
}
header('Location: ' . app_url('pages/home.html'));
exit;
