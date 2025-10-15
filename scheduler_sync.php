<?php
/*
  EduKaz Auto-Sync Script with Logging
  Проверяет соединение с Railway и синхронизирует локальную базу.
*/

include("db.php");

$logfile = __DIR__ . "/sync_log.txt";
$time = date("Y-m-d H:i:s");

// Если Railway уже доступен — выходим
if (!$using_backup) {
    file_put_contents($logfile, "[$time] ✅ Railway доступен, синхронизация не требуется.\n", FILE_APPEND);
    exit;
}

// Подключаемся к Railway
$railway_conn = pg_connect("host=interchange.proxy.rlwy.net port=54049 dbname=railway user=postgres password=USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu sslmode=require");
if (!$railway_conn) {
    file_put_contents($logfile, "[$time] ⚠️ Railway недоступен, попытка не удалась.\n", FILE_APPEND);
    exit;
}

// Подключаемся к локальной базе
$local_conn = pg_connect("host=localhost port=5432 dbname=edukaz_backup user=postgres password=12345 sslmode=disable");
if (!$local_conn) {
    file_put_contents($logfile, "[$time] ❌ Ошибка: нет доступа к локальной базе.\n", FILE_APPEND);
    exit;
}

// Список таблиц для синхронизации
$tables = ["users", "files"];

foreach ($tables as $table) {
    $res = pg_query($local_conn, "SELECT * FROM $table");
    $rows = pg_fetch_all($res);

    pg_query($railway_conn, "TRUNCATE TABLE $table RESTART IDENTITY CASCADE");

    if ($rows) {
        foreach ($rows as $r) {
            $cols = array_keys($r);
            $vals = array_map(fn($v) => "'" . pg_escape_string($v) . "'", array_values($r));
            $sql = "INSERT INTO $table (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
            pg_query($railway_conn, $sql);
        }
    }
    file_put_contents($logfile, "[$time] 🔄 Таблица $table синхронизирована.\n", FILE_APPEND);
}

file_put_contents($logfile, "[$time] ✅ Синхронизация завершена успешно.\n", FILE_APPEND);
?>
