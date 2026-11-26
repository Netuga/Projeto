<?php
require_once __DIR__ . '/../includes/session.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'csrf') {
    $token = csrfToken();
    if (!persistSecureSession()) {
        http_response_code(500);
        exit;
    }
    echo json_encode(['success' => true, 'csrf_token' => $token], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

function jsonResponse(array $payload): void {
    global $conn;

    if (!persistSecureSession()) {
        error_log('Could not persist session before JSON response.');
        http_response_code(500);
        exit;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    exit;
}

function requirePostRequest(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Pedido inválido.']);
    }

    if (!isValidCsrfToken(requestCsrfToken())) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualiza a página e tenta novamente.']);
    }
}

function requireLoggedUser(): int {
    $uid = currentUserId();
    if (!$uid) {
        jsonResponse(['success' => false, 'message' => 'Não autenticado.']);
    }
    return $uid;
}

function columnExists(mysqli $conn, string $table, string $column): bool {
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

function normalizeEmail(string $email): string {
    return strtolower(trim($email));
}

function validateUsername(string $username): ?string {
    if ($username === '') {
        return 'Preenche o username.';
    }
    if (strlen($username) < 3) {
        return 'Username deve ter pelo menos 3 caracteres.';
    }
    if (strlen($username) > 30) {
        return 'Username não pode ter mais de 30 caracteres.';
    }
    if (!preg_match('/\A[A-Za-z0-9_.-]+\z/', $username)) {
        return 'Username só pode usar letras, números, ponto, hífen e underscore.';
    }
    return null;
}

function validatePasswordInput(string $password, string $label = 'Password'): ?string {
    if ($password === '') {
        return 'Preenche a password.';
    }
    if (strlen($password) < 8) {
        return $label . ' deve ter pelo menos 8 caracteres.';
    }
    if (strlen($password) > 72) {
        return $label . ' não pode ter mais de 72 caracteres.';
    }
    return null;
}

function validateAvatar(string $avatar): ?string {
    if (strlen($avatar) > 32 || preg_match('/[<>&"\']/', $avatar)) {
        return 'Avatar inválido.';
    }
    return null;
}

function userValueExists(mysqli $conn, string $column, string $value, int $ignoreUserId = 0): bool {
    if (!in_array($column, ['username', 'email'], true)) {
        return false;
    }

    if ($ignoreUserId > 0) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE `$column` = ? AND id <> ? LIMIT 1");
        $stmt->bind_param('si', $value, $ignoreUserId);
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE `$column` = ? LIMIT 1");
        $stmt->bind_param('s', $value);
    }

    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function duplicateIdentityMessage(mysqli $conn, string $username, string $email, int $ignoreUserId = 0): ?string {
    $usernameTaken = userValueExists($conn, 'username', $username, $ignoreUserId);
    $emailTaken = userValueExists($conn, 'email', $email, $ignoreUserId);

    if ($usernameTaken && $emailTaken) {
        return 'Esse username e esse email já existem.';
    }
    if ($usernameTaken) {
        return 'Esse username já existe.';
    }
    if ($emailTaken) {
        return 'Esse email já existe.';
    }
    return null;
}

function clientIp(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function loginAttemptsTableReady(mysqli $conn): bool {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    $ready = $result && $result->num_rows > 0;
    return $ready;
}

function loginAttemptHash(string $identifier): string {
    return hash('sha256', strtolower(trim($identifier)));
}

function assertLoginAllowed(mysqli $conn, string $identifier): void {
    if (!loginAttemptsTableReady($conn)) {
        return;
    }

    $hash = loginAttemptHash($identifier);
    $ip = clientIp();
    $stmt = $conn->prepare('
        SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) AS seconds_left
        FROM login_attempts
        WHERE identifier_hash = ? AND ip_address = ?
        LIMIT 1
    ');
    $stmt->bind_param('ss', $hash, $ip);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $secondsLeft = (int)($row['seconds_left'] ?? 0);

    if ($secondsLeft > 0) {
        $minutes = max(1, (int)ceil($secondsLeft / 60));
        jsonResponse([
            'success' => false,
            'message' => 'Demasiadas tentativas falhadas. Tenta novamente daqui a ' . $minutes . ' min.',
        ]);
    }
}

function recordFailedLogin(mysqli $conn, string $identifier): void {
    if (!loginAttemptsTableReady($conn)) {
        return;
    }

    $hash = loginAttemptHash($identifier);
    $ip = clientIp();
    $stmt = $conn->prepare('
        SELECT attempts, TIMESTAMPDIFF(SECOND, last_attempt, NOW()) AS age_seconds
        FROM login_attempts
        WHERE identifier_hash = ? AND ip_address = ?
        LIMIT 1
    ');
    $stmt->bind_param('ss', $hash, $ip);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $attempts = $row && (int)$row['age_seconds'] <= 900 ? (int)$row['attempts'] + 1 : 1;

    if ($row) {
        if ($attempts >= 5) {
            $upd = $conn->prepare('
                UPDATE login_attempts
                SET attempts = ?, last_attempt = NOW(), blocked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                WHERE identifier_hash = ? AND ip_address = ?
            ');
            $upd->bind_param('iss', $attempts, $hash, $ip);
        } else {
            $upd = $conn->prepare('
                UPDATE login_attempts
                SET attempts = ?, last_attempt = NOW(), blocked_until = NULL
                WHERE identifier_hash = ? AND ip_address = ?
            ');
            $upd->bind_param('iss', $attempts, $hash, $ip);
        }
        $upd->execute();
        return;
    }

    $ins = $conn->prepare('
        INSERT INTO login_attempts (identifier_hash, ip_address, attempts, last_attempt)
        VALUES (?, ?, ?, NOW())
    ');
    $ins->bind_param('ssi', $hash, $ip, $attempts);
    $ins->execute();
}

function clearLoginAttempts(mysqli $conn, string $identifier): void {
    if (!loginAttemptsTableReady($conn)) {
        return;
    }

    $hash = loginAttemptHash($identifier);
    $ip = clientIp();
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier_hash = ? AND ip_address = ?');
    $stmt->bind_param('ss', $hash, $ip);
    $stmt->execute();
}

function ensureUserDefaults(mysqli $conn, int $userId): void {
    $statsStmt = $conn->prepare('INSERT IGNORE INTO user_stats (user_id) VALUES (?)');
    $statsStmt->bind_param('i', $userId);
    $statsStmt->execute();
}

function recordSuccessfulLogin(mysqli $conn, int $userId): void {
    ensureUserDefaults($conn, $userId);

    $upd = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $upd->bind_param('i', $userId);
    $upd->execute();

    $vis = $conn->prepare('UPDATE user_stats SET total_visits = total_visits + 1 WHERE user_id = ?');
    $vis->bind_param('i', $userId);
    $vis->execute();
}

function publicUser(array $user): array {
    return [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'avatar' => $user['avatar'],
        'is_admin' => (int)($user['is_admin'] ?? 0),
        'needs_password_setup' => (bool)($user['needs_password_setup'] ?? false),
    ];
}

function loadSessionPayload(mysqli $conn, int $uid): array {
    $needsPasswordSelect = columnExists($conn, 'users', 'needs_password_setup')
        ? 'u.needs_password_setup'
        : '0 AS needs_password_setup';
    $googleIdSelect = columnExists($conn, 'users', 'google_id')
        ? 'u.google_id'
        : 'NULL AS google_id';
    $discordIdSelect = columnExists($conn, 'users', 'discord_id')
        ? 'u.discord_id'
        : 'NULL AS discord_id';
    $discordUsernameSelect = columnExists($conn, 'users', 'discord_username')
        ? 'u.discord_username'
        : 'NULL AS discord_username';
    $discordGlobalNameSelect = columnExists($conn, 'users', 'discord_global_name')
        ? 'u.discord_global_name'
        : 'NULL AS discord_global_name';
    $discordAvatarSelect = columnExists($conn, 'users', 'discord_avatar')
        ? 'u.discord_avatar'
        : 'NULL AS discord_avatar';

    $stmt = $conn->prepare('
        SELECT u.id, u.username, u.avatar, u.email, u.created_at, u.is_admin,
               ' . $needsPasswordSelect . ',
               ' . $googleIdSelect . ',
               ' . $discordIdSelect . ',
               ' . $discordUsernameSelect . ',
               ' . $discordGlobalNameSelect . ',
               ' . $discordAvatarSelect . ',
               s.pvp_rank, s.pvp_points, s.total_visits, s.favorite_unit
        FROM users u
        LEFT JOIN user_stats s ON s.user_id = u.id
        WHERE u.id = ?
    ');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        clearAuthenticatedSession();
        return ['logged' => false];
    }

    ensureUserDefaults($conn, $uid);

    return ['logged' => true, 'user' => $user, 'csrf_token' => csrfToken()];
}

switch ($action) {
    case 'register':
        requirePostRequest();

        $username = trim($_POST['username'] ?? '');
        $email = normalizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            jsonResponse(['success' => false, 'message' => 'Preenche todos os campos.']);
        }

        $usernameError = validateUsername($username);
        if ($usernameError) {
            jsonResponse(['success' => false, 'message' => $usernameError]);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Email inválido.']);
        }
        $passwordError = validatePasswordInput($password);
        if ($passwordError) {
            jsonResponse(['success' => false, 'message' => $passwordError]);
        }

        $duplicateMessage = duplicateIdentityMessage($conn, $username, $email);
        if ($duplicateMessage) {
            jsonResponse(['success' => false, 'message' => $duplicateMessage]);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!$hash) {
            jsonResponse(['success' => false, 'message' => 'Erro ao preparar a password.']);
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO users (username, email, password, last_login) VALUES (?, ?, ?, NOW())');
            $stmt->bind_param('sss', $username, $email, $hash);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error, $stmt->errno);
            }
            $userId = (int)$conn->insert_id;

            $statsStmt = $conn->prepare('INSERT INTO user_stats (user_id, total_visits) VALUES (?, 1)');
            $statsStmt->bind_param('i', $userId);
            if (!$statsStmt->execute()) {
                throw new RuntimeException($statsStmt->error, $statsStmt->errno);
            }

            $conn->commit();
        } catch (Throwable $error) {
            $conn->rollback();
            if ((int)$error->getCode() === 1062) {
                jsonResponse(['success' => false, 'message' => 'Esse username ou email já existe.']);
            }
            jsonResponse(['success' => false, 'message' => 'Erro ao criar conta.']);
        }

        $user = [
            'id' => $userId,
            'username' => $username,
            'avatar' => null,
            'is_admin' => 0,
        ];
        try {
            setAuthenticatedUser($user);
        } catch (Throwable $error) {
            error_log('Registration session start failed: ' . $error->getMessage());
            jsonResponse(['success' => false, 'message' => 'A conta foi criada, mas não foi possível iniciar a sessão. Tenta entrar novamente.']);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Conta criada e login efetuado!',
            'user' => publicUser($user),
        ]);

    case 'login':
        requirePostRequest();

        $identifier = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identifier === '' || $password === '') {
            jsonResponse(['success' => false, 'message' => 'Preenche todos os campos.']);
        }

        assertLoginAllowed($conn, $identifier);

        $stmt = $conn->prepare('SELECT id, username, email, password, avatar, is_admin FROM users WHERE username = ? OR email = ? LIMIT 1');
        $identifierEmail = normalizeEmail($identifier);
        $stmt->bind_param('ss', $identifier, $identifierEmail);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            recordFailedLogin($conn, $identifier);
            jsonResponse(['success' => false, 'message' => 'Email/username ou password incorretos.']);
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if ($newHash) {
                $rehash = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $rehash->bind_param('si', $newHash, $user['id']);
                $rehash->execute();
            }
        }

        clearLoginAttempts($conn, $identifier);
        recordSuccessfulLogin($conn, (int)$user['id']);
        try {
            setAuthenticatedUser($user);
        } catch (Throwable $error) {
            error_log('Login session start failed: ' . $error->getMessage());
            jsonResponse(['success' => false, 'message' => 'Credenciais aceites, mas não foi possível iniciar a sessão. Tenta novamente.']);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Login efetuado!',
            'user' => publicUser($user),
        ]);

    case 'logout':
        requirePostRequest();
        clearAuthenticatedSession();
        jsonResponse(['success' => true]);

    case 'session':
        $uid = currentUserId();
        if (!$uid) {
            jsonResponse(['logged' => false, 'csrf_token' => csrfToken()]);
        }
        jsonResponse(loadSessionPayload($conn, $uid));

    case 'update_profile':
        requirePostRequest();
        $uid = requireLoggedUser();
        $fields = [];
        $params = [];
        $types = '';

        if (array_key_exists('username', $_POST)) {
            $newUsername = trim($_POST['username']);
            $usernameError = validateUsername($newUsername);
            if ($usernameError) {
                jsonResponse(['success' => false, 'message' => $usernameError]);
            }
            if (userValueExists($conn, 'username', $newUsername, $uid)) {
                jsonResponse(['success' => false, 'message' => 'Esse username já existe.']);
            }
            $fields[] = 'username = ?';
            $params[] = $newUsername;
            $types .= 's';
        }

        if (array_key_exists('email', $_POST)) {
            $newEmail = normalizeEmail($_POST['email']);
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'message' => 'Email inválido.']);
            }
            if (userValueExists($conn, 'email', $newEmail, $uid)) {
                jsonResponse(['success' => false, 'message' => 'Esse email já existe.']);
            }
            $fields[] = 'email = ?';
            $params[] = $newEmail;
            $types .= 's';
        }

        if (array_key_exists('avatar', $_POST)) {
            $avatar = trim($_POST['avatar']);
            $avatarError = validateAvatar($avatar);
            if ($avatarError) {
                jsonResponse(['success' => false, 'message' => $avatarError]);
            }
            $fields[] = 'avatar = ?';
            $params[] = $avatar;
            $types .= 's';
        }

        if (!$fields) {
            jsonResponse(['success' => false, 'message' => 'Nada para atualizar.']);
        }

        $params[] = $uid;
        $types .= 'i';
        $stmt = $conn->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            if (isset($newUsername)) {
                $_SESSION['username'] = $newUsername;
            }
            if (isset($avatar)) {
                $_SESSION['avatar'] = $avatar;
            }
            jsonResponse(['success' => true, 'message' => 'Perfil atualizado!']);
        }

        jsonResponse(['success' => false, 'message' => $conn->errno === 1062 ? 'Username ou email já em uso.' : 'Erro ao atualizar.']);

    case 'change_password':
        requirePostRequest();
        $uid = requireLoggedUser();
        $oldPw = $_POST['old_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $supportsPasswordSetupFlag = columnExists($conn, 'users', 'needs_password_setup');

        if ($newPw === '') {
            jsonResponse(['success' => false, 'message' => 'Preenche todos os campos.']);
        }
        $passwordError = validatePasswordInput($newPw, 'A nova password');
        if ($passwordError) {
            jsonResponse(['success' => false, 'message' => $passwordError]);
        }

        $stmt = $conn->prepare(
            'SELECT password' . ($supportsPasswordSetupFlag ? ', needs_password_setup' : '') . ' FROM users WHERE id = ?'
        );
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $needsPasswordSetup = $supportsPasswordSetupFlag && (bool)($row['needs_password_setup'] ?? false);

        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'Utilizador não encontrado.']);
        }

        if (!$needsPasswordSetup && $oldPw === '') {
            jsonResponse(['success' => false, 'message' => 'Preenche todos os campos.']);
        }

        if (!$needsPasswordSetup && !password_verify($oldPw, $row['password'])) {
            jsonResponse(['success' => false, 'message' => 'Password atual incorreta.']);
        }

        $hash = password_hash($newPw, PASSWORD_DEFAULT);
        if (!$hash) {
            jsonResponse(['success' => false, 'message' => 'Erro ao preparar a password.']);
        }

        if ($supportsPasswordSetupFlag) {
            $upd = $conn->prepare('UPDATE users SET password = ?, needs_password_setup = 0 WHERE id = ?');
        } else {
            $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        }
        $upd->bind_param('si', $hash, $uid);
        $upd->execute();
        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
        jsonResponse([
            'success' => true,
            'message' => $needsPasswordSetup ? 'Password criada!' : 'Password alterada!',
            'needs_password_setup' => false,
        ]);

    case 'unlink_discord':
        requirePostRequest();
        $uid = requireLoggedUser();

        $supportsDiscordId = columnExists($conn, 'users', 'discord_id');
        if (!$supportsDiscordId) {
            jsonResponse(['success' => false, 'message' => 'Associacao Discord indisponivel nesta base de dados.']);
        }

        $supportsDiscordUsername = columnExists($conn, 'users', 'discord_username');
        $supportsDiscordGlobalName = columnExists($conn, 'users', 'discord_global_name');
        $supportsDiscordAvatar = columnExists($conn, 'users', 'discord_avatar');
        $supportsPasswordSetupFlag = columnExists($conn, 'users', 'needs_password_setup');
        $supportsGoogleId = columnExists($conn, 'users', 'google_id');

        $selectFields = ['discord_id'];
        $selectFields[] = $supportsPasswordSetupFlag ? 'needs_password_setup' : '0 AS needs_password_setup';
        $selectFields[] = $supportsGoogleId ? 'google_id' : 'NULL AS google_id';

        $stmt = $conn->prepare('SELECT ' . implode(', ', $selectFields) . ' FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'Utilizador não encontrado.']);
        }

        if (trim((string)($row['discord_id'] ?? '')) === '') {
            jsonResponse(['success' => false, 'message' => 'Esta conta não tem Discord associado.']);
        }

        $hasGoogleLogin = trim((string)($row['google_id'] ?? '')) !== '';
        $needsPasswordSetup = (bool)($row['needs_password_setup'] ?? false);

        if (!$hasGoogleLogin && $needsPasswordSetup) {
            jsonResponse([
                'success' => false,
                'message' => 'Define primeiro uma password antes de removeres o Discord desta conta.',
            ]);
        }

        $updates = ['discord_id = NULL'];
        if ($supportsDiscordUsername) {
            $updates[] = 'discord_username = NULL';
        }
        if ($supportsDiscordGlobalName) {
            $updates[] = 'discord_global_name = NULL';
        }
        if ($supportsDiscordAvatar) {
            $updates[] = 'discord_avatar = NULL';
        }

        $update = $conn->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $update->bind_param('i', $uid);

        if (!$update->execute()) {
            jsonResponse(['success' => false, 'message' => 'Não foi possível remover a associação Discord.']);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Conta Discord removida.',
        ]);

    case 'delete_account':
        requirePostRequest();
        $uid = requireLoggedUser();
        $pw = $_POST['password'] ?? '';

        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($pw, $row['password'])) {
            jsonResponse(['success' => false, 'message' => 'Password incorreta.']);
        }

        $del = $conn->prepare('DELETE FROM users WHERE id = ?');
        $del->bind_param('i', $uid);
        $del->execute();
        clearAuthenticatedSession();
        jsonResponse(['success' => true]);

    case 'toggle_favorite':
        requirePostRequest();
        $uid = requireLoggedUser();
        $unitId = (int)($_POST['unit_id'] ?? 0);
        if (!$unitId) {
            jsonResponse(['success' => false, 'message' => 'ID inválido.']);
        }

        ensureUserDefaults($conn, $uid);
        $cur = $conn->prepare('SELECT favorite_unit FROM user_stats WHERE user_id = ?');
        $cur->bind_param('i', $uid);
        $cur->execute();
        $curRow = $cur->get_result()->fetch_assoc();
        $isFav = ((int)($curRow['favorite_unit'] ?? 0) === $unitId);

        if ($isFav) {
            $upd = $conn->prepare('UPDATE user_stats SET favorite_unit = NULL WHERE user_id = ?');
            $upd->bind_param('i', $uid);
        } else {
            $upd = $conn->prepare('UPDATE user_stats SET favorite_unit = ? WHERE user_id = ?');
            $upd->bind_param('ii', $unitId, $uid);
        }
        $upd->execute();

        jsonResponse([
            'success' => true,
            'favorited' => !$isFav,
            'message' => $isFav ? 'Removido dos favoritos.' : 'Definido como favorita!',
        ]);

    case 'is_favorite':
        $uid = currentUserId();
        if (!$uid) {
            jsonResponse(['favorited' => false]);
        }
        $unitId = (int)($_GET['unit_id'] ?? 0);
        $cur = $conn->prepare('SELECT favorite_unit FROM user_stats WHERE user_id = ?');
        $cur->bind_param('i', $uid);
        $cur->execute();
        $curRow = $cur->get_result()->fetch_assoc();
        jsonResponse(['favorited' => ((int)($curRow['favorite_unit'] ?? 0) === $unitId)]);

    case 'get_favorites':
        $uid = currentUserId();
        if (!$uid) {
            jsonResponse([]);
        }

        $stmt = $conn->prepare('
            SELECT u.id, u.name, u.emoji, u.img, u.bg,
                   CASE WHEN u.rarity = "common" THEN "rare" ELSE u.rarity END AS rarity
            FROM user_stats s
            INNER JOIN units u ON u.id = s.favorite_unit
            WHERE s.user_id = ?
            LIMIT 1
        ');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        jsonResponse($row ? [$row] : []);

    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.']);
}
