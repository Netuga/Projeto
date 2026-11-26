<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';

$ok = 0;
$skip = 0;
$log = [];
$dbName = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';

function logStep($status, $message) {
    global $log;
    $log[] = [$status, $message];
}

function runSql($conn, $sql, $label) {
    global $ok, $skip;
    if ($conn->query($sql)) {
        $ok++;
        logStep('ok', $label);
        return;
    }

    if (in_array($conn->errno, [1050, 1060, 1061, 1826], true)) {
        $skip++;
        logStep('skip', $label . ' (ja existia)');
        return;
    }

    logStep('err', $label . ': ' . $conn->error);
}

function columnExists($conn, $dbName, $table, $column) {
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM information_schema.columns
        WHERE table_schema = ? AND table_name = ? AND column_name = ?
    ');
    $stmt->bind_param('sss', $dbName, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function referenceColumnType($conn, $dbName, $table, $column, $fallback) {
    $stmt = $conn->prepare('
        SELECT COLUMN_TYPE
        FROM information_schema.columns
        WHERE table_schema = ? AND table_name = ? AND column_name = ?
        LIMIT 1
    ');
    $stmt->bind_param('sss', $dbName, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $type = trim((string)($row['COLUMN_TYPE'] ?? ''));
    return $type !== '' ? strtoupper($type) : $fallback;
}

function uniqueIndexExists($conn, $dbName, $table, $columns) {
    $columnList = implode(',', $columns);
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM (
            SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ",") AS indexed_columns
            FROM information_schema.statistics
            WHERE table_schema = ? AND table_name = ? AND non_unique = 0
            GROUP BY index_name
            HAVING indexed_columns = ?
        ) matches
    ');
    $stmt->bind_param('sss', $dbName, $table, $columnList);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function indexExists($conn, $dbName, $table, $indexName) {
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM information_schema.statistics
        WHERE table_schema = ? AND table_name = ? AND index_name = ?
    ');
    $stmt->bind_param('sss', $dbName, $table, $indexName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function foreignKeyExists($conn, $dbName, $table, $constraintName) {
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS total
        FROM information_schema.table_constraints
        WHERE constraint_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = "FOREIGN KEY"
    ');
    $stmt->bind_param('sss', $dbName, $table, $constraintName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function addColumnIfMissing($conn, $dbName, $table, $column, $definition, $label) {
    global $skip;

    if (columnExists($conn, $dbName, $table, $column)) {
        $skip++;
        logStep('skip', $label . ' (ja existia)');
        return;
    }

    runSql($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition", $label);
}

function addUniqueIndexIfMissing($conn, $dbName, $table, $indexName, $columns, $label) {
    global $skip;

    if (uniqueIndexExists($conn, $dbName, $table, $columns)) {
        $skip++;
        logStep('skip', $label . ' (ja existia)');
        return;
    }

    $escapedColumns = array_map(function ($column) {
        return "`$column`";
    }, $columns);
    runSql($conn, "ALTER TABLE `$table` ADD UNIQUE KEY `$indexName` (" . implode(', ', $escapedColumns) . ")", $label);
}

function addIndexIfMissing($conn, $dbName, $table, $indexName, $columns, $label) {
    global $skip;

    if (indexExists($conn, $dbName, $table, $indexName)) {
        $skip++;
        logStep('skip', $label . ' (ja existia)');
        return;
    }

    $escapedColumns = array_map(function ($column) {
        return "`$column`";
    }, $columns);

    runSql($conn, "ALTER TABLE `$table` ADD INDEX `$indexName` (" . implode(', ', $escapedColumns) . ")", $label);
}

function addForeignKeyIfMissing($conn, $dbName, $table, $constraintName, $sql, $label) {
    global $skip;

    if (foreignKeyExists($conn, $dbName, $table, $constraintName)) {
        $skip++;
        logStep('skip', $label . ' (ja existia)');
        return;
    }

    runSql($conn, $sql, $label);
}

$userIdType = referenceColumnType($conn, $dbName, 'users', 'id', 'INT UNSIGNED');
$unitIdType = referenceColumnType($conn, $dbName, 'units', 'id', 'INT UNSIGNED');

$tables = [
    'users' => "
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(120) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            avatar VARCHAR(32) DEFAULT NULL,
            google_id VARCHAR(64) DEFAULT NULL,
            discord_id VARCHAR(32) DEFAULT NULL,
            roblox_id VARCHAR(32) DEFAULT NULL,
            discord_username VARCHAR(64) DEFAULT NULL,
            discord_global_name VARCHAR(64) DEFAULT NULL,
            discord_avatar VARCHAR(255) DEFAULT NULL,
            needs_password_setup TINYINT(1) NOT NULL DEFAULT 0,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'login_attempts' => "
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            blocked_until TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uq_login_attempts_identity_ip (identifier_hash, ip_address),
            INDEX idx_login_attempts_blocked_until (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'units' => "
        CREATE TABLE IF NOT EXISTS units (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            emoji VARCHAR(16) DEFAULT NULL,
            img VARCHAR(255) DEFAULT NULL,
            img_card VARCHAR(255) DEFAULT NULL,
            img_ingame VARCHAR(255) DEFAULT NULL,
            img_minimal VARCHAR(255) DEFAULT NULL,
            bg TEXT DEFAULT NULL,
            rarity VARCHAR(20) NOT NULL DEFAULT 'rare',
            energymultiplier INT NOT NULL DEFAULT 0,
            damagemultiplier DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            avatarbase_multiplier INT NOT NULL DEFAULT 0,
            type VARCHAR(80) DEFAULT NULL,
            elements_json TEXT DEFAULT NULL,
            atk INT NOT NULL DEFAULT 0,
            spd DECIMAL(10,2) NOT NULL DEFAULT 1.00,
            `range` INT NOT NULL DEFAULT 100,
            tier VARCHAR(5) NOT NULL DEFAULT 'c',
            description TEXT DEFAULT NULL,
            passive TEXT DEFAULT NULL,
            ability VARCHAR(120) DEFAULT NULL,
            ability_desc TEXT DEFAULT NULL,
            ult_damage VARCHAR(120) DEFAULT NULL,
            ult_multiplier VARCHAR(50) DEFAULT NULL,
            ult_duration INT DEFAULT NULL,
            ult_cooldown INT DEFAULT NULL,
            ability_cooldown INT DEFAULT NULL,
            obtain_method VARCHAR(255) DEFAULT NULL,
            perf_wave_clear INT DEFAULT NULL,
            perf_boss_dps INT DEFAULT NULL,
            perf_utility INT DEFAULT NULL,
            perf_survival INT DEFAULT NULL,
            video_url VARCHAR(255) DEFAULT NULL,
            skins_json TEXT DEFAULT NULL,
            is_meta TINYINT(1) NOT NULL DEFAULT 0,
            is_new TINYINT(1) NOT NULL DEFAULT 0,
            added_by $userIdType DEFAULT NULL,
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_units_rarity (rarity),
            INDEX idx_units_tier (tier),
            CONSTRAINT fk_units_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'user_stats' => "
        CREATE TABLE IF NOT EXISTS user_stats (
            user_id $userIdType NOT NULL PRIMARY KEY,
            pvp_rank VARCHAR(30) NOT NULL DEFAULT 'Bronze',
            pvp_points INT NOT NULL DEFAULT 0,
            total_visits INT NOT NULL DEFAULT 0,
            favorite_unit $unitIdType DEFAULT NULL,
            CONSTRAINT fk_user_stats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_stats_favorite FOREIGN KEY (favorite_unit) REFERENCES units(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'items' => "
        CREATE TABLE IF NOT EXISTS items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            emoji VARCHAR(16) DEFAULT NULL,
            img VARCHAR(255) DEFAULT NULL,
            bg TEXT DEFAULT NULL,
            rarity VARCHAR(20) NOT NULL DEFAULT 'rare',
            type VARCHAR(120) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            effect VARCHAR(255) DEFAULT NULL,
            obtain_method VARCHAR(255) DEFAULT NULL,
            dmg_bonus DECIMAL(10,2) NOT NULL DEFAULT 0,
            spd_bonus DECIMAL(10,2) DEFAULT NULL,
            added_by $userIdType DEFAULT NULL,
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_items_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'accessories' => "
        CREATE TABLE IF NOT EXISTS accessories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            emoji VARCHAR(16) DEFAULT NULL,
            img VARCHAR(255) DEFAULT NULL,
            bg TEXT DEFAULT NULL,
            rarity VARCHAR(20) NOT NULL DEFAULT 'rare',
            type VARCHAR(120) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            effect VARCHAR(255) DEFAULT NULL,
            obtain_method VARCHAR(255) DEFAULT NULL,
            dmg_bonus DECIMAL(10,2) NOT NULL DEFAULT 0,
            spd_bonus DECIMAL(10,2) DEFAULT NULL,
            added_by $userIdType DEFAULT NULL,
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_accessories_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'weapons' => "
        CREATE TABLE IF NOT EXISTS weapons (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            emoji VARCHAR(16) DEFAULT NULL,
            img VARCHAR(255) DEFAULT NULL,
            bg TEXT DEFAULT NULL,
            rarity VARCHAR(20) NOT NULL DEFAULT 'rare',
            type VARCHAR(120) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            effect VARCHAR(255) DEFAULT NULL,
            obtain_method VARCHAR(255) DEFAULT NULL,
            dmg_bonus DECIMAL(10,2) NOT NULL DEFAULT 0,
            spd_bonus DECIMAL(10,2) DEFAULT NULL,
            added_by $userIdType DEFAULT NULL,
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_weapons_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'gamemodes' => "
        CREATE TABLE IF NOT EXISTS gamemodes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mode_key VARCHAR(20) NOT NULL,
            label VARCHAR(120) NOT NULL,
            badge VARCHAR(80) DEFAULT NULL,
            preview_title VARCHAR(160) DEFAULT NULL,
            preview_text TEXT DEFAULT NULL,
            gradient TEXT DEFAULT NULL,
            bg_image VARCHAR(255) DEFAULT NULL,
            meta_json TEXT DEFAULT NULL,
            rewards_json TEXT DEFAULT NULL,
            notes_json TEXT DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            added_by $userIdType DEFAULT NULL,
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_gamemodes_mode_key (mode_key),
            INDEX idx_gamemodes_sort (mode_key, sort_order, id),
            CONSTRAINT fk_gamemodes_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
];

foreach ($tables as $label => $sql) {
    runSql($conn, $sql, "Tabela `$label`");
}

$columns = [
    ['users', 'avatar', "VARCHAR(32) DEFAULT NULL", 'users.avatar'],
    ['users', 'google_id', "VARCHAR(64) DEFAULT NULL", 'users.google_id'],
    ['users', 'discord_id', "VARCHAR(32) DEFAULT NULL", 'users.discord_id'],
    ['users', 'roblox_id', "VARCHAR(32) DEFAULT NULL", 'users.roblox_id'],
    ['users', 'discord_username', "VARCHAR(64) DEFAULT NULL", 'users.discord_username'],
    ['users', 'discord_global_name', "VARCHAR(64) DEFAULT NULL", 'users.discord_global_name'],
    ['users', 'discord_avatar', "VARCHAR(255) DEFAULT NULL", 'users.discord_avatar'],
    ['users', 'needs_password_setup', "TINYINT(1) NOT NULL DEFAULT 0", 'users.needs_password_setup'],
    ['users', 'is_admin', "TINYINT(1) NOT NULL DEFAULT 0", 'users.is_admin'],
    ['users', 'last_login', "TIMESTAMP NULL DEFAULT NULL", 'users.last_login'],
    ['units', 'img_card', "VARCHAR(255) DEFAULT NULL", 'units.img_card'],
    ['units', 'img_ingame', "VARCHAR(255) DEFAULT NULL", 'units.img_ingame'],
    ['units', 'img_minimal', "VARCHAR(255) DEFAULT NULL", 'units.img_minimal'],
    ['units', 'energymultiplier', "INT NOT NULL DEFAULT 0", 'units.energymultiplier'],
    ['units', 'damagemultiplier', "DECIMAL(10,2) NOT NULL DEFAULT 1.00", 'units.damagemultiplier'],
    ['units', 'avatarbase_multiplier', "INT NOT NULL DEFAULT 0", 'units.avatarbase_multiplier'],
    ['units', 'elements_json', "TEXT DEFAULT NULL", 'units.elements_json'],
    ['units', 'ability_desc', "TEXT DEFAULT NULL", 'units.ability_desc'],
    ['units', 'ult_damage', "VARCHAR(120) DEFAULT NULL", 'units.ult_damage'],
    ['units', 'passive', "TEXT DEFAULT NULL", 'units.passive'],
    ['units', 'ult_multiplier', "VARCHAR(50) DEFAULT NULL", 'units.ult_multiplier'],
    ['units', 'ult_duration', "INT DEFAULT NULL", 'units.ult_duration'],
    ['units', 'ult_cooldown', "INT DEFAULT NULL", 'units.ult_cooldown'],
    ['units', 'ability_cooldown', "INT DEFAULT NULL", 'units.ability_cooldown'],
    ['units', 'obtain_method', "VARCHAR(255) DEFAULT NULL", 'units.obtain_method'],
    ['units', 'perf_wave_clear', "INT DEFAULT NULL", 'units.perf_wave_clear'],
    ['units', 'perf_boss_dps', "INT DEFAULT NULL", 'units.perf_boss_dps'],
    ['units', 'perf_utility', "INT DEFAULT NULL", 'units.perf_utility'],
    ['units', 'perf_survival', "INT DEFAULT NULL", 'units.perf_survival'],
    ['units', 'video_url', "VARCHAR(255) DEFAULT NULL", 'units.video_url'],
    ['units', 'skins_json', "TEXT DEFAULT NULL", 'units.skins_json'],
    ['units', 'is_meta', "TINYINT(1) NOT NULL DEFAULT 0", 'units.is_meta'],
    ['units', 'is_new', "TINYINT(1) NOT NULL DEFAULT 0", 'units.is_new'],
    ['units', 'added_by', "$userIdType DEFAULT NULL", 'units.added_by'],
    ['units', 'added_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP", 'units.added_at'],
    ['items', 'obtain_method', "VARCHAR(255) DEFAULT NULL", 'items.obtain_method'],
    ['gamemodes', 'label', "VARCHAR(120) NOT NULL DEFAULT 'Mode'", 'gamemodes.label'],
    ['gamemodes', 'badge', "VARCHAR(80) DEFAULT NULL", 'gamemodes.badge'],
    ['gamemodes', 'preview_title', "VARCHAR(160) DEFAULT NULL", 'gamemodes.preview_title'],
    ['gamemodes', 'preview_text', "TEXT DEFAULT NULL", 'gamemodes.preview_text'],
    ['gamemodes', 'gradient', "TEXT DEFAULT NULL", 'gamemodes.gradient'],
    ['gamemodes', 'bg_image', "VARCHAR(255) DEFAULT NULL", 'gamemodes.bg_image'],
    ['gamemodes', 'meta_json', "TEXT DEFAULT NULL", 'gamemodes.meta_json'],
    ['gamemodes', 'rewards_json', "TEXT DEFAULT NULL", 'gamemodes.rewards_json'],
    ['gamemodes', 'notes_json', "TEXT DEFAULT NULL", 'gamemodes.notes_json'],
    ['gamemodes', 'sort_order', "INT NOT NULL DEFAULT 0", 'gamemodes.sort_order'],
    ['gamemodes', 'added_by', "$userIdType DEFAULT NULL", 'gamemodes.added_by'],
    ['gamemodes', 'added_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP", 'gamemodes.added_at'],
    ['gamemodes', 'updated_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", 'gamemodes.updated_at'],
    ['user_stats', 'pvp_rank', "VARCHAR(30) NOT NULL DEFAULT 'Bronze'", 'user_stats.pvp_rank'],
    ['user_stats', 'pvp_points', "INT NOT NULL DEFAULT 0", 'user_stats.pvp_points'],
    ['user_stats', 'total_visits', "INT NOT NULL DEFAULT 0", 'user_stats.total_visits'],
    ['user_stats', 'favorite_unit', "$unitIdType DEFAULT NULL", 'user_stats.favorite_unit'],
];

foreach ($columns as [$table, $column, $definition, $label]) {
    addColumnIfMissing($conn, $dbName, $table, $column, $definition, $label);
}

$uniqueIndexes = [
    ['users', 'uq_users_username', ['username'], 'users.username UNIQUE'],
    ['users', 'uq_users_email', ['email'], 'users.email UNIQUE'],
    ['users', 'uq_users_google_id', ['google_id'], 'users.google_id UNIQUE'],
    ['users', 'uq_users_discord_id', ['discord_id'], 'users.discord_id UNIQUE'],
    ['users', 'uq_users_roblox_id', ['roblox_id'], 'users.roblox_id UNIQUE'],
    ['login_attempts', 'uq_login_attempts_identity_ip', ['identifier_hash', 'ip_address'], 'login_attempts identifier/ip UNIQUE'],
];

foreach ($uniqueIndexes as [$table, $indexName, $columnsForIndex, $label]) {
    addUniqueIndexIfMissing($conn, $dbName, $table, $indexName, $columnsForIndex, $label);
}

$indexes = [
    ['login_attempts', 'idx_login_attempts_blocked_until', ['blocked_until'], 'login_attempts.blocked_until INDEX'],
    ['units', 'idx_units_rarity', ['rarity'], 'units.rarity INDEX'],
    ['units', 'idx_units_tier', ['tier'], 'units.tier INDEX'],
    ['gamemodes', 'idx_gamemodes_mode_key', ['mode_key'], 'gamemodes.mode_key INDEX'],
    ['gamemodes', 'idx_gamemodes_sort', ['mode_key', 'sort_order', 'id'], 'gamemodes.sort INDEX'],
];

foreach ($indexes as [$table, $indexName, $columnsForIndex, $label]) {
    addIndexIfMissing($conn, $dbName, $table, $indexName, $columnsForIndex, $label);
}

$foreignKeys = [
    ['units', 'fk_units_added_by', "ALTER TABLE `units` ADD CONSTRAINT `fk_units_added_by` FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL", 'units.added_by FK'],
    ['user_stats', 'fk_user_stats_user', "ALTER TABLE `user_stats` ADD CONSTRAINT `fk_user_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE", 'user_stats.user FK'],
    ['user_stats', 'fk_user_stats_favorite', "ALTER TABLE `user_stats` ADD CONSTRAINT `fk_user_stats_favorite` FOREIGN KEY (`favorite_unit`) REFERENCES `units`(`id`) ON DELETE SET NULL", 'user_stats.favorite_unit FK'],
    ['items', 'fk_items_added_by', "ALTER TABLE `items` ADD CONSTRAINT `fk_items_added_by` FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL", 'items.added_by FK'],
    ['gamemodes', 'fk_gamemodes_added_by', "ALTER TABLE `gamemodes` ADD CONSTRAINT `fk_gamemodes_added_by` FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL", 'gamemodes.added_by FK'],
];

foreach ($foreignKeys as [$table, $constraintName, $sql, $label]) {
    addForeignKeyIfMissing($conn, $dbName, $table, $constraintName, $sql, $label);
}

if (columnExists($conn, $dbName, 'user_stats', 'favorite_unit')) {
    runSql($conn, "ALTER TABLE user_stats MODIFY COLUMN favorite_unit INT UNSIGNED DEFAULT NULL", 'user_stats.favorite_unit (INT)');
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Setup · Anime Warriors</title>
<style>
body{font-family:monospace;background:#06060f;color:#dde0ff;padding:40px;max-width:800px;margin:0 auto}
h1{color:#d91f2f;letter-spacing:3px}
.row{padding:8px 0;border-bottom:1px solid #1a1a35}
.ok{color:#00ff9d}
.skip{color:#ffd040}
.err{color:#ff5c7d}
.warn{background:rgba(255,208,64,.08);border:1px solid #ffd040;padding:16px;border-radius:12px;margin-top:20px;color:#ffd040;line-height:1.8}
</style>
</head>
<body>
<h1>Anime Warriors - Setup Tabelas</h1>

<?php foreach ($log as [$status, $message]): ?>
  <div class="row <?= $status ?>">
    <?= $status === 'ok' ? 'OK' : ($status === 'skip' ? 'SKIP' : 'ERR') ?> - <?= htmlspecialchars($message) ?>
  </div>
<?php endforeach; ?>

<div style="margin-top:16px;background:#12122a;padding:14px;border-radius:10px">
  OK <?= $ok ?> | SKIP <?= $skip ?>
</div>

<div class="warn">
  Apaga este ficheiro depois de o usares.<br>
  Depois podes gerir dados em <strong>admin/index.php</strong> e nos separadores suportados pelo painel.
</div>
</body>
</html>