<?php
include("db.php");
session_start();
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $u = $_POST['username'];
  $p = $_POST['password'];

  $stmt = $conn->prepare("SELECT id,password,role FROM users WHERE username=? OR email=?");
  $stmt->bind_param("ss", $u, $u);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $hash, $role);
    $stmt->fetch();
    if (password_verify($p, $hash)) {
      $_SESSION['user_id'] = $id;
      $_SESSION['username'] = $u;
      $_SESSION['role'] = $role;
      header("Location: index.php");
      exit;
    } else {
      $message = "Неверный пароль!";
    }
  } else {
    $message = "Пользователь не найден!";
  }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Вход</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
  <h2>Вход</h2>
  <form method="post">
    <input type="text" name="username" placeholder="Логин или Email" required>
    <input type="password" name="password" placeholder="Пароль" required>
    <button type="submit">Войти</button>
  </form>
  <div class="nav">
    Нет аккаунта? <a class="btn" href="register.php">Зарегистрироваться</a>
  </div>
  <?php if($message): ?><p class="msg error"><?= $message ?></p><?php endif; ?>
</div>
</body>
</html>
