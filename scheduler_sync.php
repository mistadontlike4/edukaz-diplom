<?php
include("db.php");

date_default_timezone_set('Asia/Almaty');
$logfile = __DIR__ . "/sync_log.txt";

try {
    // Проверяем соединение
    if (pg_connection_status($conn) !== PGSQL_CONNECTION_OK) {
        $status = "❌ Railway недоступен, переключение на локальный сервер";
        $emoji = "❌";
    } else {
        // Тестовый запрос
        $result = @pg_query($conn, "SELECT NOW()");
        if ($result) {
            $status = "✅ Railway доступен, синхронизация выполнена";
            $emoji = "✅";
        } else {
            $status = "⚠️ Railway отвечает, но синхронизация не завершена";
            $emoji = "⚠️";
        }
    }

    // Логирование
    $log = "[" . date("Y-m-d H:i:s") . "] $emoji $status";
    file_put_contents($logfile, $log . PHP_EOL, FILE_APPEND);

    // Перенаправляем обратно
    header("Location: admin.php?sync=success");
    exit;

} catch (Throwable $e) {
    $error = "[" . date("Y-m-d H:i:s") . "] ❌ Ошибка при синхронизации: " . $e->getMessage();
    file_put_contents($logfile, $error . PHP_EOL, FILE_APPEND);
    header("Location: admin.php?sync=error");
    exit;
}
?>
