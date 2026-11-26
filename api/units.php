<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function unitsApiJson($payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $action = trim((string)($_GET['action'] ?? 'list'));

    if ($action === 'list') {
        $result = $conn->query('SELECT * FROM units ORDER BY id DESC');
        if (!$result) throw new RuntimeException('Não foi possível carregar as units.');
        unitsApiJson($result->fetch_all(MYSQLI_ASSOC));
    }

    if ($action === 'stats') {
        $result = $conn->query('SELECT LOWER(rarity) AS rarity, COUNT(*) AS total FROM units GROUP BY LOWER(rarity)');
        if (!$result) throw new RuntimeException('Não foi possível carregar as estatísticas.');
        $stats = ['total' => 0, 'secreto' => 0, 'myth' => 0, 'legend' => 0, 'epic' => 0, 'rare' => 0];
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $rarity = strtolower((string)$row['rarity']);
            $count = (int)$row['total'];
            $stats['total'] += $count;
            $rarityKeys = [
                'secret' => 'secreto', 'secreto' => 'secreto',
                'mythical' => 'myth', 'mythic' => 'myth', 'myth' => 'myth',
                'legendary' => 'legend', 'legend' => 'legend',
                'epic' => 'epic', 'rare' => 'rare',
            ];
            $key = $rarityKeys[$rarity] ?? null;
            if ($key !== null) $stats[$key] += $count;
        }
        unitsApiJson($stats);
    }

    if ($action === 'recent') {
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 5)));
        $stmt = $conn->prepare('SELECT * FROM units ORDER BY id DESC LIMIT ?');
        if (!$stmt) throw new RuntimeException('Não foi possível preparar a lista de recentes.');
        $stmt->bind_param('i', $limit);
        if (!$stmt->execute()) throw new RuntimeException('Não foi possível carregar as units recentes.');
        unitsApiJson($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    unitsApiJson(['error' => 'Ação desconhecida.'], 400);
} catch (Throwable $error) {
    error_log('Units API failed: ' . $error->getMessage());
    unitsApiJson(['error' => 'Não foi possível carregar as units.'], 500);
}
