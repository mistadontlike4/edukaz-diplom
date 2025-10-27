<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: login.php"); exit;
}

// детектор Railway (чтобы скрыть кнопки синка на проде)
$on_railway = isset($_ENV['RAILWAY_ENVIRONMENT'])
           || isset($_SERVER['RAILWAY_ENVIRONMENT'])
           || (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'railway.app'));

// простая статистика
$users_total = 0; $files_total = 0; $last_file_at = '-';
if ($conn) {
  if ($r = pg_query($conn, "SELECT COUNT(*) FROM users")) $users_total = (int)pg_fetch_result($r,0,0);
  if ($r = pg_query($conn, "SELECT COUNT(*) FROM files")) $files_total = (int)pg_fetch_result($r,0,0);
  if ($r = pg_query($conn, "SELECT to_char(MAX(uploaded_at),'YYYY-MM-DD HH24:MI') FROM files"))
      $last_file_at = pg_fetch_result($r,0,0) ?: '-';
}

// запуск синка (plain) по кнопкам
$sync_response = null;
if (isset($_GET['action']) && $_GET['action']==='sync') {
  $mode = $_GET['mode'] ?? 'both';
  if (!in_array($mode, ['pull','push','both'], true)) $mode = 'both';

  // обращаемся к sync.php по HTTP, чтобы гарантированно исполнился PHP
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\');
  $url    = $scheme.'://'.$_SERVER['HTTP_HOST'].$base.'/sync.php?mode='.$mode.'&plain=1';

  $ctx = stream_context_create(['http'=>['timeout'=>120]]);
  $sync_response = @file_get_contents($url, false, $ctx);
  if ($sync_response === false) $sync_response = "❌ Не удалось обратиться к $url";
}

// читаем хвост логов
$logfile = __DIR__ . "/sync_log.txt";
$log_text = file_exists($logfile) ? file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$tail = implode("\n", array_slice($log_text, -300));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админ-панель — Мониторинг</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .toolbar { display:flex; gap:10px; flex-wrap:wrap; margin:10px 0; }
    .pill { display:inline-block; padding:10px 14px; background:#fff; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,.06); margin-right:10px; }
    pre.log { background:#111; color:#cde; border-radius:10px; padding:12px; max-height:420px; overflow:auto; font:12.5px/1.45 Consolas,Monaco,monospace; }
    .note { background:#fff7e6; border:1px solid #ffd591; color:#8a6d3b; padding:10px 12px; border-radius:8px; }
    .btn.disabled { pointer-events:none; opacity:.6; }
    .header-actions { display:flex; gap:8px; }
  </style>
</head>
<body>
<div class="card" style="max-width:1100px;margin:20px auto;">

  <div class="header-actions" style="justify-content:space-between; align-items:center;">
    <div>
      <span class="pill">Активная БД: <b><?= $on_railway ? 'Railway' : 'Локальная' ?></b></span>
      <span class="pill">Пользователи: <b><?= (int)$users_total ?></b></span>
      <span class="pill">Файлы: <b><?= (int)$files_total ?></b></span>
      <span class="pill">Последний файл: <b><?= htmlspecialchars($last_file_at) ?></b></span>
    </div>
    <div class="header-actions">
      <a href="index.php" class="btn">⬅ На главную</a>
      <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
    </div>
  </div>

  <h2 style="margin-top:10px;">🖥 Мониторинг системы</h2>

  <div class="toolbar">
    <?php if ($on_railway): ?>
      <a class="btn disabled">🔁 Синхронизация (оба направления)</a>
      <a class="btn disabled">⬇ Получить с Railway → локальная</a>
      <a class="btn disabled">⬆ Отправить локальная → Railway</a>
    <?php else: ?>
      <a class="btn" href="?action=sync&mode=both">🔁 Синхронизация (оба направления)</a>
      <a class="btn" href="?action=sync&mode=pull">⬇ Получить с Railway → локальная</a>
      <a class="btn" href="?action=sync&mode=push">⬆ Отправить локальная → Railway</a>
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
      <b>Ответ:</b> <?= htmlspecialchars($sync_response, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <pre class="log"><?= htmlspecialchars($tail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>

  <div class="toolbar" style="justify-content:space-between;">
    <a href="index.php" class="btn">⬅ На главную</a>
    <a href="logout.php" class="btn btn-danger">🚪 Выйти</a>
  </div>

</div>
</body>
</html>
