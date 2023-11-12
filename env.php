<?php

function loadEnv($filePath): void
{
    if (!file_exists($filePath)) {
        echoNl('.env is not exists');
        sleep(1);
        exit;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        echoNl('.env is empty');
        sleep(1);
        exit;
    }

    foreach ($lines as $line) {
        list($name, $value) = explode('=', $line, 2);

        if (!getenv($name)) {
            putenv("$name=$value");
        }
    }
}

$envFilePath = __DIR__ . '/.env';

loadEnv($envFilePath);
