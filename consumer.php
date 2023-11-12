<?php

require_once __DIR__ . '/env.php';

ini_set('memory_limit',-1);
set_time_limit(0);

$host = getenv('DB_HOST');
$database = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASS');

$redisHost = getenv('REDIS_HOST');
$redisPort = getenv('REDIS_PORT');

$sendFrom = getenv('SEND_FROM') ?: 'service@example.net';

const PUBLISHER_DATA_EMAIL_NOTIFICATION = 'email_notification';
const PUBLISHER_DATA_EMAIL_CHECK = 'email_check';

$publisherData = getenv('PUBLISHER_DATA');
if (!in_array($publisherData, [PUBLISHER_DATA_EMAIL_NOTIFICATION, PUBLISHER_DATA_EMAIL_CHECK])) {
    echoNl('Need to specify PUBLISHER_DATA env var, values must be email_notification or email_check');
    exit;
}

$batchOffsetSeconds = (int) getenv('BATCH_OFFSET_TIME');
$futureOffsetSeconds = (int) getenv('FUTURE_OFFSET_TIME');

if (!$batchOffsetSeconds || !$futureOffsetSeconds) {
    echoNl('Need to specify BATCH_OFFSET_TIME and FUTURE_OFFSET_TIME env vars');
    sleep(60);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
} catch (PDOException $e) {
    echoNl('Database connection error: ' . $e->getMessage());
    sleep(60);
    exit;
}

$redis = new Redis();
if (!$redis->connect($redisHost, $redisPort)) {
    echoNl('Redis connection error');
    sleep(60);
    exit;
}

$adaptiveSleepTime = 0;
$queueName = "{$publisherData}_{$futureOffsetSeconds}_{$batchOffsetSeconds}";

$dataLoadCallbacks = [
    PUBLISHER_DATA_EMAIL_CHECK => 'loadEmailCheckUser',
    PUBLISHER_DATA_EMAIL_NOTIFICATION => 'loadEmailNotificationUser'
];
$dataLoadCallback = $dataLoadCallbacks[$publisherData];


$dataProcessCallbacks = [
    PUBLISHER_DATA_EMAIL_CHECK => 'processEmailCheckUser',
    PUBLISHER_DATA_EMAIL_NOTIFICATION => 'processEmailNotificationUser'
];
$dataProcessCallback = $dataProcessCallbacks[$publisherData];

while (true) {
    try {
        $userId = $redis->lPop($queueName);
        if ($userId) {
            $user = $dataLoadCallback($pdo, $userId);

            if ($user) {
                $dataProcessCallback($pdo, $user, $sendFrom);
            }
        } else {
            sleep(1);
        }
        $adaptiveSleepTime = 0;
    } catch (PDOException $e) {
        echoNl('Database error: ' . $e->getMessage());
        adaptiveSleep($adaptiveSleepTime);
    } catch (RedisException $e) {
        echoNl('Redis error: ' . $e->getMessage());
        adaptiveSleep($adaptiveSleepTime);
    } catch (\Throwable $e) {
        echoNl('Unknown error: ' . $e->getMessage());
        adaptiveSleep($adaptiveSleepTime);
    }
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

function processEmailCheckUser(\PDO $pdo, array $user)
{
    if (filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $isEmailValid = check_email($user['email']);
    } else {
        $isEmailValid = 0;
    }
    updateUser($pdo, $user, $isEmailValid);
}

function processEmailNotificationUser(\PDO $pdo, array $user, $sendFrom)
{
    $to = $user['email'];
    $message = "{$user['username']}, your subscription is expiring soon";
    send_email($sendFrom, $to, $message);
}

function updateUser(\PDO $pdo, array $user, int $isEmailValid): void
{
    $updateStmt = $pdo->prepare('UPDATE users SET checked = 1, valid = :valid WHERE id = :id');
    $updateStmt->execute([
        'valid' => $isEmailValid,
        'id' => $user['id']
    ]);
}

function check_email(string $email): int
{
    sleep(rand(1, 60));
    return rand(0, 1);
}

function send_email($from, $to, $text)
{
    sleep(rand(1, 10));
}

function adaptiveSleep(&$adaptiveSleepTime)
{
    sleep($adaptiveSleepTime);
    $adaptiveSleepTime = $adaptiveSleepTime < 60 ? $adaptiveSleepTime++ : $adaptiveSleepTime;
}

function echoNl($message)
{
    echo $message . PHP_EOL;
}
