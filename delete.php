<?php
include("db.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM files WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // проверка прав
        if ($_SESSION['role'] == 'admin' || $row['uploaded_by'] == $_SESSION['user_id']) {
            $path = "uploads/" . $row['filename'];
            if (file_exists($path)) unlink($path);
            $conn->query("DELETE FROM files WHERE id=$id");
        }
    }
}
header("Location: index.php");
exit;
?>
