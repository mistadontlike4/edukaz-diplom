<?php
// ✅ Подключение к PostgreSQL Railway

// Строка подключения (можно хранить в переменной окружения DATABASE_URL)
$url = "postgresql://postgres:USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu@postgres.railway.internal:5432/railway";

// Разбираем URL на составляющие
$db = parse_url($url);

if (!$db || !isset($db['host'])) {
    die("❌ Ошибка парсинга строки подключения!");
}

$host = $db['host'];
$port = $db['port'] ?? '5432';
$user = $db['user'];
$pass = $db['pass'];
$name = ltrim($db['path'], '/');

// Формируем строку подключения для pg_connect
$conn_str = "host=$host port=$port dbname=$name user=$user password=$pass";

// Пробуем подключиться
$conn = pg_connect($conn_str);

if (!$conn) {
    die("❌ Ошибка подключения: " . pg_last_error());
}

// ✅ Проверка соединения
$result = pg_query($conn, "SELECT version();");
if ($result) {
    $row = pg_fetch_row($result);
    echo "✅ Подключение успешно! PostgreSQL версия: " . $row[0];
} else {
    echo "⚠️ Подключение установлено, но не удалось получить версию PostgreSQL.";
}

pg_close($conn);
?>
