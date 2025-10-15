<?php
session_start();
require_once "db.php";

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Удаление файла (только для админа)
if (isset($_GET['delete']) && $role === 'admin') {
    $file_id = intval($_GET['delete']);

    // Получаем путь к файлу
    $res = pg_query_params($conn, "SELECT filename FROM files WHERE id = $1", [$file_id]);
    if ($res && ($row = pg_fetch_assoc($res))) {
        $filepath = "uploads/" . $row['filename'];
        if (file_exists($filepath)) {
            unlink($filepath); // удаляем сам файл с диска
        }
    }

    // Удаляем запись из БД
    pg_query_params($conn, "DELETE FROM files WHERE id = $1", [$file_id]);

    header("Location: files.php");
    exit;
}

// Получаем список файлов
$query = "
    SELECT f.id, f.filename, f.original_name, f.access_type, f.uploaded_at, u.username
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    WHERE f.access_type = 'public' OR f.uploaded_by = $1
    ORDER BY f.uploaded_at DESC
";
$result = pg_query_params($conn, $query, [$user_id]);
if (!$result) {
    die("Ошибка запроса: " . pg_last_error($conn));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Файлы</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="max-width:900px; width:95%; text-align:left;">
  <h2>📂 Список файлов</h2>
  <table class="file-list">
    <tr>
      <th>Имя файла</th>
      <th>Загрузил</th>
      <th>Тип доступа</th>
      <th>Дата</th>
      <th>Действия</th>
    </tr>
    <?php while ($f = pg_fetch_assoc($result)): ?>
    <tr>
      <td><?= htmlspecialchars($f['original_name']) ?></td>
      <td><?= htmlspecialchars($f['username']) ?></td>
      <td>
        <?php if ($f['access_type'] === 'public'): ?>
          🌍 Публичный
        <?php elseif ($f['access_type'] === 'private'): ?>
          🔒 Личный
        <?php else: ?>
          👤 Для пользователя
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($f['uploaded_at']) ?></td>
      <td>
        <a class="btn ok" href="download.php?id=<?= $f['id'] ?>">⬇ Скачать</a>
        <?php if ($role === 'admin'): ?>
          <a class="btn danger" href="files.php?delete=<?= $f['id'] ?>" onclick="return confirm('Удалить файл?')">🗑 Удалить</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  <div style="margin-top:20px; text-align:center;">
    <a href="index.php" class="btn danger">⬅ Назад</a>
  </div>
</div>
</body>
</html>
