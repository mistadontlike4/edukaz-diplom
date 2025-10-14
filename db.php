<?php
$url = getenv('DATABASE_URL');

if (!$url) {
    die("❌ Переменная DATABASE_URL не найдена в окружении!");
}

// echo "<p>📦 DATABASE_URL: $url</p>";

$db = parse_url($url);

if (!$db || !isset($db['host'])) {
    die("❌ Ошибка парсинга DATABASE_URL. Проверь формат: postgres://user:pass@host:port/dbname");
}


$host = isset($db['host']) ? $db['host'] : '';
$port = isset($db['port']) ? $db['port'] : '5432';
$user = isset($db['user']) ? $db['user'] : '';
$pass = isset($db['pass']) ? $db['pass'] : '';
$name = isset($db['path']) ? ltrim($db['path'], '/') : '';

if (!$host || !$user || !$pass || !$name) {
    die("❌ Не все параметры подключения определены! Проверьте DATABASE_URL.");
}

// echo "<p>🔗 Подключение к: $host:$port / БД: $name / Пользователь: $user</p>";

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

if (!$conn) {
    die("❌ Ошибка подключения: " . pg_last_error());
}

// echo "✅ Подключение к PostgreSQL установлено!";
?>
