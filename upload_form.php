<?php
include("db.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $filename = $_FILES['file']['name'];
    $filesize = $_FILES['file']['size'];
    $tmp_name = $_FILES['file']['tmp_name'];
    $uploaded_by = $_SESSION['user_id'];
    $access_type = $_POST['access_type'];
    $shared_with_id = null;

    // Если выбрали "отправить пользователю" → ищем id по логину
    if ($access_type === "shared" && !empty($_POST['shared_with'])) {
        $shared_username = trim($_POST['shared_with']);
        $stmt_user = $conn->prepare("SELECT id FROM users WHERE username=?");
        $stmt_user->bind_param("s", $shared_username);
        $stmt_user->execute();
        $stmt_user->bind_result($shared_with_id);
        $stmt_user->fetch();
        $stmt_user->close();

        if (!$shared_with_id) {
            die("❌ Ошибка: пользователя '$shared_username' не существует.");
        }
    }

    // Создаём папку при необходимости
    if (!is_dir("uploads")) {
        mkdir("uploads");
    }
    move_uploaded_file($tmp_name, "uploads/" . $filename);

    // Запись в БД
    $stmt = $conn->prepare("INSERT INTO files (filename, uploaded_by, size, access_type, shared_with) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siisi", $filename, $uploaded_by, $filesize, $access_type, $shared_with_id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Загрузка файла</title>
  <link rel="stylesheet" href="style.css">
  <script>
    function toggleSharedField() {
      const select = document.getElementById("access_type");
      const sharedField = document.getElementById("shared_field");
      sharedField.style.display = (select.value === "shared") ? "block" : "none";
    }
  </script>
</head>
<body>
<div class="card">
  <h2>📤 Загрузить файл</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <select name="access_type" id="access_type" onchange="toggleSharedField()" required>
      <option value="public">🌍 Публичный</option>
      <option value="private">🔒 Личный</option>
      <option value="shared">👤 Отправить пользователю</option>
    </select>
    <div id="shared_field" style="display:none;">
      <input type="text" name="shared_with" placeholder="Введите логин пользователя">
    </div>
    <button type="submit" class="btn btn-success">Загрузить</button>
  </form>
  <a href="index.php" class="btn btn-danger">Назад</a>
</div>
</body>
</html>
