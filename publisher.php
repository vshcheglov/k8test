<?php

$currentUnixTime = time();

require_once __DIR__ . '/common.php';

removePhpMemoryTimeLimits();

setExecutor(EXECUTOR_PUBLISHER);

$mode = getMode();
$futureOffsetSeconds = getFutureOffsetSeconds();
$batchOffsetSeconds = getBatchOffsetSeconds();

$queueName = getQueueName();
$lastExecutionTimeFilePath = __DIR__ . "/last/$queueName";
$lastExecutionTime = readLastExecutionTime($lastExecutionTimeFilePath);

$batchStartTime = calculateBatchStartTime($currentUnixTime, $futureOffsetSeconds, $batchOffsetSeconds, $lastExecutionTime);
$batchEndTime = $currentUnixTime + $futureOffsetSeconds + $batchOffsetSeconds;

$pdo = loadDatabase();
$redis = loadRedis();

$dataLoadFunction = getDataLoadFunction($mode);

try {
    $results = $dataLoadFunction($pdo, $batchStartTime, $batchEndTime);
    foreach ($results as $row) {
        $userId = $row['id'];
        $redis->rPush($queueName, $userId);
    }
    saveLastExecutionTime($lastExecutionTimeFilePath, $batchEndTime);
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

function getDataLoadFunction(string $mode): callable
{
    $dataLoadFunctions = [
        MODE_EMAIL_CHECK => 'loadEmailCheckData',
        MODE_EMAIL_NOTIFICATION => 'loadEmailNotificationData'
    ];
    return $dataLoadFunctions[$mode];
}

function readLastExecutionTime(string $lastExecutionTimeFilePath): int|false
{
    return file_exists($lastExecutionTimeFilePath) ? (int) file_get_contents($lastExecutionTimeFilePath) : false;
}

function saveLastExecutionTime(string $lastExecutionTimeFilePath, int $batchEndTime): void
{
    file_put_contents($lastExecutionTimeFilePath, $batchEndTime);
}

function calculateBatchStartTime(int $currentUnixTime, int $futureOffsetSeconds, int $batchOffsetSeconds, int|false $lastExecutionTime): int
{
    $batchStartTime = $currentUnixTime + $futureOffsetSeconds;
    if ($lastExecutionTime && is_numeric($lastExecutionTime)) {
        $deltaTime = $currentUnixTime + $futureOffsetSeconds + $batchOffsetSeconds - (int)$lastExecutionTime;
        $batchStartTime = $deltaTime > $batchOffsetSeconds ? $batchStartTime : (int)$lastExecutionTime;
    }
    return $batchStartTime;
}
