<?php

require_once __DIR__ . '/env.php';

const MODE_EMAIL_NOTIFICATION = 'email_notification';
const MODE_EMAIL_CHECK = 'email_check';

const EXECUTOR_PUBLISHER = 'publisher';
const EXECUTOR_CONSUMER = 'consumer';

$k8executor = null;

function setExecutor(string $name): void
{
    global $k8executor;
    $k8executor = $name;
}

function getExecutor(): string
{
    global $k8executor;
    return $k8executor;
}

function removePhpMemoryTimeLimits(): void
{
    ini_set('memory_limit',-1);
    set_time_limit(0);
}

function getQueueName(): string
{
    return getMode() . '_' . getFutureOffsetSeconds() . '_' . getBatchOffsetSeconds();
}

function getFutureOffsetSeconds(): int
{
    $futureOffsetSeconds = (int) getenv('FUTURE_OFFSET_SECONDS');
    if (!$futureOffsetSeconds) {
        echoNl('Need to specify FUTURE_OFFSET_SECONDS environment variable');
        sleep(1);
        exit;
    }
    return $futureOffsetSeconds;
}

function getBatchOffsetSeconds(): int
{
    $batchOffsetSeconds = (int) getenv('BATCH_OFFSET_SECONDS');
    if (!$batchOffsetSeconds) {
        echoNl('Need to specify BATCH_OFFSET_SECONDS environment variable');
        sleep(1);
        exit;
    }
    return $batchOffsetSeconds;
}

function getEmailNotificationFrom(): string
{
    $sendFrom = (string) getenv('EMAIL_NOTIFICATION_FROM');
    if (getMode() === MODE_EMAIL_NOTIFICATION && !$sendFrom) {
        echoNl('Need to specify EMAIL_NOTIFICATION_FROM environment variable');
        sleep(1);
        exit;
    }
    return $sendFrom;
}

function getMode(): string
{
    $type = getenv('MODE');
    if (!in_array($type, [MODE_EMAIL_NOTIFICATION, MODE_EMAIL_CHECK])) {
        echoNl('Need to specify MODE environment variable, values must be email_notification or email_check');
        sleep(1);
        exit;
    }
    return $type;
}

function loadDatabase(): \PDO
{
    try {
        $host = getenv('DB_HOST');
        $database = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $password = getenv('DB_PASS');
        $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
        return $pdo;
    } catch (PDOException $e) {
        echoNl('Database error: ' . $e->getMessage());
        sleepIfExecutorConsumer();
        exit;
    } catch (\Throwable $e) {
        echoNl('Unknown error: ' . $e->getMessage());
        sleepIfExecutorConsumer();
        exit;
    }
}

function loadRedis(): \Redis
{
    try {
        $redisHost = getenv('REDIS_HOST');
        $redisPort = getenv('REDIS_PORT');
        $redis = new Redis();
        if ($redis->connect($redisHost, $redisPort)) {
            return $redis;
        }
        throw new RedisException('failed to connect');
    } catch (RedisException $e) {
        echoNl('Redis error: ' . $e->getMessage());
        sleepIfExecutorConsumer();
        exit;
    } catch (\Throwable $e) {
        echoNl('Unknown error: ' . $e->getMessage());
        sleepIfExecutorConsumer();
        exit;
    }
}

function sleepIfExecutorConsumer(int $seconds = 30)
{
    if (getExecutor() === EXECUTOR_CONSUMER) {
        sleep(60);
    }
}

function echoNl(string $message): void
{
    echo $message . PHP_EOL;
}
