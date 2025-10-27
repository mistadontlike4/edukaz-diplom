<?php
/**
 * sync.php — двунаправленная синхронизация Local <-> Railway
 * Режимы:
 *   - ?mode=pull  : Railway → Local
 *   - ?mode=push  : Local → Railway
 *   - ?mode=both  : (по умолчанию) сначала pull, затем push
 *
 * Логи: sync_log.txt (в корне проекта)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Almaty');

header('Content-Type: text/html; charset=utf-8');

$logFile = __DIR__ . '/sync_log.txt';
function logmsg($m){ global $logFile; file_put_contents($logFile, "[".date("Y-m-d H:i:s")."] $m\n", FILE_APPEND); }
function escv($v){ return $v===null ? 'NULL' : "'".pg_escape_string($v)."'"; }

// Ловим ошибки подключения без использования pg_last_error() на null
function pg_try_connect(string $conn_str, ?string &$err = null) {
  $err = null;
  set_error_handler(function($errno, $errstr) use (&$err){ $err = $errstr; });
  $c = @pg_connect($conn_str);
  restore_error_handler();
  return $c;
}

// ---- Параметр режима ----
$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'both';
if (!in_array($mode, ['pull','push','both'], true)) $mode = 'both';

// ---- Подключения ----
// Railway (REMOTE)
$remote = [
  'host' => 'interchange.proxy.rlwy.net',
  'port' => '54049',
  'dbname' => 'railway',
  'user' => 'postgres',
  'password' => 'USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu',
];

// Local (LOCAL) — пароль admin
$local = [
  'host' => 'localhost',
  'port' => '5432',
  'dbname' => 'edukaz_backup',
  'user' => 'postgres',
  'password' => 'admin',
];

logmsg("🚀 Старт синхронизации (mode=$mode)");

// Добавим connect_timeout, чтобы быстро падать, а не висеть
$remote_str = "host={$remote['host']} port={$remote['port']} dbname={$remote['dbname']} user={$remote['user']} password={$remote['password']} connect_timeout=5";
$local_str  = "host={$local['host']}  port={$local['port']}  dbname={$local['dbname']}  user={$local['user']}  password={$local['password']}  connect_timeout=5";

// Пробуем подключиться и собираем понятные ошибки
$remote_err = $local_err = null;
$remote_conn = pg_try_connect($remote_str, $remote_err);
$local_conn  = pg_try_connect($local_str, $local_err);

// Чёткие сообщения (без deprecated):
if (!$remote_conn) {
  $meta = "host={$remote['host']} port={$remote['port']} db={$remote['dbname']} user={$remote['user']}";
  logmsg("❌ Railway недоступен ($meta): ".($remote_err ?: 'нет деталей'));
  exit("❌ Railway недоступен: ".htmlspecialchars($remote_err ?: 'нет деталей')."<br>($meta)");
}
if (!$local_conn) {
  $meta = "host={$local['host']} port={$local['port']} db={$local['dbname']} user={$local['user']}";
  logmsg("❌ Локальная БД недоступна ($meta): ".($local_err ?: 'нет деталей'));
  exit("❌ Локальная БД недоступна: ".htmlspecialchars($local_err ?: 'нет деталей')."<br>($meta)");
}

// ---- Схема/миграции (минимум, чтобы не падать) ----
$schema_sql = [
  // users
  "CREATE TABLE IF NOT EXISTS users (
     id SERIAL PRIMARY KEY,
     username VARCHAR(100) UNIQUE NOT NULL,
     email    VARCHAR(150) UNIQUE NOT NULL,
     password TEXT NOT NULL,
     role     VARCHAR(20) DEFAULT 'user',
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );",
  // files
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
  // на случай старых схем
  "ALTER TABLE files ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;",
  "ALTER TABLE files ADD COLUMN IF NOT EXISTS size BIGINT DEFAULT 0;",
  "ALTER TABLE files ADD COLUMN IF NOT EXISTS downloads INTEGER DEFAULT 0;"
];

foreach ([$local_conn,$remote_conn] as $cx) {
  foreach ($schema_sql as $sql) { @pg_query($cx, $sql); }
}

// ---- Гарантируем корректные FK (безопасно если уже есть) ----
$fk_sql = [
  "ALTER TABLE files
     DROP CONSTRAINT IF EXISTS files_uploaded_by_fkey,
     ADD  CONSTRAINT files_uploaded_by_fkey
       FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE;",
  "ALTER TABLE files
     DROP CONSTRAINT IF EXISTS files_shared_with_fkey,
     ADD  CONSTRAINT files_shared_with_fkey
       FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE SET NULL;"
];
foreach ([$local_conn,$remote_conn] as $cx) {
  foreach ($fk_sql as $sql) { @pg_query($cx, $sql); }
}

function has_column($conn,$table,$col){
  $q="SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name=$1 AND column_name=$2";
  $r=pg_query_params($conn,$q,[$table,$col]); return $r && pg_fetch_row($r);
}
function user_map($conn){
  $m=[]; $r=pg_query($conn,"SELECT id,username FROM users");
  if ($r) while($x=pg_fetch_assoc($r)) $m[$x['username']]=(int)$x['id'];
  return $m;
}
function upsert_user_from_row($dst_conn,$row){
  $ex = pg_query_params($dst_conn,"SELECT id FROM users WHERE username=$1 OR email=$2",[$row['username'],$row['email']]);
  if ($ex && ($e=pg_fetch_assoc($ex))) return (int)$e['id'];
  $q="INSERT INTO users(username,email,password,role,created_at)
      VALUES($1,$2,$3,$4,COALESCE($5,NOW())) RETURNING id";
  $r=pg_query_params($dst_conn,$q,[
    $row['username'],$row['email'],$row['password'],$row['role']?:'user',$row['created_at']
  ]);
  if ($r && ($x=pg_fetch_assoc($r))) return (int)$x['id'];
  return null;
}
function file_exists_by_natural_key($conn,$original_name,$uploader_id,$uploaded_at,$size){
  $q="SELECT 1 FROM files WHERE original_name=$1 AND uploaded_by=$2 AND size=$3 AND ";
  if ($uploaded_at===null){ $q.="uploaded_at IS NULL"; $p=[$original_name,$uploader_id,(int)$size]; }
  else { $q.="uploaded_at=$4"; $p=[$original_name,$uploader_id,(int)$size,$uploaded_at]; }
  $r=pg_query_params($conn,$q,$p); return ($r && pg_fetch_row($r));
}
function insert_file_if_missing($dst_conn,$row,$uploader_id,$shared_with_id,$has_file_data_col){
  $orig = $row['original_name'];
  $up_at = $row['uploaded_at'];
  $size  = $row['size']!==null ? (int)$row['size'] : 0;

  if (file_exists_by_natural_key($dst_conn,$orig,$uploader_id,$up_at,$size)) return true;

  $cols = ['filename','original_name','uploaded_by','size','downloads','access_type','shared_with','uploaded_at'];
  $vals = [
    escv($row['filename']),
    escv($row['original_name']),
    $uploader_id,
    (int)$size,
    (int)($row['downloads'] ?? 0),
    escv($row['access_type'] ?? 'public'),
    ($shared_with_id===null ? 'NULL' : (int)$shared_with_id),
    ($up_at===null ? 'NULL' : escv($up_at))
  ];

  // бинарь file_data по умолчанию не копируем (можно включить при необходимости):
  // if ($has_file_data_col && array_key_exists('file_data',$row) && $row['file_data']!==null) {
  //   $cols[]='file_data'; $vals[]=escv($row['file_data']);
  // }

  $sql="INSERT INTO files (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
  return (bool)pg_query($dst_conn,$sql);
}

// ---- Направления ----
function pull_remote_to_local($remote_conn,$local_conn){
  logmsg("⬇️ PULL: Railway → Local");

  // users
  $lmap = user_map($local_conn);
  $ru = pg_query($remote_conn,"SELECT * FROM users");
  $created=0;
  if ($ru) while($u=pg_fetch_assoc($ru)){
    if (!isset($lmap[$u['username']])){
      $nid = upsert_user_from_row($local_conn,$u);
      if ($nid){ $lmap[$u['username']]=$nid; $created++; }
    }
  }
  logmsg("PULL users: создано = $created");

  // files
  $has_fd_local = has_column($local_conn,'files','file_data');
  $rf = pg_query($remote_conn,"
    SELECT f.*, u.username AS uploader_name, su.username AS shared_name
    FROM files f
    JOIN users u  ON f.uploaded_by=u.id
    LEFT JOIN users su ON f.shared_with=su.id
  ");
  $ins=0;
  if ($rf) while($f=pg_fetch_assoc($rf)){
    $uid = $lmap[$f['uploader_name']] ?? null;
    if ($uid===null) continue;
    $sid = null;
    if (!empty($f['shared_name'])) { $sid = $lmap[$f['shared_name']] ?? null; }
    if (insert_file_if_missing($local_conn,$f,$uid,$sid,$has_fd_local)) $ins++;
  }
  logmsg("PULL files: добавлено = $ins");
}

function push_local_to_remote($local_conn,$remote_conn){
  logmsg("⬆️ PUSH: Local → Railway");

  // users
  $rmap = user_map($remote_conn);
  $lu = pg_query($local_conn,"SELECT * FROM users");
  $created=0;
  if ($lu) while($u=pg_fetch_assoc($lu)){
    if (!isset($rmap[$u['username']])){
      $nid = upsert_user_from_row($remote_conn,$u);
      if ($nid){ $rmap[$u['username']]=$nid; $created++; }
    }
  }
  logmsg("PUSH users: создано = $created");

  // files
  $has_fd_remote = has_column($remote_conn,'files','file_data');
  $lf = pg_query($local_conn,"
    SELECT f.*, u.username AS uploader_name, su.username AS shared_name
    FROM files f
    JOIN users u  ON f.uploaded_by=u.id
    LEFT JOIN users su ON f.shared_with=su.id
  ");
  $ins=0;
  if ($lf) while($f=pg_fetch_assoc($lf)){
    $uid = $rmap[$f['uploader_name']] ?? null;
    if ($uid===null) continue;
    $sid = null;
    if (!empty($f['shared_name'])) { $sid = $rmap[$f['shared_name']] ?? null; }
    if (insert_file_if_missing($remote_conn,$f,$uid,$sid,$has_fd_remote)) $ins++;
  }
  logmsg("PUSH files: добавлено = $ins");
}

// ---- Запуск ----
try {
  if ($mode==='pull') {
    pull_remote_to_local($remote_conn,$local_conn);
  } elseif ($mode==='push') {
    push_local_to_remote($local_conn,$remote_conn);
  } else { // both
    pull_remote_to_local($remote_conn,$local_conn);
    push_local_to_remote($local_conn,$remote_conn);
  }
  logmsg("🎉 Синхронизация завершена (mode=$mode).");
  echo "<p style='font:14px Arial;color:green;'>✅ Готово (mode=$mode). Смотри sync_log.txt</p>";
} catch (Throwable $e) {
  logmsg("💥 Ошибка: ".$e->getMessage());
  echo "<p style='font:14px Arial;color:#b02a37;'>❌ Ошибка: ".htmlspecialchars($e->getMessage())."</p>";
}
