<?php
include("db.php");
session_start();
date_default_timezone_set('Asia/Almaty');

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'user';

// список файлов доступных пользователю
$sql = "
  SELECT f.id, f.filename, f.original_name, f.size, f.uploaded_by, f.access_type, f.shared_with, f.uploaded_at,
         u.username AS uploader, u2.username AS shared_user
  FROM files f
  JOIN users u ON f.uploaded_by = u.id
  LEFT JOIN users u2 ON f.shared_with = u2.id
  WHERE
      f.access_type = 'public'
   OR (f.access_type = 'private' AND f.uploaded_by = $user_id)
   OR (f.access_type = 'user' AND (f.shared_with = $user_id OR f.uploaded_by = $user_id))
  ORDER BY f.uploaded_at DESC NULLS LAST, f.id DESC
";
$result = pg_query($conn, $sql);

function dt($ts) { return $ts ? date('Y-m-d H:i', strtotime($ts)) : '—'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Файлы — EduKaz</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="max-width:1000px; width:94%; margin:auto;">
  <h2 style="text-align:center;">📂 Список файлов</h2>
  <div style="text-align:center;margin:6px 0;"><?= $db_status ?></div>

  <table>
    <tr>
      <th>Имя</th>
      <th>Загрузил</th>
      <th>Размер</th>
      <th>Добавлен</th>
      <th>Доступ</th>
      <th>Действия</th>
    </tr>
    <?php while($row = pg_fetch_assoc($result)): ?>
      <tr>
        <td><?= htmlspecialchars($row['original_name'] ?: $row['filename']) ?></td>
        <td><?= htmlspecialchars($row['uploader']) ?></td>
        <td><?= $row['size'] ? round($row['size']/1024,1) . ' КБ' : '—' ?></td>
        <td><?= dt($row['uploaded_at']) ?></td>
        <td>
          <?php if ($row['access_type']==='public'): ?>
            🌍 Публичный
          <?php elseif ($row['access_type']==='private'): ?>
            🔒 Личный
          <?php elseif ($row['access_type']==='user'): ?>
            👤 Для <?= htmlspecialchars($row['shared_user'] ?? "—") ?>
          <?php endif; ?>
        </td>
        <td>
          <a class="btn btn-success" href="download.php?id=<?= (int)$row['id'] ?>">Скачать</a>
          <?php if ($role==='admin' || (int)$row['uploaded_by']===$user_id): ?>
            <a class="btn btn-danger" href="delete.php?id=<?= (int)$row['id'] ?>" onclick="return confirm('Удалить файл?')">Удалить</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>

  <div class="footer-buttons" style="text-align:center;margin-top:12px;">
    <a href="upload_form.php" class="btn">Загрузить файл</a>
    <?php if ($role==='admin'): ?>
      <a href="admin.php" class="btn">Админ-панель</a>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-danger">Выйти</a>
  </div>
</div>
</body>
</html>
