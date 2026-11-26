<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

startSecureSession();

function googleLoginFail(): void
{
    header('Location: ' . app_url('pages/login.html?error=google_fail'));
    exit;
}

function googleHttpJson(string $url, string $method = 'GET', array $data = [], array $headers = []): array
{
    $method = strtoupper($method);
    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        $requestHeaders = $headers;

        if ($method === 'GET' && $data) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . app_http_build_query($data);
        } elseif ($data) {
            $requestHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, app_http_build_query($data));
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $requestHeaders,
        ]);

        $responseBody = (string)curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($responseBody === '' && curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error !== '' ? $error : 'Falha na ligacao ao servico OAuth.');
        }

        curl_close($ch);
    } else {
        if ($method === 'GET' && $data) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . app_http_build_query($data);
        }

        $requestHeaders = $headers;
        $content = '';
        if ($method !== 'GET' && $data) {
            $requestHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            $content = app_http_build_query($data);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'timeout' => 20,
                'header' => implode("\r\n", $requestHeaders),
                'content' => $content,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $responseBody = $responseBody === false ? '' : $responseBody;
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $match)) {
            $statusCode = (int)$match[1];
        }
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $decoded['_http_status'] = $statusCode;
    $decoded['_raw_body'] = $responseBody;

    return $decoded;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ');
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function buildGoogleUsername(mysqli $conn, string $displayName, string $email): string
{
    $seed = $displayName !== '' ? $displayName : strstr($email, '@', true);
    $username = preg_replace('/[^A-Za-z0-9_.-]/', '', str_replace(' ', '_', (string)$seed));
    $username = substr($username ?: 'user', 0, 24);
    $base = $username;
    $suffix = 1;

    while (true) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            return $username;
        }

        $username = substr($base, 0, max(1, 24 - strlen((string)$suffix))) . $suffix;
        $suffix++;
    }
}

function ensureUserDefaults(mysqli $conn, int $userId, bool $incrementVisits = true): void
{
    $statsStmt = $conn->prepare('INSERT IGNORE INTO user_stats (user_id) VALUES (?)');
    $statsStmt->bind_param('i', $userId);
    $statsStmt->execute();

    if ($incrementVisits) {
        $visitsStmt = $conn->prepare('UPDATE user_stats SET total_visits = total_visits + 1 WHERE user_id = ?');
        $visitsStmt->bind_param('i', $userId);
        $visitsStmt->execute();
    }
}

if (
    empty($_GET['code']) ||
    empty($_GET['state']) ||
    empty($_SESSION['oauth_state']) ||
    !hash_equals((string)$_SESSION['oauth_state'], (string)$_GET['state'])
) {
    googleLoginFail();
}

unset($_SESSION['oauth_state']);

try {
    $tokenData = googleHttpJson('https://oauth2.googleapis.com/token', 'POST', [
        'code' => (string)$_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]);

    $accessToken = trim((string)($tokenData['access_token'] ?? ''));
    if ($accessToken === '') {
        throw new RuntimeException('Token Google invalido.');
    }

    $profile = googleHttpJson(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        'GET',
        [],
        ['Authorization: Bearer ' . $accessToken]
    );

    $googleId = trim((string)($profile['id'] ?? ''));
    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $name = trim((string)($profile['name'] ?? ''));

    if ($googleId === '' || $email === '') {
        throw new RuntimeException('Perfil Google incompleto.');
    }

    $supportsGoogleId = columnExists($conn, 'users', 'google_id');
    $supportsPasswordSetupFlag = columnExists($conn, 'users', 'needs_password_setup');

    if ($supportsGoogleId) {
        $stmt = $conn->prepare('SELECT id, username, avatar, is_admin, email, google_id FROM users WHERE google_id = ? OR email = ? LIMIT 1');
        $stmt->bind_param('ss', $googleId, $email);
    } else {
        $stmt = $conn->prepare('SELECT id, username, avatar, is_admin, email FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $username = buildGoogleUsername($conn, $name, $email);
        $passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        if (!$passwordHash) {
            throw new RuntimeException('Falha ao preparar password de conta Google.');
        }

        $conn->begin_transaction();
        try {
            if ($supportsGoogleId) {
                if ($supportsPasswordSetupFlag) {
                    $insert = $conn->prepare('
                        INSERT INTO users (username, email, password, avatar, google_id, needs_password_setup, last_login)
                        VALUES (?, ?, ?, NULL, ?, 1, NOW())
                    ');
                    $insert->bind_param('ssss', $username, $email, $passwordHash, $googleId);
                } else {
                    $insert = $conn->prepare('
                        INSERT INTO users (username, email, password, avatar, google_id, last_login)
                        VALUES (?, ?, ?, NULL, ?, NOW())
                    ');
                    $insert->bind_param('ssss', $username, $email, $passwordHash, $googleId);
                }
            } else {
                if ($supportsPasswordSetupFlag) {
                    $insert = $conn->prepare('
                        INSERT INTO users (username, email, password, avatar, needs_password_setup, last_login)
                        VALUES (?, ?, ?, NULL, 1, NOW())
                    ');
                    $insert->bind_param('sss', $username, $email, $passwordHash);
                } else {
                    $insert = $conn->prepare('
                        INSERT INTO users (username, email, password, avatar, last_login)
                        VALUES (?, ?, ?, NULL, NOW())
                    ');
                    $insert->bind_param('sss', $username, $email, $passwordHash);
                }
            }

            if (!$insert->execute()) {
                throw new RuntimeException($insert->error ?: 'Falha ao criar utilizador Google.');
            }

            $userId = (int)$conn->insert_id;
            ensureUserDefaults($conn, $userId, false);

            $visitStmt = $conn->prepare('UPDATE user_stats SET total_visits = 1 WHERE user_id = ?');
            $visitStmt->bind_param('i', $userId);
            $visitStmt->execute();

            $conn->commit();

            $user = [
                'id' => $userId,
                'username' => $username,
                'avatar' => null,
                'is_admin' => 0,
                'email' => $email,
                'needs_password_setup' => $supportsPasswordSetupFlag ? 1 : 0,
            ];
        } catch (Throwable $error) {
            $conn->rollback();
            throw $error;
        }
    } else {
        if ($supportsGoogleId) {
            $update = $conn->prepare('
                UPDATE users
                SET google_id = ?, email = ?, last_login = NOW()
                WHERE id = ?
            ');
            $update->bind_param('ssi', $googleId, $email, $user['id']);
        } else {
            $update = $conn->prepare('
                UPDATE users
                SET email = ?, last_login = NOW()
                WHERE id = ?
            ');
            $update->bind_param('si', $email, $user['id']);
        }
        $update->execute();
        ensureUserDefaults($conn, (int)$user['id'], true);
    }

    setAuthenticatedUser([
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'avatar' => $user['avatar'] ?? null,
        'is_admin' => (int)($user['is_admin'] ?? 0),
    ]);

    header('Location: ' . app_url('pages/home.html'));
    exit;
} catch (Throwable $error) {
    error_log('Google login error: ' . $error->getMessage());
    googleLoginFail();
}
