<?php

require_once __DIR__ . '/common.php';

removePhpMemoryTimeLimits();

setExecutor(EXECUTOR_CONSUMER);

$mode = getMode();
$futureOffsetSeconds = getFutureOffsetSeconds();
$batchOffsetSeconds = getBatchOffsetSeconds();
$emailNotificationFrom = getEmailNotificationFrom();

$pdo = loadDatabase();
$redis = loadRedis();

$queueName = getQueueName();

$dataLoadFunction = getDataLoadFunction($mode);
$dataProcessFunction = getDataProcessFunction($mode);

$sleepSeconds = 0;

while (true) {
    try {
        $userId = $redis->lPop($queueName);
        if (!$userId) {
            sleep(1);
            continue;
        }
        if ($user = $dataLoadFunction($pdo, $userId)) {
            $dataProcessFunction($pdo, $user, $emailNotificationFrom);
        }
        $sleepSeconds = 0;
    } catch (PDOException $e) {
        echoNl('Database error: ' . $e->getMessage());
        adaptiveSleep($sleepSeconds);
    } catch (RedisException $e) {
        echoNl('Redis error: ' . $e->getMessage());
        adaptiveSleep($sleepSeconds);
    } catch (\Throwable $e) {
        echoNl('Unknown error: ' . $e->getMessage());
        adaptiveSleep($sleepSeconds);
    }
}

function adaptiveSleep(&$seconds): void
{
    $sleepSeconds = $seconds < 60 ? $seconds + 5 : $seconds;
    sleep($sleepSeconds);
}

function loadEmailCheckUser(\PDO $pdo, $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :userId AND validts > UNIX_TIMESTAMP() AND checked = 0 AND confirmed = 0 AND valid = 0");
    $stmt->execute(['userId' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function loadEmailNotificationUser(\PDO $pdo, $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :userId AND validts > UNIX_TIMESTAMP() AND (confirmed = 1 OR valid = 1)");
    $stmt->execute(['userId' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function processEmailCheckUser(\PDO $pdo, array $user): void
{
    $valid = filter_var($user['email'], FILTER_VALIDATE_EMAIL) ? check_email($user['email']) : 0;
    saveEmailValidationToDb($pdo, $user, $valid);
}

function processEmailNotificationUser(\PDO $pdo, array $user, string $sendFrom): void
{
    $to = (string) $user['email'];
    $message = "{$user['username']}, your subscription is expiring soon";
    send_email($sendFrom, $to, $message);
}

function saveEmailValidationToDb(\PDO $pdo, array $user, int $isEmailValid): void
{
    $updateStmt = $pdo->prepare('UPDATE users SET checked = 1, valid = :valid WHERE id = :id');
    $updateStmt->execute([
        'valid' => $isEmailValid,
        'id' => $user['id']
    ]);
}

function getDataLoadFunction(string $mode): callable
{
    $dataLoadFunctions = [
        MODE_EMAIL_CHECK => 'loadEmailCheckUser',
        MODE_EMAIL_NOTIFICATION => 'loadEmailNotificationUser'
    ];
    return $dataLoadFunctions[$mode];
}

function getDataProcessFunction(string $mode): callable
{
    $dataProcessFunctions = [
        MODE_EMAIL_CHECK => 'processEmailCheckUser',
        MODE_EMAIL_NOTIFICATION => 'processEmailNotificationUser'
    ];
    return $dataProcessFunctions[$mode];
}

function check_email(string $email): int
{
    sleep(rand(1, 60));
    return rand(0, 1);
}

function send_email(string $from, string $to, string $text): void
{
    sleep(rand(1, 10));
}
