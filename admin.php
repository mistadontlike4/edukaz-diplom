<?php
include("db.php");
session_start();
date_default_timezone_set('Asia/Almaty');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: login.php");
  exit;
}

$admin_id = (int)$_SESSION['user_id'];

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$msg = "";

/* ---------- HANDLERS ---------- */
// Создать пользователя
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create_user') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { die("CSRF mismatch"); }

  $u = trim($_POST['username'] ?? '');
  $e = trim($_POST['email'] ?? '');
  $p = $_POST['password'] ?? '';
  $r = $_POST['role'] ?? 'user';

  if ($u==='' || $e==='' || $p==='') {
    $msg = "<div class='alert bad'>Заполните все поля.</div>";
  } else {
    $dup = pg_query_params($conn, "SELECT 1 FROM users WHERE username=$1 OR email=$2", [$u,$e]);
    if ($dup && pg_fetch_row($dup)) {
      $msg = "<div class='alert bad'>Логин или email уже заняты.</div>";
    } else {
      $hash = password_hash($p, PASSWORD_BCRYPT);
      $ok = pg_query_params($conn,
        "INSERT INTO users(username,email,password,role) VALUES($1,$2,$3,$4)",
        [$u,$e,$hash,$r]
      );
      $msg = $ok ? "<div class='alert ok'>Пользователь создан.</div>"
                 : "<div class='alert bad'>Ошибка создания: ".htmlspecialchars(pg_last_error($conn))."</div>";
    }
  }
}

// Удалить пользователя
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='delete_user') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { die("CSRF mismatch"); }
  $uid = (int)($_POST['user_id'] ?? 0);
  if ($uid === $admin_id) {
    $msg = "<div class='alert bad'>Нельзя удалить самого себя.</div>";
  } else {
    // Если нет FK ON DELETE CASCADE — сначала удалите файлы пользователя:
    // pg_query_params($conn, "DELETE FROM files WHERE uploaded_by=$1", [$uid]);
    $ok = pg_query_params($conn, "DELETE FROM users WHERE id=$1", [$uid]);
    $msg = $ok ? "<div class='alert ok'>Пользователь удалён.</div>"
               : "<div class='alert bad'>Ошибка удаления: ".htmlspecialchars(pg_last_error($conn))."</div>";
  }
}

/* ---------- DASH/QUERIES ---------- */
$active_db_label = $is_local ? 'Локальная (edukaz_backup)' : 'Railway';

$users_count = 0; $files_count = 0; $last_file_at = null;
if ($res = pg_query($conn, "SELECT COUNT(*) c FROM users")) $users_count = (int)pg_fetch_assoc($res)['c'];
if ($res = pg_query($conn, "SELECT COUNT(*) c, MAX(uploaded_at) last_at FROM files")) {
  $row = pg_fetch_assoc($res); $files_count=(int)$row['c']; $last_file_at=$row['last_at'];
}

$users = pg_query($conn, "SELECT id,username,email,role,created_at FROM users ORDER BY id DESC");

