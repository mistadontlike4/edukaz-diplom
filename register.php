<?php
session_start();
require_once "db.php";

// Генерация CSRF токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Проверка CSRF токена
    if (
        !isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        die("CSRF token mismatch");
    }

    $u = trim($_POST['username'] ?? '');
    $e = trim($_POST['email'] ?? '');
    $p_raw = $_POST['password'] ?? '';
    $p_confirm = $_POST['password_confirm'] ?? '';

    // Серверная проверка совпадения паролей
    if ($p_raw !== $p_confirm) {
        $message = "ваши пароли не одинаковые";
    } elseif (empty($u) || empty($e) || empty($p_raw)) {
        $message = "Пожалуйста, заполните все поля!";
    } else {
        $p = password_hash($p_raw, PASSWORD_BCRYPT);

        // Проверяем, не занят ли логин или email
        $check = pg_query_params(
            $conn,
            "SELECT id FROM users WHERE username = $1 OR email = $2",
            [$u, $e]
        );

        if (!$check) {
            die("Ошибка запроса: " . pg_last_error($conn));
        }

        if (pg_num_rows($check) > 0) {
            $message = "❌ Логин или email уже заняты!";
        } else {
            // Вставка нового пользователя
            $insert = pg_query_params(
                $conn,
                "INSERT INTO users (username, email, password, role, created_at) VALUES ($1, $2, $3, 'user', NOW())",
                [$u, $e, $p]
            );

            if ($insert) {
                header("Location: login.php?success=1");
                exit;
            } else {
                $message = "Ошибка при регистрации: " . pg_last_error($conn);
            }
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
  <style>
    /* Небольшие стили для ошибки */
    .error { color: #b00020; background:#ffecec; padding:10px; border-radius:6px; margin-bottom:10px; }
    .form-row { margin-bottom:12px; }
    input[type="text"], input[type="email"], input[type="password"] { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; }
    .btn { display:inline-block; padding:8px 14px; border-radius:6px; background:#0d6efd; color:#fff; text-decoration:none; border:none; cursor:pointer; }
    .btn-success { background:#0d6efd; }
  </style>
  <script>
    // Клиентская проверка: пароль и подтверждение
    function validateForm(event) {
      var p = document.getElementById('password').value;
      var pc = document.getElementById('password_confirm').value;
      var errBox = document.getElementById('client_error');
      if (p !== pc) {
        errBox.textContent = 'ваши пароли не одинаковые';
        errBox.style.display = 'block';
        event.preventDefault();
        return false;
      }
      errBox.style.display = 'none';
      return true;
    }

    // У
