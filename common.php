<?php

require_once __DIR__ . '/env.php';

const PUBLISHER_DATA_EMAIL_NOTIFICATION = 'email_notification';
const PUBLISHER_DATA_EMAIL_CHECK = 'email_check';

function removePhpMemoryTimeLimits(): void
{
    ini_set('memory_limit',-1);
    set_time_limit(0);
}

function getQueueName(): string
{
    return getPublisherData() . '_' . getFutureOffsetSeconds() . '_' . getBatchOffsetSeconds();
}

function getFutureOffsetSeconds(): int
{
    $futureOffsetSeconds = (int) getenv('FUTURE_OFFSET_TIME');
    if (!$futureOffsetSeconds) {
        echoNl('Need to specify FUTURE_OFFSET_TIME environment variable');
        sleep(1);
        exit;
    }
    return $futureOffsetSeconds;
}

function getBatchOffsetSeconds(): int
{
    $batchOffsetSeconds = (int) getenv('BATCH_OFFSET_TIME');
    if (!$batchOffsetSeconds) {
        echoNl('Need to specify BATCH_OFFSET_TIME environment variable');
        sleep(1);
        exit;
    }
    return $batchOffsetSeconds;
}

function getEmailNotificationSender(): string
{
    $sendFrom = (string) getenv('SEND_FROM');
    if (!$sendFrom) {
        echoNl('Need to specify SEND_FROM environment variable');
        sleep(1);
        exit;
    }
    return $sendFrom;
}

function getPublisherData(): string
{
    $publisherData = getenv('PUBLISHER_DATA');
    if (!in_array($publisherData, [PUBLISHER_DATA_EMAIL_NOTIFICATION, PUBLISHER_DATA_EMAIL_CHECK])) {
        echoNl('Need to specify PUBLISHER_DATA env var, values must be email_notification or email_check');
        sleep(1);
        exit;
    }
    return $publisherData;
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
        sleep(1);
        exit;
    } catch (\Throwable $e) {
        echoNl('Unknown error: ' . $e->getMessage());
        sleep(1);
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
        sleep(1);
        exit;
    } catch (\Throwable $e) {
        echoNl('Unknown error: ' . $e->getMessage());
        sleep(1);
        exit;
    }
}

function echoNl($message): void
{
    echo $message . PHP_EOL;
}
