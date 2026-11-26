<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

startSecureSession();

function discordLoginFail(string $reason = 'discord_fail', ?bool $forceLinkMode = null): void
{
    $oauthMeta = $_SESSION['oauth_meta'] ?? null;
    $isLinkMode = $forceLinkMode ?? (
        is_array($oauthMeta)
        && (($oauthMeta['provider'] ?? '') === 'discord')
        && (($oauthMeta['mode'] ?? 'login') === 'link')
    );

    unset($_SESSION['oauth_state'], $_SESSION['oauth_meta']);

    if ($isLinkMode) {
        header('Location: ' . app_url('pages/profile.html?' . app_http_build_query([
            'discord_error' => $reason,
        ])));
        exit;
    }

    header('Location: ' . app_url('pages/login.html?error=' . rawurlencode($reason)));
    exit;
}

function discordProfileRedirect(array $params = []): void
{
    $query = $params ? ('?' . app_http_build_query($params)) : '';
    header('Location: ' . app_url('pages/profile.html' . $query));
    exit;
}

function discordHttpJson(string $url, string $method = 'GET', array $data = [], array $headers = []): array
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
            throw new RuntimeException($error !== '' ? $error : 'Falha na ligacao ao Discord.');
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

function discordColumnExists(mysqli $conn, string $table, string $column): bool
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

