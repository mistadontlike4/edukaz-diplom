<?php
include("db.php");
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role']!=="admin") { header("Location: login.php"); exit; }

$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
$files = $conn->query("SELECT f.*, u.username, u2.username AS receiver 
                       FROM files f
                       JOIN users u ON f.uploaded_by=u.id
                       LEFT JOIN users u2 ON f.shared_with=u2.id
                       ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ-панель</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width: 90%; max-width: 1000px;">
  <h2>👤 Пользователи</h2>
  <div class="table-container">
  <table>
    <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Дата</th></tr>
    <?php while($u = $users->fetch_assoc()): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= $u['role'] ?></td>
        <td><?= $u['created_at'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>

<div class="card" style="width: 90%; max-width: 1000px;">
  <h2>📂 Файлы</h2>
  <div class="table-container">
  <table>
    <tr><th>Имя</th><th>Загрузил</th><th>Доступ</th><th>Получатель</th><th>Действия</th></tr>
    <?php while($f = $files->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($f['original_name']) ?></td>
        <td><?= htmlspecialchars($f['username']) ?></td>
        <td><?= $f['access_type'] ?></td>
        <td><?= $f['receiver'] ?: "-" ?></td>
        <td>
          <a class="btn" href="download.php?id=<?= $f['id'] ?>">⬇ Скачать</a>
          <a class="btn btn-danger" href="delete.php?id=<?= $f['id'] ?>">Удалить</a>
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
