<?php
session_start();
require_once "db.php";

// Если пользователь уже вошёл — перенаправляем
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Введите логин и пароль.";
    } else {
        // Проверяем наличие пользователя
        $query = "SELECT id, username, password, role FROM users WHERE username = $1";
        $result = pg_query_params($conn, $query, [$username]);

        if (!$result) {
            die("Ошибка запроса: " . pg_last_error($conn));
        }

        $user = pg_fetch_assoc($result);
        if ($user && password_verify($password, $user['password'])) {
            // Создаём сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header("Location: index.php");
            exit;
        } else {
            $error = "❌ Неверный логин или пароль.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Вход в систему</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
  <h2>🔐 Вход в систему EduKaz</h2>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="text" name="username" placeholder="Логин" required>
    <input type="password" name="password" placeholder="Пароль" required>
    <button type="submit" class="btn btn-success">Войти</button>
  </form>
  <p>Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
</div>
</body>
</html>
