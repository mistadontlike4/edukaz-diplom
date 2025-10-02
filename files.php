<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Удаление файла (только для админа)
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $file_id = intval($_GET['delete']);

    // находим путь к файлу
    $stmt = $conn->prepare("SELECT filepath FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->bind_result($filepath);
    if ($stmt->fetch()) {
        if (file_exists($filepath)) {
            unlink($filepath); // удаляем сам файл с диска
        }
    }
    $stmt->close();

    // удаляем запись из БД
    $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->close();

    header("Location: files.php");
    exit;
}

// Достаём список файлов
$result = $conn->query("
    SELECT f.id, f.filename, f.filepath, f.is_public, f.uploaded_at, u.username
    FROM files f
    JOIN users u ON f.uploaded_by = u.id
    WHERE f.is_public = 1 OR f.uploaded_by = {$_SESSION['user_id']}
    ORDER BY f.uploaded_at DESC
");<?php
include("db.php");
if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit; }
$res=$conn->query("SELECT f.*, u.username FROM files f JOIN users u ON f.uploaded_by=u.id ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Файлы</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="max-width:900px; width:95%; text-align:left;">
  <h2>📂 Список файлов</h2>
  <table class="file-list">
    <tr><th>Имя файла</th><th>Загрузил</th><th>Дата</th><th>Скачать</th></tr>
    <?php while($f=$res->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($f['original_name']) ?></td>
      <td><?= htmlspecialchars($f['username']) ?></td>
      <td><?= $f['uploaded_at'] ?></td>
      <td><a class="btn ok" href="<?= $f['filename'] ?>" download>⬇ Скачать</a></td>
    </tr>
    <?php endwhile; ?>
  </table>
  <div style="margin-top:20px; text-align:center;">
    <a href="index.php" class="btn danger">⬅ Назад</a>
  </div>
</div>
</body>
</html>
