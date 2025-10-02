<?php
include("db.php");
session_start();
$csrf_token = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32));
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = $csrf_token;
}
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF token mismatch");
  }
  $u = trim($_POST['username']);
  $e = trim($_POST['email']);
  $p = password_hash($_POST['password'], PASSWORD_BCRYPT);

  $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
  $stmt->bind_param("ss", $u, $e);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $message = "Логин или email уже заняты!";
  } else {
    $stmt = $conn->prepare("INSERT INTO users (username,email,password,role) VALUES (?,?,?,'user')");
    $stmt->bind_param("sss", $u, $e, $p);
    if ($stmt->execute()) {
      header("Location: login.php?success=1");
      exit;
    } else {
      $message = "Ошибка при регистрации!";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Регистрация</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
  <h2>Регистрация</h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="text" name="username" placeholder="Логин" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Пароль" required>
    <button type="submit">Зарегистрироваться</button>
  </form>
  <div class="nav">
    Уже есть аккаунт? <a class="btn" href="login.php">Войти</a
