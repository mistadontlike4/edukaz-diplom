<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Флаг для определения режима
$is_local = false;
$conn = false;

// 1️⃣ Пробуем Railway
if (getenv("DATABASE_URL")) {
    $url = getenv("DATABASE_URL");
} else {
    // Можно задать вручную, если нет переменной окружения
    $url = "postgresql://postgres:USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu@interchange.proxy.rlwy.net:54049/railway";
}

try {
    $db = parse_url($url);
    if (!$db || !isset($db['host'])) {
        throw new Exception("Некорректный формат DATABASE_URL");
    }

    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'];
    $name = ltrim($db['path'], '/');

    $conn = @pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

    if ($conn) {
        $is_local = false;
    } else {
        throw new Exception("Не удалось подключиться к Railway PostgreSQL");
    }
} catch (Throwable $e) {
    // 2️⃣ Переход на локальную базу
    $is_local = true;
    $host = "localhost";
    $port = "5432";
    $user = "postgres";
    $pass = "12345"; // ← поставь свой пароль от локальной PostgreSQL
    $name = "edukaz";

    $conn = @pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");
}

// 3️⃣ Проверка соединения
if (!$conn) {
    echo "<p style='color:red;'>❌ Ошибка подключения к базе данных.</p>";
} else {
    echo "<p style='color:green;'>✅ Подключено к " . ($is_local ? "локальной базе данных" : "Railway PostgreSQL") . "</p>";
}
?>
