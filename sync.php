<?php
// ===== Настройки подключений =====

// Railway (REMOTE)
$remote = [
  'host' => 'interchange.proxy.rlwy.net',
  'port' => '54049',
  'dbname' => 'railway',
  'user' => 'postgres',
  'password' => 'USLLNRHbFMSNNdOUnAxkbHxbkfpsmQGu',
];

// Локальная (LOCAL)
$local = [
  'host' => 'localhost',
  'port' => '5432',
  'dbname' => 'edukaz_backup',
  'user' => 'postgres',
  'password' => 'admin', // <-- твой пароль локальной БД
];

date_default_timezone_set('Asia/Almaty');
$mode = isset($_GET['mode']) ? strtolower($_GET['mode']) : 'both'; // pull | push | both
$logFile = __DIR__ . '/sync_log.txt';

function logmsg($msg) {
  global $logFile;
  file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] $msg".PHP_EOL, FILE_APPEND);
}
function escv($v){ return $v===null ? 'NULL' : "'".pg_escape_string($v)."'"; }

// Connect
$remote_conn = @pg_connect("host={$remote['host']} port={$remote['port']} dbname={$remote['dbname']} user={$remote['user']} password={$remote['password']}");
$local_conn  = @pg_connect("host={$local['host']}  port={$local['port']}  dbname={$local['dbname']}  user={$local['user']}  password={$local['password']}");

if (!$local_conn)  { logmsg("❌ Локальная БД недоступна.");  exit("❌ Локальная БД недоступна."); }
if (!$remote_conn) { logmsg("❌ Railway недоступен.");       exit("❌ Railway недоступен."); }

logmsg("🚀 Старт синхронизации (mode=$mode)");

// ===== Схема (минимум, чтобы не падать) =====
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
  // гарантируем наличие uploaded_at
  "ALTER TABLE files ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;"
];
// применим на обеих базах
foreach ([$local_conn,$remote_conn] as $cx) {
  foreach ($schema_sql as $sql) { @pg_query($cx, $sql); }
}

// ===== Утилиты =====

// Вернуть map username->id для соединения
function user_map($conn) {
  $map = [];
  $res = pg_query($conn, "SELECT id, username FROM users");
  if ($res) while ($r = pg_fetch_assoc($res)) $map[$r['username']] = (int)$r['id'];
  return $map;
}
// Есть ли колонка в таблице?
function has_column($conn,$table,$col){
  $q = "SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name=$1 AND column_name=$2";
  $r = pg_query_params($conn,$q,[$table,$col]);
  return $r && pg_fetch_row($r);
}

// Проверка "естественного ключа" файла: original_name + uploader_username + uploaded_at + size
function file_exists_by_natural_key($conn, $original_name, $uploader_id, $uploaded_at, $size){
  // ищем по полям. uploaded_at может быть NULL
  $q = "SELECT 1 FROM files WHERE original_name=$1 AND uploaded_by=$2 AND size=$3 AND ";
  if ($uploaded_at === null) {
    $q .= "uploaded_at IS NULL";
    $params = [$original_name, $uploader_id, $size];
  } else {
    $q .= "uploaded_at = $4";
    $params = [$original_name, $uploader_id, $size, $uploaded_at];
  }
  $res = pg_query_params($conn, $q, $params);
  return ($res && pg_fetch_row($res));
}

// Вставка юзера, если нет (по username/email)
function upsert_user_from_row($dst_conn, $row){
  // если уже есть — выходим
  $exists = pg_query_params($dst_conn, "SELECT id FROM users WHERE username=$1 OR email=$2", [$row['username'],$row['email']]);
  if ($exists && ($x = pg_fetch_assoc($exists))) return (int)$x['id'];

  $q = "INSERT INTO users (username,email,password,role,created_at)
        VALUES ($1,$2,$3,$4,COALESCE($5, NOW()))
        RETURNING id";
  $res = pg_query_params($dst_conn, $q, [
    $row['username'], $row['email'], $row['password'], $row['role'] ?: 'user', $row['created_at']
  ]);
  if ($res && ($r = pg_fetch_assoc($res))) return (int)$r['id'];
  return null;
}

// Вставка файла если нет по «естественному ключу»
function insert_file_if_missing($dst_conn, $row, $uploader_id, $shared_with_id, $has_file_data_col){
  $orig = $row['original_name'];
  $up_at = $row['uploaded_at'];
  $size  = $row['size'] !== null ? (int)$row['size'] : 0;

  if (file_exists_by_natural_key($dst_conn, $orig, $uploader_id, $up_at, $size)) return true;

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

  // по желанию можно копировать file_data, если столбец есть и он небольшой.
  if ($has_file_data_col && array_key_exists('file_data',$row) && $row['file_data']!==null) {
    // пропускаем бинарь по умолчанию (можно включить при желании)
    // $cols[] = 'file_data';
    // $vals[] = escv($row['file_data']);
  }

  $sql = "INSERT INTO files (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
  $res = pg_query($dst_conn, $sql);
  return (bool)$res;
}

