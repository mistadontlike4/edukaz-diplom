<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

if (!isset($_GET['id'])) {
    die("Файл не указан.");
}

$file_id = intval($_GET['id']);

// Получаем данные файла (только метаданные и потоковый доступ)
$query = "
    SELECT f.original_name, f.file_data, f.access_type, f.uploaded_by, f.shared_with
    FROM files f
    WHERE f.id = $1
";
$res = pg_query_params($conn, $query, [$file_id]);
$file = pg_fetch_assoc($res);

if (!$file) {
    die("❌ Файл не найден в базе данных.");
}

// Проверка прав доступа
if (
    $file['access_type'] === 'private' && $file['uploaded_by'] != $user_id && $role !== 'admin'
    || $file['access_type'] === 'user' && $file['shared_with'] != $user_id && $file['uploaded_by'] != $user_id
) {
    die("🚫 У вас нет доступа к этому файлу.");
}

// Заголовки для браузера
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" . basename($file['original_name']) . "\"");
header("Content-Transfer-Encoding: binary");
header("Cache-Control: must-revalidate");
header("Pragma: public");

// Читаем поток байтов из базы и выдаём по частям (чтобы не грузить всю память)
$chunkSize = 1024 * 1024; // 1 МБ
$data = pg_unescape_bytea($file['file_data']);
$length = strlen($data);

for ($i = 0; $i < $length; $i += $chunkSize) {
    echo substr($data, $i, $chunkSize);
    flush(); // отправляем клиенту
}

exit;
?>
