<?php

require_once __DIR__ . '/common.php';

removePhpMemoryTimeLimits();

$pdo = loadDatabase();

$insertValues = [];
$batchSize = 5000;
for ($i = 0; $i < 15000000; $i++) {
    $username = "user_" . $i;
    $email = $username . "@example.net";

    $addOrSubtract = (mt_rand(0, 100) < 90) ? 1 : -1; // 10% subscriptions are expired
    $randomSeconds = mt_rand(0, 30 * 24 * 60 * 60); // random monthly subscription time
    $validts = (mt_rand(0, 100) < 20) ? time() + ($addOrSubtract * $randomSeconds) : 0; // 20% subscribed

    $confirmed = (mt_rand(0, 100) < 15) ? 1 : 0; // 15% confirmed email
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
