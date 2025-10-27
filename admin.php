<?php
// admin.php — единая админ-панель: Пользователи / Файлы / Мониторинг
// Требует: session['role']='admin', style.css, sync.php (поддерживает ?plain=1), db.php (PostgreSQL $conn)

session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: login.php"); exit;
}

// CSRF
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Определяем, запущены ли мы на Railway (прод)
$on_railway = isset($_ENV['RAILWAY_ENVIRONMENT'])
           || isset($_SERVER['RAILWAY_ENVIRONMENT'])
           || (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'railway.app'));

// --- Обработка действий администратора --- //
$message = "";

// Создание пользователя
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='create_user') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { die("CSRF token mismatch"); }
  $u = trim($_POST['username'] ?? '');
  $e = trim($_POST['email'] ?? '');
  $p = $_POST['password'] ?? '';
  $r = $_POST['role'] ?? 'user';

  if ($u==='' || $e==='' || $p==='') {
    $message = "⚠ Заполните все поля.";
  } else {
    $ph = password_hash($p, PASSWORD_BCRYPT);
    $res = @pg_query_params($conn,
      "INSERT INTO users (username,email,password,role) VALUES ($1,$2,$3,$4)",
      [$u,$e,$ph,$r]
    );
    if ($res) {
      $message = "✅ Пользователь «".h($u)."» создан.";
    } else {
      $err = pg_last_error($conn);
      if (str_contains((string)$err, "users_username_key")) $message = "❌ Такой логин уже есть.";
      elseif (str_contains((string)$err, "users_email_key")) $message = "❌ Такой email уже есть.";
      else $message = "❌ Ошибка создания: ".h($err);
    }
  }
}

// Удаление пользователя (каскад удалит его файлы, если FK настроены)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete_user') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { die("CSRF token mismatch"); }
  $uid = (int)($_POST['user_id'] ?? 0);
  if ($uid>0) {
    $res = @pg_query_params($conn, "DELETE FROM users WHERE id=$1", [$uid]);
    if ($res) $message = "🗑 Пользователь #$uid удалён.";
    else $message = "❌ Не удалось удалить пользователя #$uid: ".h(pg_last_error($conn));
  }
}

// Удаление файла
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete_file') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { die("CSRF token mismatch"); }
  $fid = (int)($_POST['file_id'] ?? 0);
  if ($fid>0) {
    // узнаём имя файла на диске
    $r = pg_query_params($conn, "SELECT filename FROM files WHERE id=$1", [$fid]);
    if ($r && ($row = pg_fetch_assoc($r))) {
      $path = __DIR__ . "/uploads/" . $row['filename'];
      if (is_file($path)) @unlink($path);
    }
    $res = @pg_query_params($conn, "DELETE FROM files WHERE id=$1", [$fid]);
    if ($res) $message = "🗑 Файл #$fid удалён.";
    else $message = "❌ Не удалось удалить файл #$fid: ".h(pg_last_error($conn));
  }
}

// --- Данные для вкладок --- //

