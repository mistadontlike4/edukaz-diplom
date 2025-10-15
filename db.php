<?php
/*
  EduKaz dual-database system
  Автоматическое подключение: Railway → локальный PostgreSQL
  + статус-индикатор и безопасное подключение
*/

// Railway (основная база)
$railway_url = "postgresql://postgres:USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu@interchange.proxy.rlwy.net:54049/railway";

// Локальная база (резервная, XAMPP)
$local_url   = "postgresql://postgres:12345@localhost:5432/edukaz_backup";

// --- Функция подключения к базе по URL ---
function connect_pg($url, $ssl = 'require') {
    $db = parse_url($url);
    if (!$db || !isset($db['host'])) return false;

    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'] ?? '';
    $name = ltrim($db['path'], '/');

    $conn_str = "host=$host port=$port dbname=$name user=$user password=$pass sslmode=$ssl";
    $conn = @pg_connect($conn_str);
    return $conn;
}

// --- Подключаемся к Railway ---
$conn = connect_pg($railway_url);
$using_backup = false;
$db_status = "";

// Если Railway недоступен → пробуем локальную базу
if (!$conn) {
    $conn = connect_pg($local_url, 'disable');
    $using_backup = true;
}

// --- Формируем статус подключения ---
if (!$conn) {
    $db_status = "<span style='color:red'>❌ Нет соединения с базой данных.</span>";
} elseif ($using_backup) {
    $db_status = "<span style='color:orange'>⚠️ Работаем с резервной базой (локальной).</span>";
} else {
    $db_status = "<span style='color:green'>✅ Подключено к Railway PostgreSQL.</span>";
}
if ($conn) {
    echo "<p style='color:green;'>✅ Подключение успешно к: " . ($is_local ? "локальной базе" : "Railway PostgreSQL") . "</p>";
} else {
    echo "<p style='color:red;'>❌ Ошибка подключения к базе данных.</p>";
}
?>
