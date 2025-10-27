<?php
// Админ-панель (сокращённая версия с упором на Мониторинг)
session_start();
require_once "db.php"; // должен устанавливать $conn (Railway/локально) и, желательно, role в сессии

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: login.php"); exit;
}

// Статистика
$users_total = 0; $files_total = 0; $last_file_at = '-';
if ($conn) {
  $r = pg_query($conn,"SELECT COUNT(*) FROM users"); if ($r) $users_total = (int)pg_fetch_result($r,0,0);
  $r = pg_query($conn,"SELECT COUNT(*) FROM files"); if ($r) $files_total = (int)pg_fetch_result($r,0,0);
  $r = pg_query($conn,"SELECT to_char(MAX(uploaded_at),'YYYY-MM-DD HH24:MI') FROM files");
  if ($r) $last_file_at = pg_fetch_result($r,0,0) ?: '-';
}

// Мониторинг: прогон синка по кнопкам
$sync_response = null;
if (isset($_GET['action']) && $_GET['action']==='sync') {
  $mode = $_GET['mode'] ?? 'both';
  if (!in_array($mode,['pull','push','both'],true)) $mode='both';

  // вызывать по HTTP, чтобы гарантированно исполнился PHP
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\');
  $url    = $scheme.'://'.$_SERVER['HTTP_HOST'].$base.'/sync.php?mode='.$mode.'&plain=1';

  $ctx = stream_context_create(['http'=>['timeout'=>120]]);
  $sync_response = @file_get_contents($url,false,$ctx);
  if ($sync_response===false) $sync_response = "❌ Не удалось обратиться к $url";
}

// Логи
$logfile = __DIR__."/sync_log.txt";
$log_text = file_exists($logfile) ? file($logfile, FILE_IGNORE_NEW_LINES) : [];
$tail = implode("\n", array_slice($log_text, -300));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Админ-панель — Мониторинг</title>
<link rel="stylesheet" href="style.css">
<style>
  body{font-family:Inter,Arial,sans-serif;background:#f6f7fb;margin:0}
  .wrap{max-width:1100px;margin:26px auto;padding:0 12px}
  .row{display:flex;gap:14px;flex-wrap:wrap}
  .card{background:#fff;border-radius:14px;box-shadow:0 4px 14px rgba(0,0,0,.06);padding:16px 18px}
  .pill{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.06);min-width:240px}
  .btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#1677ff;color:#fff;text-decoration:none}
  .btn:hover{background:#0d62d6}
  .btn.red{background:#e74c3c}
  .btn.gray{background:#eef1f7;color:#111}
  .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 8px}
  pre.log{background:#111;color:#cde; padding:12px;border-radius:10px;max-height:420px;overflow:auto;font:12.5px/1.45 Consolas,Monaco,monospace}
  .stat{display:flex;gap:10px;align-items:center}
  .dot{width:10px;height:10px;border-radius:50%;}
  .dot.green{background:#2ecc71}
  .dot.red{background:#e74c3c}
</style>
</head>
<body>
<div class="wrap">

  <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px">
    <div class="stat">
      <span class="dot green"></span>
      <b>Railway PostgreSQL подключён</b>
    </div>
    <div class="row">
      <a class="btn gray" href="index.php">⬅ На главную</a>
      <a class="btn red" href="logout.php">🚪 Выйти</a>
    </div>
  </div>

  <div class="row">
    <div class="pill">Активная БД&nbsp;&nbsp; <b>Railway</b></div>
    <div class="pill">Пользователи&nbsp;&nbsp; <b><?= (int)$users_total ?></b></div>
    <div class="pill">Файлы&nbsp;&nbsp; <b><?= (int)$files_total ?></b></div>
    <div class="pill">Последний файл&nbsp;&nbsp; <b><?= htmlspecialchars($last_file_at) ?></b></div>
  </div>

  <div class="card" style="margin-top:18px">
    <h3>🖥 Мониторинг системы</h3>

    <div class="toolbar">
      <a class="btn" href="?action=sync&mode=both">🔁 Синхронизация (оба направления)</a>
      <a class="btn" href="?action=sync&mode=pull">⬇ Получить с Railway → локальная</a>
      <a class="btn" href="?action=sync&mode=push">⬆ Отправить локальная → Railway</a>
    </div>

    <?php if ($sync_response !== null): ?>
      <div class="card" style="background:#fff7e6;border:1px solid #ffd591;margin:10px 0">
        <div style="color:#8c6d1f;font:14px/1.4 Arial">
          <b>⚠ Ответ:</b> <?= htmlspecialchars($sync_response, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      </div>
    <?php endif; ?>

    <pre class="log"><?= htmlspecialchars($tail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>

    <div class="toolbar" style="justify-content:space-between">
      <a class="btn gray" href="index.php">⬅ На главную</a>
      <a class="btn red" href="logout.php">🚪 Выйти</a>
    </div>
  </div>

</div>
</body>
</html>
