<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Обработка отправки формы
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['file'])) {
    $orig = basename($_FILES['file']['name']);
    $uploadDir = "uploads/";

    // Создаём папку, если нет
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $target = $uploadDir . time() . "_" . $orig;
    $access_type = $_POST['access_type'] ?? 'public';
    $shared_with = null;

    // Если выбрано "для пользователя" — ищем ID получателя
    if ($access_type === 'user' && !empty($_POST['shared_username'])) {
        $u = trim($_POST['shared_username']);
        $res = pg_query_params($conn, "SELECT id FROM users WHERE username = $1", [$u]);
        if ($res && ($row = pg_fetch_assoc($res))) {
            $shared_with = $row['id'];
        } else {
            echo "<p class='msg error'>❌ Ошибка: пользователь не найден.</p>";
            exit;
        }
    }

    // Перемещаем файл
    if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        $query = "
            INSERT INTO files (filename, original_name, uploaded_by, access_type, shared_with)
            VALUES ($1, $2, $3, $4, $5)
        ";
        $params = [$target, $orig, $_SESSION['user_id'], $access_type, $shared_with];
        $res = pg_query_params($conn, $query, $params);

        if ($res) {
            header("Location: index.php");
            exit;
        } else {
            echo "<p class='msg error'>Ошибка записи в базу: " . pg_last_error($conn) . "</p>";
        }
    } else {
        echo "<p class='msg error'>Ошибка при загрузке файла на сервер!</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Загрузка файла</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
  <h2>📤 Загрузить файл</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <select name="access_type" id="access_type" onchange="toggleShared()" required>
      <option value="public">🌍 Публичный</option>
      <option value="private">🔒 Личный</option>
      <option value="user">👤 Отправить пользователю</option>
    </select>
    <div id="shared_user" style="display:none;">
      <input type="text" name="shared_username" placeholder="Введите логин получателя">
    </div>
    <button type="submit" class="btn btn-success">Загрузить</button>
  </form>
  <a href="index.php" class="btn btn-danger">⬅ Назад</a>
</div>

<script>
function toggleShared() {
  const sel = document.getElementById('access_type');
  const field = document.getElementById('shared_user');
  field.style.display = sel.value === 'user' ? 'block' : 'none';
}
</script>
</body>
</html>
