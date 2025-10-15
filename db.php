<?php
// Подключение к PostgreSQL Railway
$url = "postgresql://postgres:USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu@postgres.railway.internal:5432/railway";
$db = parse_url($url);

$host = $db['host'];
$port = $db['port'] ?? '5432';
$user = $db['user'];
$pass = $db['pass'];
$name = ltrim($db['path'], '/');

$conn_str = "host=$host port=$port dbname=$name user=$user password=$pass";
$conn = pg_connect($conn_str);

if (!$conn) {
    die("❌ Ошибка подключения: " . pg_last_error());
}

// ⚠️ Не добавляй echo, print_r, var_dump или ?> в конце