$files = pg_query($conn, "
  SELECT f.id, f.original_name, f.filename, f.access_type, f.shared_with, f.uploaded_at,
         u.username AS uploader, u2.username AS receiver
  FROM files f
  JOIN users u  ON f.uploaded_by = u.id
  LEFT JOIN users u2 ON f.shared_with = u2.id
  ORDER BY f.uploaded_at DESC NULLS LAST, f.id DESC
");

$logfile = __DIR__ . "/sync_log.txt";
$log_content = file_exists($logfile) ? file($logfile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : [];

function dt($ts){ return $ts ? date('Y-m-d H:i', strtotime($ts)) : '—'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ-панель — EduKaz</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body{font-family:Arial;background:#f6f7fb}
    .topbar{display:flex;gap:8px;justify-content:center;margin:8px 0 14px}
    .btn{padding:8px 14px;border-radius:8px;background:#007bff;color:#fff;text-decoration:none;cursor:pointer;border:none}
    .btn:hover{background:#0056b3}
    .btn-danger{background:#e74c3c}.btn-danger:hover{background:#c0392b}
    .tabs{text-align:center;margin-bottom:14px}
    .tab-btn{display:inline-block;padding:8px 14px;margin:2px;border-radius:8px;background:#007bff;color:#fff;text-decoration:none}
    .tab-btn:hover{background:#0056b3}
    .tab-content{display:none;background:#fff;padding:18px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .active{display:block}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{border:1px solid #ddd;padding:8px;text-align:center}
    .status-box{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:10px 0}
    .status-item{background:#fff;padding:12px 18px;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,.08);min-width:220px;text-align:center}
    .log{background:#111;color:#eee;font-family:monospace;padding:10px;border-radius:8px;max-height:300px;overflow:auto;white-space:pre-wrap}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .alert{margin:8px 0;padding:10px;border-radius:8px}
    .ok{background:#eaf8ee;color:#157347}.bad{background:#fdecea;color:#b02a37}
  </style>
  <script>
    function openTab(id){
      document.querySelectorAll('.tab-content').forEach(el=>el.style.display='none');
      document.getElementById(id).style.display='block';
    }
    function confirmDel(uid,uname){
      if(confirm('Удалить пользователя «'+uname+'»?')) {
        const f = document.getElementById('delForm');
        f.user_id.value = uid; f.submit();
      }
      return false;
    }
    async function runSync(mode){
      const statusEl = document.getElementById('syncStatus');
      const logEl = document.getElementById('syncLog');
      statusEl.style.display = 'block';
      statusEl.innerHTML = "⏳ Выполняю синхронизацию ("+mode+")…";
      logEl.textContent = "Идёт выполнение…";

      try {
        const resp = await fetch('sync.php?mode=' + encodeURIComponent(mode), {cache:'no-store'});
        const text = await resp.text();
        statusEl.innerHTML = text.includes('✅') ?
          "✅ Синхронизация завершена ("+mode+")." :
          "⚠️ Ответ: " + text.replace(/</g,'&lt;');

        const logResp = await fetch('log_view.php?n=200&ts=' + Date.now(), {cache:'no-store'});
        const logText = await logResp.text();
        logEl.textContent = logText || 'Лог пуст.';
        logEl.scrollTop = logEl.scrollHeight;
      } catch (e) {
        statusEl.innerHTML = "❌ Ошибка запроса: " + (e.message || e);
      }
    }
  </script>
</head>
<body>

<div class="card" style="width:92%;max-width:1100px;margin:auto;">
  <h2 style="text-align:center;">👑 Админ-панель EduKaz</h2>

  <div class="topbar">
    <a href="index.php" class="btn">⬅ На главную</a>
    <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
  </div>

  <div style="text-align:center;margin:6px 0;"><?= $db_status ?></div>
  <?= $msg ?>

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

  <!-- USERS -->
  <div id="users" class="tab-content active">
    <h3>👤 Пользователи</h3>

    <form method="post" class="card" style="padding:12px;margin-bottom:10px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="create_user">
      <div class="form-grid">
        <input type="text" name="username" placeholder="Логин" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Пароль" required>
        <select name="role" required>
          <option value="user">user</option>
          <option value="admin">admin</option>
        </select>
      </div>
      <div style="margin-top:8px;text-align:right;">
        <button type="submit" class="btn">➕ Создать пользователя</button>
      </div>
    </form>

    <table>
      <tr><th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Создан</th><th>Действия</th></tr>
      <?php while($u = pg_fetch_assoc($users)): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td><?= dt($u['created_at']) ?></td>
          <td>
            <?php if ((int)$u['id'] !== $admin_id): ?>
              <a href="#" class="btn btn-danger" onclick="return confirmDel(<?= (int)$u['id'] ?>,'<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">🗑 Удалить</a>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>

    <!-- Hidden delete form -->
    <form id="delForm" method="post" style="display:none;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="user_id" value="">
    </form>
  </div>

  <!-- FILES -->
  <div id="files" class="tab-content">
    <h3>📂 Файлы</h3>
    <table>
      <tr>
        <th>Имя</th><th>Загрузил</th><th>Добавлен</th><th>Доступ</th><th>Получатель</th><th>Действия</th>
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

  <!-- MONITOR -->
  <div id="monitor" class="tab-content">
    <h3>🖥 Мониторинг системы</h3>

    <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:10px;">
      <button class="btn" onclick="runSync('both')">🔄 Синхронизация (оба направления)</button>
      <button class="btn" onclick="runSync('pull')">⬇ Получить с Railway → локальная</button>
      <button class="btn" onclick="runSync('push')">⬆ Отправить локальная → Railway</button>
    </div>

    <div id="syncStatus" style="text-align:center;margin:8px 0;display:none;"></div>

    <div class="log" id="syncLog" style="min-height:120px;">
      <div>Здесь появится лог после запуска синхронизации…</div>
    </div>

    <div style="text-align:center;margin-top:14px;">
      <a href="index.php" class="btn">⬅ На главную</a>
      <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
    </div>
  </div>

</div>
</body>
</html>
