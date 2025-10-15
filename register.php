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
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
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
                "INSERT INTO users (username, email, password, role, created_at) VALUES ($1, $2, $3, 'user', now())",
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
    .error { color: #b00020; background:#ffecec; padding:10px; border-radius:6px; margin-bottom:10px; }
    .form-row { margin-bottom:12px; }
  </style>
</head>
<body>
<div class="card" style="max-width:600px; margin:40px auto;">
  <h2>🧾 Регистрация</h2>

  <?php if ($message): ?>
    <div class="error"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form id="registerForm" method="post" onsubmit="return checkPasswords();">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="form-row">
      <input type="text" name="username" placeholder="Логин" required style="width:100%; padding:10px; box-sizing:border-box;">
    </div>

    <div class="form-row">
      <input type="email" name="email" placeholder="Email" required style="width:100%; padding:10px; box-sizing:border-box;">
    </div>

    <div class="form-row">
      <input id="password" type="password" name="password" placeholder="Пароль" required style="width:100%; padding:10px; box-sizing:border-box;">
    </div>

    <div class="form-row">
      <input id="password_confirm" type="password" name="password_confirm" placeholder="Подтвердите пароль" required style="width:100%; padding:10px; box-sizing:border-box;">
    </div>

    <div id="clientError" class="error" style="display:none;"></div>

    <button type="submit" class="btn btn-success" style="padding:10px 18px;">Зарегистрироваться</button>
  </form>

  <div class="nav" style="margin-top:12px;">
    Уже есть аккаунт?
    <a class="btn" href="login.php" style="display:inline-block; margin-left:8px;">Войти</a>
  </div>
</div>

<script>
function checkPasswords() {
  const pass = document.getElementById('password').value;
  const conf = document.getElementById('password_confirm').value;
  const clientErr = document.getElementById('clientError');

  if (pass !== conf) {
    clientErr.textContent = "ваши пароли не одинаковые";
    clientErr.style.display = 'block';
    return false; // блокируем отправку формы
  }
  clientErr.style.display = 'none';
  return true;
}
</script>
</body>
</html>
