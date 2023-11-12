<?php

require_once __DIR__ . '/env.php';

ini_set('memory_limit', -1);
set_time_limit(0);

$host = getenv('DB_HOST');
$database = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASS');

$pdo = new PDO("mysql:host=$host;dbname=$database", $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);

$insertValues = [];
$batchSize = 5000;
for ($i = 0; $i < 15000000; $i++) {
    $username = "user_" . $i;
    $email = $username . "@example.net";

    $addOrSubtract = (mt_rand(0, 100) < 90) ? 1 : -1; // 10% subscriptions are expired
    $randomSeconds = mt_rand(0, 30 * 24 * 60 * 60); // random monthly subscription time
    $validts = (mt_rand(0, 100) < 20) ? time() + ($addOrSubtract * $randomSeconds) : 0; // 20% subscribed

    $confirmed = (mt_rand(0, 100) < 15) ? 1 : 0; // 20% confirmed
    $checked = 0;
    $valid = 0;

    $insertValues[] = "('$username', '$email', $validts, $confirmed, $checked, $valid)";

    if (($i + 1) % $batchSize == 0 || $i == 6000000 - 1) {
        $sql = "INSERT INTO users (username, email, validts, confirmed, checked, valid) VALUES ";
        $sql .= implode(',', $insertValues);
        $pdo->exec($sql);
        $insertValues = [];
    }
}
