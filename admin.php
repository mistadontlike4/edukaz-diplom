<?php
session_start();
require_once "db.php";

// Только администраторы имеют доступ
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

// Получаем всех пользователей
$users = pg_query($conn, "SELECT id, username, email, role, created_at FROM users ORDER BY id DESC");
if (!$users) {
    die("Ошибка при загрузке пользователей: " . pg_last_error($conn));
}

// Получаем все файлы
$files = pg_query($conn, "
    SELECT f.id, f.original_name, f.access_type, f.uploaded_at,
           u.username AS uploader, u2.username AS receiver
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    LEFT JOIN users u2 ON f.shared_with = u2.id
    ORDER BY f.uploaded_at DESC
");
if (!$files) {
    die("Ошибка при загрузке файлов: " . pg_last_error($conn));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ-панель</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width:90%;max-width:1000px;">
  <h2>👤 Пользователи</h2>
  <div class="table-container">
    <table>
      <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Дата регистрации</th></tr>
      <?php while ($u = pg_fetch_assoc($users)): ?>
        <tr>
          <td><?= htmlspecialchars($u['id']) ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td><?= htmlspecialchars($u['created_at']) ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>

<div class="card" style="width:90%;max-width:1000px;">
  <h2>📂 Файлы</h2>
  <div class="table-container">
    <table>
      <tr><th>Имя</th><th>Загрузил</th><th>Доступ</th><th>Получатель</th><th>Действия</th></tr>
      <?php while ($f = pg_fetch_assoc($files)): ?>
        <tr>
          <td><?= htmlspecialchars($f['original_name']) ?></td>
          <td><?= htmlspecialchars($f['uploader']) ?></td>
          <td>
            <?php if ($f['access_type'] === 'public'): ?>
              🌍 Публичный
            <?php elseif ($f['access_type'] === 'private'): ?>
              🔒 Личный
            <?php else: ?>
              👤 Пользовательский
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($f['receiver'] ?? '-') ?></td>
          <td>
            <a class="btn" href="download.php?id=<?= $f['id'] ?>">⬇ Скачать</a>
            <a class="btn btn-danger" href="delete.php?id=<?= $f['id'] ?>" onclick="return confirm('Удалить файл?')">Удалить</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>

<div style="margin-top:20px;text-align:center;">
  <a class="btn" href="index.php">⬅ На главную</a>
  <a class="btn btn-danger" href="logout.php">🚪 Выйти</a>
</div>
</body>
</html>
