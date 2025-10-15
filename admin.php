<?php
include("db.php");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Загружаем пользователей и файлы
$users = pg_query($conn, "SELECT * FROM users ORDER BY id DESC");
$files = pg_query($conn, "
    SELECT f.*, u.username AS uploader, u2.username AS receiver
    FROM files f
    JOIN users u ON f.uploaded_by=u.id
    LEFT JOIN users u2 ON f.shared_with=u2.id
    ORDER BY f.uploaded_at DESC
");

// Загружаем логи синхронизации
$logfile = __DIR__ . "/sync_log.txt";
$log_content = file_exists($logfile) ? file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Админ-панель — EduKaz</title>
<link rel="stylesheet" href="style.css">
<style>
  body { font-family: Arial; background:#f6f7fb; }
  .tabs { text-align:center; margin-bottom:20px; }
  .tab-btn {
    display:inline-block; padding:8px 15px; margin:2px;
    border-radius:6px; background:#007bff; color:white;
    text-decoration:none; transition:0.2s;
  }
  .tab-btn:hover { background:#0056b3; }
  .tab-content { display:none; background:white; padding:20px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
  .active { display:block; }
  table { width:100%; border-collapse:collapse; margin-top:10px; }
  th, td { border:1px solid #ccc; padding:8px; text-align:center; }
  .log {
    background:#1e1e1e; color:#eee; font-family:monospace;
    padding:10px; border-radius:8px; max-height:300px; overflow-y:auto;
  }
  .btn {
    padding:6px 12px; border-radius:5px; background:#007bff;
    color:white; text-decoration:none; transition:0.2s;
  }
  .btn:hover { background:#0056b3; }
  .btn-danger { background:#e74c3c; }
  .btn-danger:hover { background:#c0392b; }
  .btn-success { background:#2ecc71; }
  .btn-success:hover { background:#27ae60; }

  .topbar {
    text-align:center;
    margin:10px 0 20px 0;
  }

  .alert {
    text-align:center;
    padding:10px;
    border-radius:8px;
    margin:10px auto;
    width:90%;
    max-width:800px;
    color:white;
    font-weight:bold;
    animation: fadeOut 5s forwards;
  }
  .success { background:#2ecc71; }
  .error { background:#e74c3c; }

  @keyframes fadeOut {
    0% { opacity: 1; }
    80% { opacity: 1; }
    100% { opacity: 0; display:none; }
  }
</style>
<script>
function openTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  document.getElementById(tabId).style.display = 'block';
}
</script>
</head>
<body>

<?php if (isset($_GET['sync'])): ?>
<div class="alert <?= $_GET['sync']=='success' ? 'success' : 'error' ?>">
  <?= $_GET['sync']=='success' ? '✅ Синхронизация успешно выполнена!' : '❌ Ошибка при синхронизации!' ?>
</div>
<?php endif; ?>

<div class="card" style="width:90%;max-width:1100px;margin:auto;">
  <h2 style="text-align:center;">👑 Админ-панель EduKaz</h2>

  <!-- Верхняя панель -->
  <div class="topbar">
    <a href="index.php" class="btn btn-success">🏠 На главную</a>
    <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
  </div>

  <div class="tabs">
    <a href="#" class="tab-btn" onclick="openTab('users')">👤 Пользователи</a>
    <a href="#" class="tab-btn" onclick="openTab('files')">📂 Файлы</a>
    <a href="#" class="tab-btn" onclick="openTab('monitor')">🖥 Мониторинг системы</a>
  </div>

  <!-- Пользователи -->
  <div id="users" class="tab-content active">
    <h3>👤 Пользователи</h3>
    <table>
      <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Дата регистрации</th></tr>
      <?php while($u = pg_fetch_assoc($users)): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td><?= $u['created_at'] ?></td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>

  <!-- Файлы -->
  <div id="files" class="tab-content">
    <h3>📂 Файлы</h3>
    <table>
      <tr><th>Имя файла</th><th>Загрузил</th><th>Доступ</th><th>Получатель</th><th>Действия</th></tr>
      <?php while($f = pg_fetch_assoc($files)): ?>
      <tr>
        <td><?= htmlspecialchars($f['original_name']) ?></td>
        <td><?= htmlspecialchars($f['uploader']) ?></td>
        <td><?= htmlspecialchars($f['access_type']) ?></td>
        <td><?= htmlspecialchars($f['receiver'] ?? '-') ?></td>
        <td>
          <a class="btn btn-success" href="download.php?id=<?= $f['id'] ?>">⬇ Скачать</a>
          <a class="btn btn-danger" href="delete.php?id=<?= $f['id'] ?>" onclick="return confirm('Удалить файл?')">🗑 Удалить</a>
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
    <div style="text-align:center;margin-top:15px;">
      <a href="scheduler_sync.php" class="btn">🔄 Запустить синхронизацию сейчас</a>
    </div>
  </div>
</div>

</body>
</html>
