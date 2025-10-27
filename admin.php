<?php
include("db.php");
session_start();
date_default_timezone_set('Asia/Almaty');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.php");
  exit;
}

// ----- виджет мониторинга: активная БД + счётчики -----
$active_db_label = $is_local ? 'Локальная (edukaz_backup)' : 'Railway';
$users_count = 0;
$files_count = 0;
$last_file_at = null;

$res = pg_query($conn, "SELECT COUNT(*) AS c FROM users");
if ($res) { $users_count = (int)pg_fetch_assoc($res)['c']; }

$res = pg_query($conn, "SELECT COUNT(*) AS c, MAX(uploaded_at) AS last_at FROM files");
if ($res) {
  $row = pg_fetch_assoc($res);
  $files_count = (int)$row['c'];
  $last_file_at = $row['last_at'];
}

// ----- данные для таблиц -----
$users = pg_query($conn, "SELECT id, username, email, role, created_at FROM users ORDER BY id DESC");

$files = pg_query($conn, "
  SELECT f.id, f.original_name, f.filename, f.access_type, f.shared_with, f.uploaded_at,
         u.username AS uploader, u2.username AS receiver
  FROM files f
  JOIN users u  ON f.uploaded_by = u.id
  LEFT JOIN users u2 ON f.shared_with = u2.id
  ORDER BY f.uploaded_at DESC NULLS LAST, f.id DESC
");

// ----- лог синхронизации -----
$logfile = __DIR__ . "/sync_log.txt";
$log_content = file_exists($logfile)
  ? file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
  : [];

function dt($ts) { return $ts ? date('Y-m-d H:i', strtotime($ts)) : '—'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ-панель — EduKaz</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { font-family: Arial; background:#f6f7fb; }
    .tabs{ text-align:center; margin-bottom:16px; }
    .tab-btn{ display:inline-block; padding:8px 14px; margin:2px; border-radius:8px; background:#007bff; color:#fff; text-decoration:none }
    .tab-btn:hover{ background:#0056b3 }
    .tab-content{ display:none; background:#fff; padding:18px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.1) }
    .active{ display:block }
    table{ width:100%; border-collapse:collapse; margin-top:10px }
    th,td{ border:1px solid #ddd; padding:8px; text-align:center }
    .log{ background:#111; color:#eee; font-family:monospace; padding:10px; border-radius:8px; max-height:300px; overflow:auto }
    .btn{ padding:6px 12px; border-radius:6px; background:#007bff; color:#fff; text-decoration:none }
    .btn:hover{ background:#0056b3 }
    .btn-danger{ background:#e74c3c } .btn-danger:hover{ background:#c0392b }
    .status-box{ display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin:10px 0 }
    .status-item{ background:#fff; padding:12px 18px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,.08); min-width:220px; text-align:center }
  </style>
  <script>
    function openTab(id){
      document.querySelectorAll('.tab-content').forEach(el => el.style.display='none');
      document.getElementById(id).style.display='block';
    }
  </script>
</head>
<body>

<div class="card" style="width:92%;max-width:1100px;margin:auto;">
  <h2 style="text-align:center;">👑 Админ-панель EduKaz</h2>
  <div style="text-align:center;margin:6px 0;"><?= $db_status ?></div>

  <div class="status-box">
    <div class="status-item"><strong>Активная БД</strong><br><?= $is_local ? '🟠 ' : '🟢 ' ?><?= htmlspecialchars($active_db_label) ?></div>
    <div class="status-item"><strong>Пользователи</strong><br>👤 <?= $users_count ?></div>
    <div class="status-item"><strong>Файлы</strong><br>📂 <?= $files_count ?></div>
    <div class="status-item"><strong>Последний файл</strong><br>🕒 <?= dt($last_file_at) ?></div>
  </div>

  <div class="tabs">
    <a class="tab-btn" href="#" onclick="openTab('users')">👤 Пользователи</a>
    <a class="tab-btn" href="#" onclick="openTab('files')">📂 Файлы</a>
    <a class="tab-btn" href="#" onclick="openTab('monitor')">🖥 Мониторинг системы</a>
  </div>

  <!-- Пользователи -->
  <div id="users" class="tab-content active">
    <h3>👤 Пользователи</h3>
    <table>
      <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Дата</th></tr>
      <?php while($u = pg_fetch_assoc($users)): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td><?= dt($u['created_at']) ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>

  <!-- Файлы -->
  <div id="files" class="tab-content">
    <h3>📂 Файлы</h3>
    <table>
      <tr>
        <th>Имя</th>
        <th>Загрузил</th>
        <th>Добавлен</th>
        <th>Доступ</th>
        <th>Получатель</th>
        <th>Действия</th>
      </tr>
      <?php while($f = pg_fetch_assoc($files)): ?>
        <tr>
          <td><?= htmlspecialchars($f['original_name']) ?></td>
          <td><?= htmlspecialchars($f['uploader']) ?></td>
          <td><?= dt($f['uploaded_at']) ?></td>
          <td><?= htmlspecialchars($f['access_type']) ?></td>
          <td><?= $f['receiver'] ? htmlspecialchars($f['receiver']) : '-' ?></td>
          <td>
            <a class="btn" href="download.php?id=<?= (int)$f['id'] ?>">⬇ Скачать</a>
            <a class="btn btn-danger" href="delete.php?id=<?= (int)$f['id'] ?>" onclick="return confirm('Удалить файл?')">🗑 Удалить</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>

  <!-- Мониторинг -->
  <div id="monitor" class="tab-content">
    <h3>🖥 Мониторинг системы</h3>
    <p>Последние 20 записей синхронизации:</p>
    <div class="log">
      <?php
        if (empty($log_content)) {
          echo "<div>Лог пуст.</div>";
        } else {
          foreach (array_reverse(array_slice($log_content, -20)) as $line) {
            echo "<div>" . htmlspecialchars($line) . "</div>";
          }
        }
      ?>
    </div>
    <div style="text-align:center;margin-top:14px;">
      <a href="scheduler_sync.php" class="btn">🔄 Запустить синхронизацию сейчас</a>
      <a href="index.php" class="btn btn-danger">⬅ На главную</a>
      <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
    </div>
  </div>
</div>

</body>
</html>
