<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../services/records.php';

startSecureSession();
$adminId = currentUserId();
if (!$adminId) {
    header('Location: ' . app_url('pages/login.html?next=admin'));
    exit;
}

$stmt = $conn->prepare('SELECT id, username, is_admin FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$adminUser = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$adminUser || (int)$adminUser['is_admin'] !== 1) {
    http_response_code(403);
    exit('Acesso reservado a administradores.');
}

function adminEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
