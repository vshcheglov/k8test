<?php

$currentUnixTime = time();

require_once __DIR__ . '/env.php';

ini_set('memory_limit',-1);
set_time_limit(0);

$host = getenv('DB_HOST');
$database = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASS');

$redisHost = getenv('REDIS_HOST');
$redisPort = getenv('REDIS_PORT');

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
    exit;
}

$queueName = "{$publisherData}_{$futureOffsetSeconds}_{$batchOffsetSeconds}";

if (!is_dir(__DIR__ . '/flag')) {
    mkdir(__DIR__ . '/flag', 0755);
}
$lastExecutionTimeFilePath = __DIR__ . "/flag/{$publisherData}_{$futureOffsetSeconds}_{$batchOffsetSeconds}";
$lastTime = file_exists($lastExecutionTimeFilePath) ? file_get_contents($lastExecutionTimeFilePath) : false;

$startTime = $currentUnixTime + $futureOffsetSeconds;
if ($lastTime && is_numeric($lastTime)) {
    $deltaTime = $currentUnixTime + $futureOffsetSeconds - (int)$lastTime;
    $startTime = $deltaTime > $batchOffsetSeconds ? $startTime : (int)$lastTime;
}

$endTime = $currentUnixTime + $futureOffsetSeconds + $batchOffsetSeconds;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
} catch (PDOException $e) {
    echoNl('Database connection error: ' . $e->getMessage());
    exit;
}

$redis = new Redis();
if (!$redis->connect($redisHost, $redisPort)) {
    echoNl('Redis connection error');
    exit;
}

try {
    $dataLoadFunctions = [
        PUBLISHER_DATA_EMAIL_CHECK => 'loadEmailCheckData',
        PUBLISHER_DATA_EMAIL_NOTIFICATION => 'loadEmailNotificationData'
    ];
    $dataLoadFunction = $dataLoadFunctions[$publisherData];
    $results = $dataLoadFunction($pdo, $startTime, $endTime);
    foreach ($results as $row) {
        $userId = $row['id'];
        $redis->rPush($queueName, $userId);
    }
    file_put_contents($lastExecutionTimeFilePath, $endTime);
} catch (PDOException $e) {
    echoNl('Database error: ' . $e->getMessage());
} catch (RedisException $e) {
    echoNl('Redis error: ' . $e->getMessage());
} catch (\Throwable $e) {
    echoNl('Unknown error: ' . $e->getMessage());
}

function loadEmailCheckData(\PDO $pdo, int $startTime, int $endTime): array
{
    $query = $pdo->prepare("SELECT users.* FROM users
            WHERE checked = 0 AND valid != 1 AND confirmed = 0 AND validts BETWEEN :startTime AND :endTime
            ORDER BY validts ASC");
    $query->execute(['startTime' => $startTime, 'endTime' => $endTime]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function loadEmailNotificationData(\PDO $pdo, int $startTime, int $endTime): array
{
    $query = $pdo->prepare("SELECT * FROM users WHERE (confirmed = 1 OR valid = 1) AND validts BETWEEN :startTime AND :endTime ORDER BY validts ASC");
    $query->execute(['startTime' => $startTime, 'endTime' => $endTime]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function echoNl($message)
{
    echo $message . PHP_EOL;
}
