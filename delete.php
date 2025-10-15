<?php
session_start();
require_once "db.php";

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Проверяем наличие параметра id
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Получаем информацию о файле
    $query = "SELECT id, filename, uploaded_by FROM files WHERE id = $1";
    $result = pg_query_params($conn, $query, [$id]);

    if ($result && ($row = pg_fetch_assoc($result))) {
        $filepath = "uploads/" . $row['filename'];

        // Проверяем права: админ или владелец
        if ($role === 'admin' || $row['uploaded_by'] == $user_id) {
            // Удаляем сам файл с диска, если существует
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Удаляем запись из базы данных
            pg_query_params($conn, "DELETE FROM files WHERE id = $1", [$id]);
        }
    }
}

// Возврат на главную страницу
header("Location: index.php");
exit;
?>
