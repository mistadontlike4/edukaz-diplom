<?php
session_start();
require_once "db.php";

// Генерация CSRF токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Обработка формы
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        die("CSRF token mismatch");
    }

    $original_name = basename($_FILES['file']['name']);
    $filesize = $_FILES['file']['size'];
    $tmp_name = $_FILES['file']['tmp_name'];
    $uploaded_by = $_SESSION['user_id'];
    $access_type = $_POST['access_type'];
    $shared_with_id = null;

    // Если выбрано «Отправить пользователю»
    if ($access_type === "user" && !empty($_POST['shared_with'])) {
        $shared_username = trim($_POST['shared_with']);

        $res = pg_query_params($conn, "SELECT id FROM users WHERE username = $1", [$shared_username]);
        if (!$res) {
            die("Ошибка запроса: " . pg_last_error($conn));
        }
        $row = pg_fetch_assoc($res);
        $shared_with_id = $row['id'] ?? null;

        if (!$shared_with_id) {
            die("❌ Ошибка: пользователя '$shared_username' не существует.");
        }
    }

    // Создание папки uploads
    if (!is_dir("uploads")) {
        mkdir("uploads");
    }

    $filename = time() . "_" . $original_name;
    if (!move_uploaded_file($tmp_name, "uploads/" . $filename)) {
        die("Ошибка при загрузке файла");
    }

    // Запись в базу данных PostgreSQL
    $query = "
        INSERT INTO files (filename, original_name, uploaded_by, size, access_type, shared_with)
        VALUES ($1, $2, $3, $4, $5, $6)
    ";
    $params = [$filename, $original_name, $uploaded_by, $filesize, $access_type, $shared_with_id];
    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        die("Ошибка вставки: " . pg_last_error($conn));
    }

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
      sharedField.style.display = (select.value === "user") ? "block" : "none";
    }
  </script>
</head>
<body>
<div class="card">
  <h2>📤 Загрузить файл</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="file" name="file" required>
    <select name="access_type" id="access_type" onchange="toggleSharedField()" required>
      <option value="public">🌍 Публичный</option>
      <option value="private">🔒 Личный</option>
      <option value="user">👤 Отправить пользователю</option>
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
