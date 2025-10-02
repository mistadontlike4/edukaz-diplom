<?php
$host = "db";
$user = "edukaz_user"; // логин из docker-compose.yml
$pass = "edukaz_pass"; // пароль из docker-compose.yml
$db   = "edukaz";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
