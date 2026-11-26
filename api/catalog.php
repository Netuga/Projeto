<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$tables = ['item' => 'items', 'accessory' => 'accessories', 'weapon' => 'weapons'];
$type = strtolower(trim((string)($_GET['type'] ?? '')));
if (!isset($tables[$type])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de conteúdo inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $table = $tables[$type];
    $result = $conn->query("SELECT * FROM `$table` ORDER BY added_at DESC, id DESC");
    if (!$result) throw new RuntimeException('Não foi possível carregar o catálogo.');
    echo json_encode($result->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Catalog API failed (' . $type . '): ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar este conteúdo.'], JSON_UNESCAPED_UNICODE);
}
