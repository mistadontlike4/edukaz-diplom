<?php
require 'db.php';

$username = 'admin';
$password = 'qwerty123';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "❌ Пользователь admin не найден в базе<br>";
} else {
    echo "✅ Пользователь найден: <pre>";
    print_r($user);
    echo "</pre>";

    if (password_verify($password, $user['password_hash'])) {
        echo "🎉 Пароль подходит!";
    } else {
        echo "❌ Пароль НЕ подходит!";
    }
}
