<?php
$host = getenv('DB_HOST') ?: "dpg-d3ia3cc9c44c73ajuq6g-a.render.com" ;
$user = "admin"; // логин из docker-compose.yml
$pass = "GsZxkEXSahECU0kGEkeEqjGXDezH8FNn"; // пароль из docker-compose.yml
$db   = "edukazdb";


// Используем расширение pgsql для PostgreSQL
$conn = pg_connect("host=$host dbname=$db user=$user password=$pass");
if (!$conn) {
    die("Ошибка подключения: " . pg_last_error());
}
?>
$host = getenv('DB_HOST') ?: 'db';