// Пользователи
$users = @pg_query($conn, "SELECT id, username, email, role, to_char(created_at,'YYYY-MM-DD HH24:MI') AS created_at
                           FROM users ORDER BY id DESC");

// Файлы
$files = @pg_query($conn, "
  SELECT f.id, f.filename, f.original_name, f.size, f.downloads, f.access_type,
         to_char(f.uploaded_at,'YYYY-MM-DD HH24:MI') AS uploaded_at,
         u.username AS uploader, u2.username AS receiver
  FROM files f
  JOIN users u  ON f.uploaded_by = u.id
  LEFT JOIN users u2 ON f.shared_with = u2.id
  ORDER BY f.id DESC
");

// Хвост логов синка
$logfile = __DIR__ . "/sync_log.txt";
$log_text = file_exists($logfile) ? file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$tail = implode("\n", array_slice($log_text, -300));

// Запуск синка из UI
$sync_response = null;
if (isset($_GET['do']) && $_GET['do']==='sync') {
  $mode = $_GET['mode'] ?? 'both';
  if (!in_array($mode, ['pull','push','both'], true)) $mode = 'both';

  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\');
  $url    = $scheme.'://'.$_SERVER['HTTP_HOST'].$base.'/sync.php?mode='.$mode.'&plain=1';

  $ctx = stream_context_create(['http'=>['timeout'=>120]]);
  $sync_response = @file_get_contents($url, false, $ctx);
  if ($sync_response === false) $sync_response = "❌ Не удалось обратиться к $url";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ-панель — EduKaz</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .tabs { display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
    .tab-btn { display:inline-block; padding:8px 14px; border-radius:8px; background:#eef1f7; color:#111; text-decoration:none; }
    .tab-btn.active { background:#1677ff; color:#fff; }
    .tab-content { display:none; }
    .tab-content.active { display:block; }
    .table-wrap { overflow:auto; }
    pre.log { background:#111; color:#cde; border-radius:8px; padding:12px; max-height:420px; overflow:auto; font:12.5px/1.45 Consolas,Monaco,monospace; }
    .note { background:#fff7e6; border:1px solid #ffd591; color:#8a6d3b; padding:10px 12px; border-radius:8px; }
    .btn.disabled { pointer-events:none; opacity:.6; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:10px; }
    .muted { color:#666; font-size:12px; }
  </style>
  <script>
    function openTab(id){
      document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
      document.querySelector('[data-tab="'+id+'"]').classList.add('active');
      document.getElementById(id).classList.add('active');
      history.replaceState(null,'','#'+id);
    }
    window.addEventListener('DOMContentLoaded',()=>{
      const h = location.hash.replace('#','') || 'users';
      openTab(h);
    });
  </script>
</head>
<body>

<div class="card" style="max-width:1100px;margin:20px auto;">

  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
    <h2 style="margin:0;">👑 Админ-панель EduKaz</h2>
    <div>
      <a href="index.php" class="btn">⬅ На главную</a>
      <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="note" style="margin-top:10px;"><?= $message ?></div>
  <?php endif; ?>

  <div class="tabs" style="margin-top:12px;">
    <a href="#users" data-tab="users" class="tab-btn">👤 Пользователи</a>
    <a href="#files" data-tab="files" class="tab-btn">📂 Файлы</a>
    <a href="#monitor" data-tab="monitor" class="tab-btn">🖥 Мониторинг</a>
  </div>

  <!-- Пользователи -->
  <div id="users" class="tab-content">
    <div class="grid">
      <div class="card">
        <h3 style="margin-top:0;">➕ Добавить пользователя</h3>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="create_user">
          <div><input type="text" name="username" placeholder="Логин" required></div>
          <div><input type="email" name="email" placeholder="Email" required></div>
          <div><input type="password" name="password" placeholder="Пароль" required></div>
          <div>
            <select name="role">
              <option value="user">user</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <button class="btn" type="submit">Создать</button>
        </form>
        <div class="muted" style="margin-top:6px;">Пароль хешируется через bcrypt (password_hash).</div>
      </div>

      <div class="card">
        <h3 style="margin-top:0;">🗑 Удалить пользователя</h3>
        <form method="post" onsubmit="return confirm('Удалить пользователя? Его файлы тоже будут удалены (ON DELETE CASCADE).');">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="delete_user">
          <div><input type="number" name="user_id" placeholder="ID пользователя" required></div>
          <button class="btn btn-danger" type="submit">Удалить</button>
        </form>
        <div class="muted" style="margin-top:6px;">Требуется FK: files.uploaded_by → users(id) ON DELETE CASCADE.</div>
      </div>
    </div>

    <div class="card" style="margin-top:12px;">
      <h3 style="margin-top:0;">👥 Список пользователей</h3>
      <div class="table-wrap">
        <table>
          <tr>
            <th>ID</th><th>Логин</th><th>Email</th><th>Роль</th><th>Создан</th>
          </tr>
          <?php if ($users): while($u = pg_fetch_assoc($users)): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><?= h($u['username']) ?></td>
              <td><?= h($u['email']) ?></td>
              <td><?= h($u['role']) ?></td>
              <td><?= h($u['created_at']) ?></td>
            </tr>
          <?php endwhile; endif; ?>
        </table>
      </div>
    </div>
  </div>

  <!-- Файлы -->
  <div id="files" class="tab-content">
    <div class="card">
      <h3 style="margin-top:0;">📂 Список файлов</h3>
      <div class="table-wrap">
        <table>
          <tr>
            <th>ID</th><th>Имя</th><th>Загрузил</th><th>Доступ</th><th>Получатель</th><th>Размер</th><th>Скачивания</th><th>Загружен</th><th>Действия</th>
          </tr>
          <?php if ($files): while($f = pg_fetch_assoc($files)): ?>
            <tr>
              <td><?= (int)$f['id'] ?></td>
              <td><?= h($f['original_name']) ?></td>
              <td><?= h($f['uploader']) ?></td>
              <td><?= h($f['access_type']) ?></td>
              <td><?= h($f['receiver'] ?? '-') ?></td>
              <td><?= number_format((float)$f['size']/1024, 1, ',', ' ') ?> КБ</td>
              <td><?= (int)$f['downloads'] ?></td>
              <td><?= h($f['uploaded_at']) ?></td>
              <td style="white-space:nowrap">
                <a class="btn" href="download.php?id=<?= (int)$f['id'] ?>">⬇</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Удалить файл?');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="delete_file">
                  <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
                  <button class="btn btn-danger" type="submit">🗑</button>
                </form>
              </td>
            </tr>
          <?php endwhile; endif; ?>
        </table>
      </div>
    </div>
  </div>

  <!-- Мониторинг -->
  <div id="monitor" class="tab-content">
    <div class="card">
      <h3 style="margin-top:0;">🖥 Мониторинг системы</h3>

      <div class="tabs" style="margin:0 0 8px 0;">
        <?php if ($on_railway): ?>
          <a class="btn disabled">🔁 Синхронизация (оба направления)</a>
          <a class="btn disabled">⬇ Railway → локальная</a>
          <a class="btn disabled">⬆ Локальная → Railway</a>
        <?php else: ?>
          <a class="btn" href="?do=sync&mode=both#monitor">🔁 Синхронизация (оба направления)</a>
          <a class="btn" href="?do=sync&mode=pull#monitor">⬇ Railway → локальная</a>
          <a class="btn" href="?do=sync&mode=push#monitor">⬆ Локальная → Railway</a>
        <?php endif; ?>
      </div>

      <?php if ($on_railway): ?>
        <div class="note" style="margin-bottom:10px;">
          ⚠ Эти операции выполняются <b>только с локального сайта</b> (http://localhost/edukaz/admin.php).
          Контейнер Railway не может подключаться к вашей локальной PostgreSQL.
        </div>
      <?php endif; ?>

      <?php if ($sync_response !== null): ?>
        <div class="note" style="margin-bottom:10px;">
          <b>Ответ:</b> <?= h($sync_response) ?>
        </div>
      <?php endif; ?>

      <pre class="log"><?= h($tail) ?></pre>

      <div class="tabs" style="justify-content:space-between;margin-top:10px;">
        <a href="index.php" class="btn">⬅ На главную</a>
        <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
      </div>
    </div>
  </div>

</div>

<script>
  // навешиваем обработчики на таб-кнопки
  document.querySelectorAll('.tab-btn').forEach(b=>{
    b.addEventListener('click', (e)=>{ e.preventDefault(); openTab(b.getAttribute('data-tab')); });
  });
</script>
</body>
</html>
