<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function gamemodesApiJson($payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gamemodeJsonList($value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function gamemodeApiRecord(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'modeKey' => (string)$row['mode_key'],
        'mode_key' => (string)$row['mode_key'],
        'label' => (string)$row['label'],
        'badge' => (string)($row['badge'] ?? ''),
        'previewTitle' => (string)($row['preview_title'] ?? ''),
        'preview_title' => (string)($row['preview_title'] ?? ''),
        'previewText' => (string)($row['preview_text'] ?? ''),
        'preview_text' => (string)($row['preview_text'] ?? ''),
        'gradient' => (string)($row['gradient'] ?? ''),
        'bgImage' => (string)($row['bg_image'] ?? ''),
        'bg_image' => (string)($row['bg_image'] ?? ''),
        'meta' => gamemodeJsonList($row['meta_json'] ?? null),
        'rewards' => gamemodeJsonList($row['rewards_json'] ?? null),
        'notes' => gamemodeJsonList($row['notes_json'] ?? null),
        'sortOrder' => (int)($row['sort_order'] ?? 0),
    ];
}

try {
    $action = trim((string)($_GET['action'] ?? 'list'));
    if ($action === 'list') {
        $result = $conn->query('SELECT * FROM gamemodes ORDER BY mode_key ASC, sort_order ASC, id ASC');
        if (!$result) throw new RuntimeException('Não foi possível carregar os gamemodes.');
        $grouped = [];
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $key = (string)$row['mode_key'];
            if (!isset($grouped[$key])) $grouped[$key] = ['items' => []];
            $grouped[$key]['items'][] = gamemodeApiRecord($row);
        }
        gamemodesApiJson($grouped);
    }

    if ($action === 'overview') {
        $result = $conn->query('SELECT * FROM gamemodes ORDER BY added_at DESC, id DESC LIMIT 1');
        if (!$result) throw new RuntimeException('Não foi possível carregar o resumo dos gamemodes.');
        $row = $result->fetch_assoc();
        gamemodesApiJson($row ? gamemodeApiRecord($row) : null);
    }

    gamemodesApiJson(['error' => 'Ação desconhecida.'], 400);
} catch (Throwable $error) {
    error_log('Gamemodes API failed: ' . $error->getMessage());
    gamemodesApiJson(['error' => 'Não foi possível carregar os gamemodes.'], 500);
}
