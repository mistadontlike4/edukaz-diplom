<?php
/*
  EduKaz dual-database connection system
  Автоматическое подключение: Railway → локальный PostgreSQL
*/

// Основная база (Railway)
$railway_url = "postgresql://postgres:USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu@interchange.proxy.rlwy.net:54049/railway";
// Резервная база (локальная, через XAMPP)
$local_url   = "postgresql://postgres:12345@localhost:5432/edukaz_backup";

// --- Функция подключения по URL ---
function connect_pg($url) {
    $db = parse_url($url);
    if (!$db || !isset($db['host'])) return false;

    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'] ?? '';
    $name = ltrim($db['path'], '/');

    $conn_str = "host=$host port=$port dbname=$name user=$user password=$pass sslmode=require";
    $conn = @pg_connect($conn_str);
    return $conn;
}

// --- Пытаемся подключиться к Railway ---
$conn = connect_pg($railway_url);
$using_backup = false;

if (!$conn) {
    error_log("⚠️ Railway PostgreSQL недоступен. Переключаюсь на локальный сервер...");
    $conn = connect_pg($local_url);
    $using_backup = true;
}

if (!$conn) {
    die("❌ Ошибка: не удалось подключиться ни к Railway, ни к локальной базе PostgreSQL.");
}

// Выводим сообщение только если подключение успешно
if ($using_backup) {
    error_log("⚠️ Railway недоступен. Используется локальная база.");
} else {
    error_log("✅ Подключено к Railway PostgreSQL.");
}
// if ($using_backup) {
//     echo "<p style='color:orange'>⚠️ Railway недоступен. Используется локальная база.</p>";
// } else {
//     echo "<p style='color:green'>✅ Подключено к Railway PostgreSQL.</p>";
// }

