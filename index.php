<?php
include("db.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;

// Получаем список файлов (пример — под PostgreSQL)
$query = "
    SELECT f.*, u.username AS uploader, u2.username AS shared_user
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    LEFT JOIN users u2 ON f.shared_with = u2.id
    WHERE 
        f.access_type = 'public'
        OR (f.access_type = 'private' AND f.uploaded_by = $1)
        OR (f.access_type = 'user' AND (f.shared_with = $1 OR f.uploaded_by = $1))
    ORDER BY f.id DESC
";
$result = pg_query_params($conn, $query, [$user_id]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>EduKaz — Файлы</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .status-bar {
      text-align: center;
      margin: 10px auto;
      padding: 8px;
      border-radius: 10px;
      background: #f8f8f8;
      width: fit-content;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      font-family: Arial;
      font-size: 14px;
    }
    .file-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    .file-table th, .file-table td {
      border: 1px solid #ccc;
      padding: 8px;
      text-align: center;
    }
    .footer-buttons {
      text-align: center;
      margin-top: 20px;
    }
    .btn { text-decoration: none; padding: 6px 12px; border-radius: 5px; background: #007bff; color: white; }
    .btn:hover { background: #0056b3; }
    .btn-danger { background: #e74c3c; }
    .btn-danger:hover { background: #c0392b; }
    .btn-success { background: #2ecc71; }
    .btn-success:hover { background: #27ae60; }
  </style>
</head>
<body>

<div class="card">
  <h2>📂 Список файлов</h2>

  <div class="status-bar"><?= $db_status ?></div>

  <table class="file-table">
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
        <td><?= round($row['size']/1024,1) ?> КБ</td>
        <td>
          <?php if ($row['access_type']==='public'): ?>
            🌍 Публичный
          <?php elseif ($row['access_type']==='private'): ?>
            🔒 Личный
          <?php elseif ($row['access_type']==='user'): ?>
            👤 Для <?= htmlspecialchars($row['shared_user'] ?? "удалённого пользователя") ?>
          <?php endif; ?>
        </td>
        <td>
          <a class="btn btn-success" href="download.php?id=<?= $row['id'] ?>">Скачать</a>
          <?php if ($role==='admin' || $row['uploaded_by']==$user_id): ?>
            <a class="btn btn-danger" href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Удалить файл?')">Удалить</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>

  <div class="footer-buttons">
    <a href="upload_form.php" class="btn">Загрузить файл</a>
    <?php if ($role==='admin'): ?>
      <a href="admin.php" class="btn">Админ-панель</a>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-danger">Выйти</a>
  </div>
</div>

</body>
</html>
