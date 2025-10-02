<?php
include("db.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Проверяем доступ к файлу
if (!isset($_GET['id'])) {
    die("Файл не указан.");
}
$file_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT f.*, u.username AS uploader, u2.username AS shared_user
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    LEFT JOIN users u2 ON f.shared_with = u2.id
    WHERE f.id = ?
      AND (
        f.access_type='public'
        OR (f.access_type='private' AND f.uploaded_by=?)
        OR (f.access_type='shared' AND (f.shared_with=? OR f.uploaded_by=?))
      )
");
$stmt->bind_param("iiii", $file_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows===0) {
    die("❌ Доступ запрещён.");
}

$file = $result->fetch_assoc();
$filepath = "uploads/" . $file['filename'];

if (!file_exists($filepath)) {
    die("❌ Файл не найден.");
}

// увеличиваем счётчик скачиваний
$conn->query("UPDATE files SET downloads=downloads+1 WHERE id=$file_id");

// отдаём файл
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"".$file['filename']."\"");
header("Content-Length: " . filesize($filepath));
readfile($filepath);
exit;
?>
