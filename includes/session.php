<?php

require_once __DIR__ . '/../config.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_DURATION,
        'path' => app_base_path(),
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Não foi possível iniciar a sessão.');
    }
}

function persistSecureSession(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }

    return session_write_close();
}

function csrfToken(): string
{
    $token = $_SESSION['csrf_token'] ?? '';
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }

    return $token;
}

function requestCsrfToken(): string
{
    $headerToken = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($headerToken !== '') {
        return $headerToken;
    }

    return trim((string)($_POST['csrf_token'] ?? ''));
}

function isValidCsrfToken(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    return hash_equals(csrfToken(), $token);
}

function setAuthenticatedUser(array $user): void
{
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Não foi possível renovar a sessão de autenticação.');
    }

    $_SESSION = [];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['avatar'] = $user['avatar'] ?? null;
    $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Persist the authenticated state and release the PHP session lock before
    // returning success or redirecting to the next page.
    if (!persistSecureSession()) {
        throw new RuntimeException('Não foi possível guardar a sessão de autenticação.');
    }
}

function currentUserId(): ?int
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && time() - $lastActivity > AUTH_IDLE_TIMEOUT_SECONDS) {
        clearAuthenticatedSession();
        return null;
    }

    $_SESSION['last_activity'] = time();
    return (int)$_SESSION['user_id'];
}

function clearAuthenticatedSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $options = [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: app_base_path(),
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ];

        if (!empty($params['domain'])) {
            $options['domain'] = $params['domain'];
        }

        setcookie(session_name(), '', $options);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }
    }
}
