<?php
session_start();
require_once "db.php";

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Проверяем, что передан ID файла
if (!isset($_GET['id'])) {
    die("Файл не указан.");
}
$file_id = intval($_GET['id']);

// Проверяем доступ пользователя к файлу
$query = "
    SELECT f.*, u.username AS uploader, u2.username AS shared_user
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    LEFT JOIN users u2 ON f.shared_with = u2.id
    WHERE f.id = $1
      AND (
        f.access_type = 'public'
        OR (f.access_type = 'private' AND f.uploaded_by = $2)
        OR (f.access_type = 'user' AND (f.shared_with = $3 OR f.uploaded_by = $4))
      )
";

$result = pg_query_params($conn, $query, [$file_id, $user_id, $user_id, $user_id]);
if (!$result) {
    die("Ошибка запроса: " . pg_last_error($conn));
}

$file = pg_fetch_assoc($result);
if (!$file) {
    die("❌ Доступ запрещён.");
}

$filepath = "uploads/" . $file['filename'];

if (!file_exists($filepath)) {
    die("❌ Файл не найден.");
}

// Увеличиваем счётчик скачиваний
pg_query_params($conn, "UPDATE files SET downloads = downloads + 1 WHERE id = $1", [$file_id]);

// Отдаём файл пользователю
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" . basename($file['original_name']) . "\"");
header("Content-Length: " . filesize($filepath));
readfile($filepath);
exit;
?>
