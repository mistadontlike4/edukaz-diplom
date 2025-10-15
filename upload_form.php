<?php
session_start();
require_once "db.php";

// Увеличиваем лимиты на загрузку (до 1 ГБ)
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('memory_limit', '1500M');
ini_set('max_execution_time', '600'); // 10 минут на загрузку

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['file'])) {

    // Проверяем, не было ли ошибок при загрузке
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        die("❌ Ошибка: файл не был загружен. Код ошибки: " . $_FILES['file']['error']);
    }

    // Проверяем, что временный файл существует
    $file_tmp = $_FILES['file']['tmp_name'];
    if (empty($file_tmp) || !file_exists($file_tmp)) {
        die("❌ Ошибка: временный файл не найден.");
    }

    $orig_name = basename($_FILES['file']['name']);
    $file_size = $_FILES['file']['size'];
    $uploaded_by = $_SESSION['user_id'];
    $access_type = $_POST['access_type'] ?? 'public';
    $shared_with = null;

    // Проверяем получателя, если выбран тип "user"
    if ($access_type === 'user' && !empty($_POST['shared_with'])) {
        $username = trim($_POST['shared_with']);
        $res = pg_query_params($conn, "SELECT id FROM users WHERE username = $1", [$username]);
        $row = pg_fetch_assoc($res);
        if (!$row) {
            die("❌ Ошибка: пользователя '$username' не существует.");
        }
        $shared_with = $row['id'];
    }

    // Читаем бинарные данные файла
    $file_data = file_get_contents($file_tmp);

    // Сохраняем файл в базе
    $query = "
        INSERT INTO files (filename, original_name, uploaded_by, size, access_type, shared_with, file_data)
        VALUES ($1, $2, $3, $4, $5, $6, $7)
    ";
    $params = [
        time() . "_" . $orig_name,
        $orig_name,
        $uploaded_by,
        $file_size,
        $access_type,
        $shared_with,
        $file_data
    ];
    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        die("Ошибка при сохранении файла: " . pg_last_error($conn));
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
<div class="card" style="max-width:600px;margin:40px auto;">
  <h2>📤 Загрузить файл</h2>
  <form method="post" enctype="multipart/form-data">
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
  <a href="index.php" class="btn btn-danger" style="margin-top:10px;">⬅ Назад</a>
</div>
</body>
</html>