// ====== PULL: Railway -> Local ======
function pull_remote_to_local($remote_conn,$local_conn){
  logmsg("⬇️ PULL: Railway → Local");
  $l_user_map = user_map($local_conn);
  $r_users = pg_query($remote_conn, "SELECT * FROM users");
  $created_users = 0;
  if ($r_users) {
    while ($u = pg_fetch_assoc($r_users)) {
      if (!isset($l_user_map[$u['username']])) {
        $new_id = upsert_user_from_row($local_conn, $u);
        if ($new_id) { $l_user_map[$u['username']] = $new_id; $created_users++; }
      }
    }
  }
  logmsg("PULL users: создано новых = $created_users");

  $has_fd_local = has_column($local_conn, 'files', 'file_data');

  $r_files = pg_query($remote_conn, "
    SELECT f.*, u.username AS uploader_name, su.username AS shared_name
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    LEFT JOIN users su ON f.shared_with = su.id
  ");
  $ins_files = 0;
  if ($r_files) {
    while ($f = pg_fetch_assoc($r_files)) {
      $uploader   = $f['uploader_name'];
      $sharedname = $f['shared_name'];
      $dst_uid = $l_user_map[$uploader] ?? null;
      if ($dst_uid===null) {
        // если вдруг не создали выше — создадим на лету пустышку (но обычно не бывает)
        continue;
      }
      $dst_shared = null;
      if ($sharedname) {
        if (!isset($l_user_map[$sharedname])) {
          // создаём заглушку для shared-пользователя?
          // пропустим shared, если его нет
          $dst_shared = null;
        } else {
          $dst_shared = $l_user_map[$sharedname];
        }
      }
      if (insert_file_if_missing($local_conn, $f, $dst_uid, $dst_shared, $has_fd_local)) $ins_files++;
    }
  }
  logmsg("PULL files: добавлено = $ins_files");
}

// ====== PUSH: Local -> Railway ======
function push_local_to_remote($local_conn,$remote_conn){
  logmsg("⬆️ PUSH: Local → Railway");
  $r_user_map = user_map($remote_conn);
  $l_users = pg_query($local_conn, "SELECT * FROM users");
  $created_users = 0;
  if ($l_users) {
    while ($u = pg_fetch_assoc($l_users)) {
      if (!isset($r_user_map[$u['username']])) {
        $new_id = upsert_user_from_row($remote_conn, $u);
        if ($new_id) { $r_user_map[$u['username']] = $new_id; $created_users++; }
      }
    }
  }
  logmsg("PUSH users: создано новых = $created_users");

  $has_fd_remote = has_column($remote_conn, 'files', 'file_data');

  $l_files = pg_query($local_conn, "
    SELECT f.*, u.username AS uploader_name, su.username AS shared_name
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    LEFT JOIN users su ON f.shared_with = su.id
  ");
  $ins_files = 0;
  if ($l_files) {
    while ($f = pg_fetch_assoc($l_files)) {
      $uploader   = $f['uploader_name'];
      $sharedname = $f['shared_name'];
      $dst_uid = $r_user_map[$uploader] ?? null;
      if ($dst_uid===null) {
        // пользователь не попал — пропускаем файл (или создаём юзера текущим блоком)
        continue;
      }
      $dst_shared = null;
      if ($sharedname) {
        $dst_shared = $r_user_map[$sharedname] ?? null;
      }
      if (insert_file_if_missing($remote_conn, $f, $dst_uid, $dst_shared, $has_fd_remote)) $ins_files++;
    }
  }
  logmsg("PUSH files: добавлено = $ins_files");
}

// ===== RUN =====
try {
  if ($mode==='pull') { pull_remote_to_local($remote_conn,$local_conn); }
  elseif ($mode==='push') { push_local_to_remote($local_conn,$remote_conn); }
  else { // both
    pull_remote_to_local($remote_conn,$local_conn);
    push_local_to_remote($local_conn,$remote_conn);
  }
  logmsg("🎉 Синхронизация завершена (mode=$mode).");
  echo "<p style='font:14px Arial;color:green;'>✅ Готово (mode=$mode). См. sync_log.txt</p>";
} catch (Throwable $e) {
  logmsg("💥 Ошибка: ".$e->getMessage());
  echo "<p style='font:14px Arial;color:#b02a37;'>❌ Ошибка: ".htmlspecialchars($e->getMessage())."</p>";
}
