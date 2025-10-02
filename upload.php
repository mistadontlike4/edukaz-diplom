<?php
include("db.php");
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

if ($_SERVER["REQUEST_METHOD"]=="POST" && isset($_FILES['file'])) {
    $orig = basename($_FILES['file']['name']);
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir);
    $target = $uploadDir . time() . "_" . $orig;

    $access_type = isset($_POST['access_type']) ? $_POST['access_type'] : 'public';
    $shared_with = null;

    if ($access_type === 'user' && !empty($_POST['shared_username'])) {
        $u = trim($_POST['shared_username']);
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
        $stmt->bind_param("s",$u);
        $stmt->execute();
        $stmt->bind_result($uid);
        if ($stmt->fetch()) { $shared_with = $uid; }
        $stmt->close();
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        $stmt = $conn->prepare("INSERT INTO files(filename,original_name,uploaded_by,access_type,shared_with) VALUES(?,?,?,?,?)");
        $stmt->bind_param("ssisi",$target,$orig,$_SESSION['user_id'],$access_type,$shared_with);
        $stmt->execute();
        header("Location: index.php");
        exit;
    } else {
        echo "<p class='msg error'>Ошибка при загрузке!</p>";
    }
}
?>
