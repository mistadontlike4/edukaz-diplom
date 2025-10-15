<?php
include("db.php");

// Проверяем, где мы сейчас
if ($using_backup) {
    echo "Работаем с локальной базой — синхронизация невозможна сейчас.";
    exit;
}

// Подключаемся ко второй (резервной) базе
$backup_conn = pg_connect("host=localhost port=5432 dbname=edukaz_backup user=postgres password=12345 sslmode=disable");

if (!$backup_conn) {
    die("❌ Не удалось подключиться к резервной базе.");
}

// --- Список таблиц для синхронизации ---
$tables = ["users", "files"];

foreach ($tables as $table) {
    echo "<h3>🔄 Синхронизация таблицы: $table</h3>";

    // Читаем данные из Railway
    $result = pg_query($conn, "SELECT * FROM $table");
    $rows = pg_fetch_all($result);

    // Очищаем локальную таблицу
    pg_query($backup_conn, "TRUNCATE TABLE $table RESTART IDENTITY CASCADE");

    // Переносим данные
    if ($rows) {
        foreach ($rows as $r) {
            $cols = array_keys($r);
            $vals = array_map(fn($v) => "'" . pg_escape_string($v) . "'", array_values($r));
            $sql = "INSERT INTO $table (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
            pg_query($backup_conn, $sql);
        }
    }
}
echo "<p style='color:green'>✅ Синхронизация завершена успешно!</p>";
?>
