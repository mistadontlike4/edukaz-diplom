<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === Инициализация переменных ===
$is_local = false;
$conn = false;
$db_status = "";

// === 1️⃣ Пытаемся подключиться к Railway PostgreSQL ===
if (getenv("DATABASE_URL")) {
    $url = getenv("DATABASE_URL");
} else {
    // Можно задать вручную, если Railway переменной нет
    $url = "postgresql://postgres:USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu@interchange.proxy.rlwy.net:54049/railway";
}

try {
    $db = parse_url($url);
    if (!$db || !isset($db['host'])) {
        throw new Exception("❌ Некорректный формат DATABASE_URL");
    }

    $host = $db['host'];
    $port = $db['port'] ?? '5432';
    $user = $db['user'];
    $pass = $db['pass'];
    $name = ltrim($db['path'], '/');

    $conn = @pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

    if ($conn) {
        $is_local = false;
        $db_status = "<div style='color:green;font-weight:bold;text-align:center;'>🟢 Railway PostgreSQL подключён</div>";
    } else {
        throw new Exception("⚠️ Не удалось подключиться к Railway PostgreSQL");
    }

} catch (Throwable $e) {

    // === 2️⃣ Автоматический переход на локальную базу ===
    $is_local = true;
    $host = "localhost";
    $port = "5432";
    $user = "postgres";
    $pass = "admin"; // ← поставь свой пароль от локальной PostgreSQL
    $name = "edukaz_backup";

    $conn = @pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

    if ($conn) {
        $db_status = "<div style='color:orange;font-weight:bold;text-align:center;'>🟠 Railway недоступен, используется локальная база (edukaz_backup)</div>";
    } else {
        $db_status = "<div style='color:red;font-weight:bold;text-align:center;'>🔴 Ошибка: ни Railway, ни локальная база недоступны!</div>";
    }
}

// === 3️⃣ Проверка подключения ===
if (!$conn) {
    echo $db_status;
    exit("<p style='color:red;text-align:center;'>❌ Подключение к базе данных не удалось. Проверь настройки.</p>");
}
?>
