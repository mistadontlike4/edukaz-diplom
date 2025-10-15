<?php
// === Настройки подключения ===

// Railway
$remote = [
    'host' => 'interchange.proxy.rlwy.net',
    'port' => '54049',
    'dbname' => 'railway',
    'user' => 'postgres',
    'password' => 'USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu'
];

// Локальная база
$local = [
    'host' => 'localhost',
    'port' => '5432',
    'dbname' => 'edukaz_backup',
    'user' => 'postgres',
    'password' => 'admin'
];

$logFile = __DIR__ . '/sync_log.txt';
function logmsg($text) {
    global $logFile;
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $text . PHP_EOL, FILE_APPEND);
}

// === Подключение ===
$remote_conn = @pg_connect("host={$remote['host']} port={$remote['port']} dbname={$remote['dbname']} user={$remote['user']} password={$remote['password']}");
$local_conn  = @pg_connect("host={$local['host']} port={$local['port']} dbname={$local['dbname']} user={$local['user']} password={$local['password']}");

if (!$remote_conn) exit("<p style='color:red'>❌ Railway недоступен.</p>");
if (!$local_conn) exit("<p style='color:red'>❌ Локальная база недоступна.</p>");

logmsg("🚀 Начало синхронизации...");

// === Проверка и создание таблиц ===
$tables_sql = [
    "users" => "
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ",
    "files" => "
        CREATE TABLE IF NOT EXISTS files (
            id SERIAL PRIMARY KEY,
            filename TEXT NOT NULL,
            original_name TEXT,
            size BIGINT DEFAULT 0,
            downloads INTEGER DEFAULT 0,
            uploaded_by INTEGER REFERENCES users(id) ON DELETE CASCADE,
            access_type VARCHAR(20) DEFAULT 'public',
            shared_with INTEGER REFERENCES users(id) ON DELETE SET NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    "
];

foreach ($tables_sql as $name => $sql) {
    pg_query($local_conn, $sql);
    logmsg("✅ Проверена таблица $name");
}

// === Проверка недостающих колонок ===
function ensure_column($conn, $table, $column, $definition) {
    $check = pg_query_params($conn,
        "SELECT 1 FROM information_schema.columns WHERE table_name=$1 AND column_name=$2",
        [$table, $column]
    );
    if (pg_num_rows($check) === 0) {
        pg_query($conn, "ALTER TABLE $table ADD COLUMN $column $definition");
        logmsg("🧩 Добавлен недостающий столбец '$column' в таблицу $table");
    }
}

ensure_column($local_conn, 'files', 'size', 'BIGINT DEFAULT 0');
ensure_column($local_conn, 'files', 'downloads', 'INTEGER DEFAULT 0');

// === Синхронизация ===
$tables = ['users', 'files'];

foreach ($tables as $table) {
    logmsg("🔄 Синхронизация таблицы: $table");

    $remote_data = pg_query($remote_conn, "SELECT * FROM $table");
    if (!$remote_data) {
        logmsg("⚠️ Не удалось прочитать таблицу $table с Railway");
        continue;
    }

    pg_query($local_conn, "TRUNCATE TABLE $table RESTART IDENTITY CASCADE");

    while ($row = pg_fetch_assoc($remote_data)) {
        $columns = array_keys($row);
        $values = array_map(fn($v) => $v === null ? 'NULL' : "'" . pg_escape_string($v) . "'", array_values($row));

        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
        $res = pg_query($local_conn, $sql);
        if (!$res) logmsg("❌ Ошибка вставки в $table: " . pg_last_error($local_conn));
    }

    logmsg("✅ Таблица $table синхронизирована.");
}

logmsg("🎉 Синхронизация завершена успешно.");
echo "<p style='color:green;font-family:Arial'>✅ Синхронизация выполнена!</p>";
?>
