<?php
session_start(); // 1️⃣ Самое первое — до любого вывода

require_once "db.php"; // 2️⃣ После session_start, без вывода

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;

// Подготавливаем запрос
$query = "
    SELECT f.*, u.username AS uploader, u2.username AS shared_user
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    LEFT JOIN users u2 ON f.shared_with = u2.id
    WHERE 
        f.access_type = 'public'
        OR (f.access_type = 'private' AND f.uploaded_by = $1)
        OR (f.access_type = 'user' AND (f.shared_with = $2 OR f.uploaded_by = $3))
    ORDER BY f.id DESC
";

// ⚠️ Для PostgreSQL используем pg_prepare / pg_query_params, а не mysqli
$result = pg_query_params($conn, $query, [$user_id, $user_id, $user_id]);
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
<div class="card">
  <h2>📂 Список файлов</h2>
  <table>
    <tr>
      <th>Имя</th>
      <th>Загрузил</th>
      <th>Размер</th>
      <th>Доступ</th>
      <th>Действия</th>
    </tr>
    <?php while ($row = pg_fetch_assoc($result)): ?>
      <tr>
        <td><?= htmlspecialchars($row['filename']) ?></td>
        <td><?= htmlspecialchars($row['uploader']) ?></td>
        <td><?= round($row['size']/1024, 1) ?> КБ</td>
        <td>
          <?php if ($row['access_type'] === 'public'): ?>
            🌍 Публичный
          <?php elseif ($row['access_type'] === 'private'): ?>
            🔒 Личный
          <?php elseif ($row['access_type'] === 'user'): ?>
            👤 Для <?= htmlspecialchars($row['shared_user'] ?? "удалённого пользователя") ?>
          <?php endif; ?>
        </td>
        <td>
          <a class="btn btn-success" href="download.php?id=<?= $row['id'] ?>">Скачать</a>
          <?php if ($role === 'admin' || $row['uploaded_by'] == $user_id): ?>
            <a class="btn btn-danger" href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Удалить файл?')">Удалить</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
  <br>
  <div class="footer-buttons">
    <a href="upload_form.php" class="btn">Загрузить файл</a>
    <?php if ($role === 'admin'): ?>
      <a href="admin.php" class="btn">Админ-панель</a>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-danger">Выйти</a>
  </div>
</div>
</body>
</html>
