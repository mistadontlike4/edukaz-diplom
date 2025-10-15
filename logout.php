<?php
// Всегда первым делом запускаем сессию
session_start();

// Удаляем все данные сессии
$_SESSION = [];

// Уничтожаем саму сессию (файл на сервере)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Безопасное перенаправление
header("Location: login.php");
exit;
?>
