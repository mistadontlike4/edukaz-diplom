<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php"; // тут у нас $conn и $is_local уже есть ✅

// Проверка подключений
if (!$conn) {
    die("❌ Нет соединения с БД");
}

echo "<h3>🔄 Синхронизация данных</h3>";

// Определяем направление
$mode = $_GET['mode'] ?? 'both';

// Синхронизация пользователей
function sync_users($src, $dst) {
    $result = pg_query($src, "SELECT id, username, email, password, role, created_at FROM users");
    while ($row = pg_fetch_assoc($result)) {
        $id = $row["id"];
        $username = pg_escape_string($dst, $row["username"]);
        $email = pg_escape_string($dst, $row["email"]);
        $password = pg_escape_string($dst, $row["password"]);
        $role = pg_escape_string($dst, $row["role"]);
        $created = $row["created_at"];

        pg_query($dst,
            "INSERT INTO users (id, username, email, password, role, created_at)
             VALUES ($id, '$username', '$email', '$password', '$role', '$created')
             ON CONFLICT (id) DO UPDATE SET
                 username=EXCLUDED.username,
                 email=EXCLUDED.email,
                 password=EXCLUDED.password,
                 role=EXCLUDED.role"
        );
    }
}

// Синхронизация файлов
function sync_files($src, $dst) {
    $result = pg_query($src,
        "SELECT id, filename, size, downloads, original_name, uploaded_by, access_type, shared_with, uploaded_at
         FROM files"
    );

    while ($row = pg_fetch_assoc($result)) {
        foreach ($row as &$v) {
            if ($v !== null) $v = pg_escape_string($dst, $v);
        }

        pg_query($dst,
            "INSERT INTO files (id, filename, size, downloads, original_name, uploaded_by, access_type, shared_with, uploaded_at)
             VALUES (
                 {$row['id']},
                 '{$row['filename']}',
                 {$row['size']},
                 {$row['downloads']},
                 '{$row['original_name']}',
                 {$row['uploaded_by']},
                 '{$row['access_type']}',
                 " . ($row['shared_with'] ? $row['shared_with'] : "NULL") . ",
                 '{$row['uploaded_at']}'
             )
             ON CONFLICT (id) DO NOTHING"
        );
    }
}

// Выбор направления
if ($mode === 'local-to-railway' || $mode === 'both') {
    if (!$is_local) die("⚠ Локальная БД недоступна");
    sync_users($conn_local, $conn);
    sync_files($conn_local, $conn);
    echo "<p>✅ Локальные данные отправлены в Railway!</p>";
}

if ($mode === 'railway-to-local' || $mode === 'both') {
    if ($is_local) die("⚠ Railway недоступен");
    sync_users($conn, $conn_local);
    sync_files($conn, $conn_local);
    echo "<p>✅ Данные с Railway обновлены локально!</p>";
}

echo "<p>🎯 Готово!</p>";
?>
<a href="admin.php" style="font-size:18px">⬅ Вернуться в админ-панель</a>