function buildDiscordUsername(mysqli $conn, string $displayName, string $discordUsername, string $email): string
{
    $seed = $displayName !== '' ? $displayName : ($discordUsername !== '' ? $discordUsername : strstr($email, '@', true));
    $username = preg_replace('/[^A-Za-z0-9_.-]/', '', str_replace(' ', '_', (string)$seed));
    $username = substr($username ?: 'player', 0, 24);
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

function ensureDiscordUserDefaults(mysqli $conn, int $userId, bool $incrementVisits = true): void
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

function discordAvatarUrl(string $discordId, string $avatarHash): ?string
{
    if ($discordId === '' || $avatarHash === '') {
        return null;
    }

    $extension = strpos($avatarHash, 'a_') === 0 ? 'gif' : 'png';
    return 'https://cdn.discordapp.com/avatars/' . rawurlencode($discordId) . '/' . rawurlencode($avatarHash) . '.' . $extension . '?size=256';
}

if (
    empty($_GET['code']) ||
    empty($_GET['state']) ||
    empty($_SESSION['oauth_state']) ||
    !hash_equals((string)$_SESSION['oauth_state'], (string)$_GET['state'])
) {
    discordLoginFail();
}

$oauthMeta = $_SESSION['oauth_meta'] ?? [];
$isLinkMode = is_array($oauthMeta)
    && (($oauthMeta['provider'] ?? '') === 'discord')
    && (($oauthMeta['mode'] ?? 'login') === 'link');
$linkUserId = $isLinkMode ? (int)($oauthMeta['user_id'] ?? 0) : 0;

unset($_SESSION['oauth_state'], $_SESSION['oauth_meta']);

try {
    $tokenData = discordHttpJson(DISCORD_API_BASE . '/oauth2/token', 'POST', [
        'client_id' => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => (string)$_GET['code'],
        'redirect_uri' => DISCORD_REDIRECT_URI,
    ]);

    $accessToken = trim((string)($tokenData['access_token'] ?? ''));
    if ($accessToken === '') {
        throw new RuntimeException('Token Discord invalido.');
    }

    $profile = discordHttpJson(
        DISCORD_API_BASE . '/users/@me',
        'GET',
        [],
        ['Authorization: Bearer ' . $accessToken]
    );

    $discordId = trim((string)($profile['id'] ?? ''));
    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $discordUsername = trim((string)($profile['username'] ?? ''));
    $discordGlobalName = trim((string)($profile['global_name'] ?? ''));
    $discordAvatarHash = trim((string)($profile['avatar'] ?? ''));
    $discordAvatarUrl = discordAvatarUrl($discordId, $discordAvatarHash);

    if ($discordId === '' || $discordUsername === '') {
        throw new RuntimeException('Perfil Discord incompleto.');
    }

    if (!$isLinkMode && $email === '') {
        throw new RuntimeException('O Discord nao devolveu um email valido.');
    }

    $supportsDiscordId = discordColumnExists($conn, 'users', 'discord_id');
    $supportsDiscordUsername = discordColumnExists($conn, 'users', 'discord_username');
    $supportsDiscordGlobalName = discordColumnExists($conn, 'users', 'discord_global_name');
    $supportsDiscordAvatar = discordColumnExists($conn, 'users', 'discord_avatar');
    $supportsPasswordSetupFlag = discordColumnExists($conn, 'users', 'needs_password_setup');

    if ($isLinkMode) {
        if (!$supportsDiscordId) {
            throw new RuntimeException('A base de dados nao suporta associacao Discord.');
        }

        $activeUserId = currentUserId();
        if ($linkUserId <= 0 || !$activeUserId || $activeUserId !== $linkUserId) {
            discordLoginFail('discord_link_session', true);
        }

        $userStmt = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $userStmt->bind_param('i', $linkUserId);
        $userStmt->execute();
        if (!$userStmt->get_result()->fetch_assoc()) {
            discordLoginFail('discord_link_session', true);
        }

        $ownerStmt = $conn->prepare('SELECT id FROM users WHERE discord_id = ? LIMIT 1');
        $ownerStmt->bind_param('s', $discordId);
        $ownerStmt->execute();
        $owner = $ownerStmt->get_result()->fetch_assoc();

        if ($owner && (int)$owner['id'] !== $linkUserId) {
            discordLoginFail('discord_already_linked', true);
        }

        $updates = ['discord_id = ?'];
        $updateTypes = 's';
        $updateParams = [$discordId];

        if ($supportsDiscordUsername) {
            $updates[] = 'discord_username = ?';
            $updateTypes .= 's';
            $updateParams[] = $discordUsername;
        }

        if ($supportsDiscordGlobalName) {
            $updates[] = 'discord_global_name = ?';
            $updateTypes .= 's';
            $updateParams[] = $discordGlobalName;
        }

        if ($supportsDiscordAvatar) {
            if ($discordAvatarUrl !== null) {
                $updates[] = 'discord_avatar = ?';
                $updateTypes .= 's';
                $updateParams[] = $discordAvatarUrl;
            } else {
                $updates[] = 'discord_avatar = NULL';
            }
        }

        $updateParams[] = $linkUserId;
        $updateTypes .= 'i';

        $update = $conn->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $update->bind_param($updateTypes, ...$updateParams);

        if (!$update->execute()) {
            throw new RuntimeException($update->error ?: 'Falha ao associar conta Discord.');
        }

        discordProfileRedirect(['discord' => 'linked']);
    }

    $selectFields = ['id', 'username', 'avatar', 'is_admin', 'email'];
    if ($supportsDiscordId) {
        $selectFields[] = 'discord_id';
    }

    if ($supportsDiscordId) {
        $stmt = $conn->prepare('SELECT ' . implode(', ', $selectFields) . ' FROM users WHERE discord_id = ? OR email = ? LIMIT 1');
        $stmt->bind_param('ss', $discordId, $email);
    } else {
        $stmt = $conn->prepare('SELECT ' . implode(', ', $selectFields) . ' FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $siteUsername = buildDiscordUsername($conn, $discordGlobalName, $discordUsername, $email);
        $passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        if (!$passwordHash) {
            throw new RuntimeException('Falha ao preparar password da conta Discord.');
        }

        $conn->begin_transaction();
        try {
            $insertColumns = ['username', 'email', 'password', 'avatar', 'last_login'];
            $insertValues = ['?', '?', '?', 'NULL', 'NOW()'];
            $insertTypes = 'sss';
            $insertParams = [$siteUsername, $email, $passwordHash];

            if ($supportsDiscordId) {
                $insertColumns[] = 'discord_id';
                $insertValues[] = '?';
                $insertTypes .= 's';
                $insertParams[] = $discordId;
            }

            if ($supportsDiscordUsername) {
                $insertColumns[] = 'discord_username';
                $insertValues[] = '?';
                $insertTypes .= 's';
                $insertParams[] = $discordUsername;
            }

            if ($supportsDiscordGlobalName) {
                $insertColumns[] = 'discord_global_name';
                $insertValues[] = '?';
                $insertTypes .= 's';
                $insertParams[] = $discordGlobalName;
            }

            if ($supportsDiscordAvatar) {
                $insertColumns[] = 'discord_avatar';
                if ($discordAvatarUrl !== null) {
                    $insertValues[] = '?';
                    $insertTypes .= 's';
                    $insertParams[] = $discordAvatarUrl;
                } else {
                    $insertValues[] = 'NULL';
                }
            }

            if ($supportsPasswordSetupFlag) {
                $insertColumns[] = 'needs_password_setup';
                $insertValues[] = '1';
            }

            $insert = $conn->prepare(
                'INSERT INTO users (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')'
            );
            $insert->bind_param($insertTypes, ...$insertParams);

            if (!$insert->execute()) {
                throw new RuntimeException($insert->error ?: 'Falha ao criar utilizador Discord.');
            }

            $userId = (int)$conn->insert_id;
            ensureDiscordUserDefaults($conn, $userId, false);

            $visitStmt = $conn->prepare('UPDATE user_stats SET total_visits = 1 WHERE user_id = ?');
            $visitStmt->bind_param('i', $userId);
            $visitStmt->execute();

            $conn->commit();

            $user = [
                'id' => $userId,
                'username' => $siteUsername,
                'avatar' => null,
                'is_admin' => 0,
                'email' => $email,
            ];
        } catch (Throwable $error) {
            $conn->rollback();
            throw $error;
        }
    } else {
        $updates = ['email = ?'];
        $updateTypes = 's';
        $updateParams = [$email];

        if ($supportsDiscordId) {
            $updates[] = 'discord_id = ?';
            $updateTypes .= 's';
            $updateParams[] = $discordId;
        }

        if ($supportsDiscordUsername) {
            $updates[] = 'discord_username = ?';
            $updateTypes .= 's';
            $updateParams[] = $discordUsername;
        }

        if ($supportsDiscordGlobalName) {
            $updates[] = 'discord_global_name = ?';
            $updateTypes .= 's';
            $updateParams[] = $discordGlobalName;
        }

        if ($supportsDiscordAvatar) {
            if ($discordAvatarUrl !== null) {
                $updates[] = 'discord_avatar = ?';
                $updateTypes .= 's';
                $updateParams[] = $discordAvatarUrl;
            } else {
                $updates[] = 'discord_avatar = NULL';
            }
        }

        $updates[] = 'last_login = NOW()';

        $updateParams[] = (int)$user['id'];
        $updateTypes .= 'i';

        $update = $conn->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $update->bind_param($updateTypes, ...$updateParams);
        $update->execute();

        ensureDiscordUserDefaults($conn, (int)$user['id'], true);
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
    error_log('Discord login error: ' . $error->getMessage());
    discordLoginFail('discord_fail', $isLinkMode);
}
