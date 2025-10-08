<?php
$url = getenv('DATABASE_URL');
$db = parse_url($url);

$host = $db['host'];
$port = $db['port'];
$user = $db['user'];
$pass = $db['pass'];
$name = ltrim($db['path'], '/');

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

if (!$conn) {
    die("❌ Ошибка подключения: " . pg_last_error());
}
echo "✅ Подключение к PostgreSQL установлено!";
?>


