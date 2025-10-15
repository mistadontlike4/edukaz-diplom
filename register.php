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

    if (empty($u) || empty($e) || empty($p_raw)) {
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
                "INSERT INTO users (username, email, password, role) VALUES ($1, $2, $3, 'user')",
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
</head>
<body>
<div class="card">
  <h2>🧾 Регистрация</h2>
  <?php if ($message): ?>
    <div class="error"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="text" name="username" placeholder="Логин" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Пароль" required>
    <button type="submit" class="btn btn-success">Зарегистрироваться</button>
  </form>
  <div class="nav">
    Уже есть аккаунт?
    <a class="btn" href="login.php">Войти</a>
  </div>
</div>
</body>
</html>
