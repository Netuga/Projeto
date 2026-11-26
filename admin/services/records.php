<?php
function adminModules(): array
{
    $base = __DIR__ . '/../modules/';
    $modules = [];
    foreach (['units', 'gamemodes', 'items', 'accessories', 'weapons', 'users', 'user_stats', 'login_attempts'] as $key) {
        $config = require $base . $key . '/config.php';
        $modules[$key] = $config;
    }
    return $modules;
}

function adminBindAndExecute(mysqli_stmt $stmt, string $types, array $values): void
{
    if ($types !== '') {
        $args = [$types];
        foreach ($values as $index => &$value) $args[] = &$value;
        call_user_func_array([$stmt, 'bind_param'], $args);
    }
    if (!$stmt->execute()) throw new RuntimeException('Falha na operação da base de dados.');
}

function adminListRecords(mysqli $conn, array $module): array
{
    $primary = $module['primary'] ?? 'id';
    $select = ['`' . $primary . '` AS id'];
    foreach ($module['columns'] as $column) $select[] = '`' . $column['name'] . '`';
    $sql = 'SELECT ' . implode(', ', array_unique($select)) . ' FROM `' . $module['table'] . '` ORDER BY `' . $primary . '` DESC LIMIT 300';
    $result = $conn->query($sql);
    if (!$result) throw new RuntimeException('Não foi possível carregar os registos.');
    return $result->fetch_all(MYSQLI_ASSOC);
}

function adminFindRecord(mysqli $conn, array $module, int $id): ?array
{
    if ($id < 1 || !empty($module['readonly'])) return null;
    $primary = $module['primary'] ?? 'id';
    $stmt = $conn->prepare('SELECT * FROM `' . $module['table'] . '` WHERE `' . $primary . '` = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function adminSaveRecord(mysqli $conn, array $module, array $input): void
{
    $values = [];
    $types = '';
    foreach ($module['fields'] as $field) {
        $name = $field['name'];
        $value = ($field['type'] ?? '') === 'checkbox' ? (!empty($input[$name]) ? 1 : 0) : trim((string)($input[$name] ?? ''));
        if (!empty($field['required']) && $value === '') throw new InvalidArgumentException('Campo obrigatório vazio.');
        if ($value === '') $value = null;
        $values[] = $value;
        $types .= in_array($field['type'] ?? 'text', ['number', 'checkbox'], true) && ($field['step'] ?? '') === '' ? 'i' : ((($field['type'] ?? '') === 'number') ? 'd' : 's');
    }

    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        $sets = [];
        foreach ($module['fields'] as $field) $sets[] = '`' . $field['name'] . '` = ?';
        $stmt = $conn->prepare('UPDATE `' . $module['table'] . '` SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $values[] = $id;
        $types .= 'i';
    } else {
        $columns = array_map(static fn($field) => '`' . $field['name'] . '`', $module['fields']);
        $marks = array_fill(0, count($columns), '?');
        $stmt = $conn->prepare('INSERT INTO `' . $module['table'] . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $marks) . ')');
    }
    adminBindAndExecute($stmt, $types, $values);
    $stmt->close();
}

function adminDeleteRecord(mysqli $conn, array $module, int $id): void
{
    if ($id < 1 || !empty($module['readonly'])) throw new InvalidArgumentException('Registo inválido.');
    $stmt = $conn->prepare('DELETE FROM `' . $module['table'] . '` WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) throw new RuntimeException('Falha ao remover registo.');
    $stmt->close();
}
