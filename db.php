<?php
$host = "localhost";
$user = "root"; // или свой логин
$pass = "";     // или свой пароль
$db   = "edukaz";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
