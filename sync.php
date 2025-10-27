<?php
/**
 * sync.php — двунаправленная синхронизация Local <-> Railway
 * Режимы: ?mode=pull | push | both (по умолчанию both)
 * Форматы ответа:
 *   - по умолчанию HTML (красиво в браузере)
 *   - ?plain=1  — чистый текст (для лог-бокса в админке)
 *
 * Логи: sync_log.txt в корне проекта
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Almaty');

$plain = isset($_GET['plain']);                 // <— ключ: чистый текст
$mode  = $_GET['mode'] ?? 'both';
if (!in_array($mode, ['pull','push','both'], true)) $mode = 'both';

$logFile = __DIR__ . '/sync_log.txt';
function logmsg($m){ global $logFile; file_put_contents($logFile, "[".date("Y-m-d H:i:s")."] $m\n", FILE_APPEND); }

// безопасное подключение без deprecated pg_last_error(null)
function pg_try_connect(string $conn_str, ?string &$err = null) {
  $err = null;
  set_error_handler(function($errno, $errstr) use (&$err){ $err = $errstr; });
  $c = @pg_connect($conn_str);
  restore_error_handler();
  return $c;
}

// === ПОДКЛЮЧЕНИЯ ===
// Railway (REMOTE)
$remote = [
  'host' => 'interchange.proxy.rlwy.net',
  'port' => '54049',
  'dbname' => 'railway',
  'user' => 'postgres',
  'password' => 'USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu',
];
// Local (LOCAL)
$local = [
  'host' => 'localhost',
  'port' => '5432',
  'dbname' => 'edukaz_backup',
  'user' => 'postgres',
  'password' => 'admin',
];

$remote_str = "host={$remote['host']} port={$remote['port']} dbname={$remote['dbname']} user={$remote['user']} password={$remote['password']} connect_timeout=5";
$local_str  = "host={$local['host']}  port={$local['port']}  dbname={$local['dbname']}  user={$local['user']}  password={$local['password']}  connect_timeout=5";

$remote_err = $local_err = null;
$remote_conn = pg_try_connect($remote_str, $remote_err);
$local_conn  = pg_try_connect($local_str,  $local_err);

if (!$remote_conn) {
  $msg = "❌ Railway недоступен: ".($remote_err ?: 'нет деталей');
  logmsg($msg);
  if ($plain) { header('Content-Type: text/plain; charset=utf-8'); echo $msg; }
  else { echo "<h3 style='color:#b02a37'>$msg</h3>"; }
  exit;
}
if (!$local_conn) {
  $msg = "❌ Локальная БД недоступна: ".($local_err ?: 'нет деталей');
  logmsg($msg);
  if ($plain) { header('Content-Type: text/plain; charset=utf-8'); echo $msg; }
  else { echo "<h3 style='color:#b02a37'>$msg</h3>"; }
  exit;
}

// === МИНИ-МИГРАЦИИ (на обеих БД безопасно) ===
$schema_sql = [
  "CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email    VARCHAR(150) UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role     VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );",
  "CREATE TABLE IF NOT EXISTS files (
    id SERIAL PRIMARY KEY,
    filename TEXT NOT NULL,
    original_name TEXT,
    size BIGINT DEFAULT 0,
    downloads INTEGER DEFAULT 0,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE CASCADE,
    access_type VARCHAR(20) DEFAULT 'public',
    shared_with INTEGER REFERENCES users(id) ON DELETE SET NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );",
  "ALTER TABLE files ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;",
  "ALTER TABLE files ADD COLUMN IF NOT EXISTS size BIGINT DEFAULT 0;",
  "ALTER TABLE files ADD COLUMN IF NOT EXISTS downloads INTEGER DEFAULT 0;",
  // FK на всякий
  "ALTER TABLE files
     DROP CONSTRAINT IF EXISTS files_uploaded_by_fkey,
     ADD  CONSTRAINT files_uploaded_by_fkey
       FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE;",
  "ALTER TABLE files
     DROP CONSTRAINT IF EXISTS files_shared_with_fkey,
     ADD  CONSTRAINT files_shared_with_fkey
       FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE SET NULL;"
];
foreach ([$local_conn,$remote_conn] as $cx) foreach ($schema_sql as $sql) @pg_query($cx, $sql);

// === УТИЛИТЫ ===
function user_map($conn){ $m=[]; $r=pg_query($conn,"SELECT id, username FROM users"); if ($r) while($x=pg_fetch_assoc($r)) $m[$x['username']] = (int)$x['id']; return $m; }

function upsert_user_from_row($dst_conn, array $row){
  $ex = pg_query_params($dst_conn,"SELECT id FROM users WHERE username=$1 OR email=$2",[$row['username'],$row['email']]);
  if ($ex && ($e=pg_fetch_assoc($ex))) return (int)$e['id'];
  $r = pg_query_params($dst_conn,
    "INSERT INTO users(username,email,password,role,created_at)
     VALUES($1,$2,$3,$4,COALESCE($5,NOW())) RETURNING id",
    [$row['username'],$row['email'],$row['password'],$row['role']?:'user',$row['created_at']]
  );
  if ($r && ($x=pg_fetch_assoc($r))) return (int)$x['id'];
  return null;
}

function file_exists_by_natural_key($conn,$original_name,$uploader_id,$uploaded_at,$size){
  if ($uploaded_at===null) {
    $r=pg_query_params($conn,"SELECT 1 FROM files WHERE original_name=$1 AND uploaded_by=$2 AND size=$3 AND uploaded_at IS NULL",
      [$original_name,(int)$uploader_id,(int)$size]);
  } else {
    $r=pg_query_params($conn,"SELECT 1 FROM files WHERE original_name=$1 AND uploaded_by=$2 AND size=$3 AND uploaded_at=$4",
      [$original_name,(int)$uploader_id,(int)$size,$uploaded_at]);
  }
  return ($r && pg_fetch_row($r));
}

function insert_file_if_missing($dst_conn, array $row, int $uploader_id, ?int $shared_with_id){
  $orig = $row['original_name'];
  $up_at = $row['uploaded_at'];
  $size  = $row['size']!==null ? (int)$row['size'] : 0;

  if (file_exists_by_natural_key($dst_conn,$orig,$uploader_id,$up_at,$size)) return true;

  $sql="INSERT INTO files
        (filename, original_name, uploaded_by, size, downloads, access_type, shared_with, uploaded_at)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8)";
  $params = [
    $row['filename'],
    $row['original_name'],
    $uploader_id,
    (int)$size,
    (int)($row['downloads'] ?? 0),
    ($row['access_type'] ?? 'public'),
    $shared_with_id,     // NULL ok
    $up_at               // NULL ok
  ];
  return (bool)pg_query_params($dst_conn,$sql,$params);
}

// === НАПРАВЛЕНИЯ ===
function pull_remote_to_local($remote_conn,$local_conn,&$u_cnt,&$f_cnt){
  $u_cnt=$f_cnt=0;
  $lmap = user_map($local_conn);
  // users
  $ru = pg_query($remote_conn,"SELECT * FROM users");
  if ($ru) while($u=pg_fetch_assoc($ru)){
    if (!isset($lmap[$u['username']])){
      $nid = upsert_user_from_row($local_conn,$u);
      if ($nid){ $lmap[$u['username']]=$nid; $u_cnt++; }
    }
  }
  // files
  $rf = pg_query($remote_conn,"SELECT f.*, u.username AS uploader_name, su.username AS shared_name
                               FROM files f
                               JOIN users u ON f.uploaded_by=u.id
                               LEFT JOIN users su ON f.shared_with=su.id");
  if ($rf) while($f=pg_fetch_assoc($rf)){
    $uid = $lmap[$f['uploader_name']] ?? null;
    if ($uid===null) continue;
    $sid = !empty($f['shared_name']) ? ($lmap[$f['shared_name']] ?? null) : null;
    if (insert_file_if_missing($local_conn,$f,$uid,$sid)) $f_cnt++;
  }
}

function push_local_to_remote($local_conn,$remote_conn,&$u_cnt,&$f_cnt){
  $u_cnt=$f_cnt=0;
  $rmap = user_map($remote_conn);
  // users
  $lu = pg_query($local_conn,"SELECT * FROM users");
  if ($lu) while($u=pg_fetch_assoc($lu)){
    if (!isset($rmap[$u['username']])){
      $nid = upsert_user_from_row($remote_conn,$u);
      if ($nid){ $rmap[$u['username']]=$nid; $u_cnt++; }
    }
  }
  // files
  $lf = pg_query($local_conn,"SELECT f.*, u.username AS uploader_name, su.username AS shared_name
                              FROM files f
                              JOIN users u ON f.uploaded_by=u.id
                              LEFT JOIN users su ON f.shared_with=su.id");
  if ($lf) while($f=pg_fetch_assoc($lf)){
    $uid = $rmap[$f['uploader_name']] ?? null;
    if ($uid===null) continue;
    $sid = !empty($f['shared_name']) ? ($rmap[$f['shared_name']] ?? null) : null;
    if (insert_file_if_missing($remote_conn,$f,$uid,$sid)) $f_cnt++;
  }
}

// === ЗАПУСК ===
try {
  logmsg("🚀 Старт синхронизации (mode=$mode)");
  $stats = [];

  if ($mode==='pull' || $mode==='both'){
    $u=$f=0; pull_remote_to_local($remote_conn,$local_conn,$u,$f);
    logmsg("⬇️ PULL: users+=$u, files+=$f"); $stats['pull_users']=$u; $stats['pull_files']=$f;
  }
  if ($mode==='push' || $mode==='both'){
    $u=$f=0; push_local_to_remote($local_conn,$remote_conn,$u,$f);
    logmsg("⬆️ PUSH: users+=$u, files+=$f"); $stats['push_users']=$u; $stats['push_files']=$f;
  }

  logmsg("🎉 Синхронизация завершена (mode=$mode).");

  if ($plain) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "✅ Готово (mode=$mode). users: +".($stats['pull_users']??0)."/+".($stats['push_users']??0).
         ", files: +".($stats['pull_files']??0)."/+".($stats['push_files']??0);
  } else {
    echo "<h3>🔄 Синхронизация данных</h3>";
    echo "<p>🎯 Готово!</p>";
    echo "<a href='admin.php' style='font-size:18px'>⬅ Вернуться в админ-панель</a>";
  }
} catch (Throwable $e) {
  logmsg("💥 Ошибка: ".$e->getMessage());
  if ($plain) { header('Content-Type: text/plain; charset=utf-8'); echo "❌ Ошибка: ".$e->getMessage(); }
  else { echo "<p style='color:#b02a37'>❌ Ошибка: ".htmlspecialchars($e->getMessage())."</p>"; }
}
