<?php

function loadEnv($filePath)
{
    if (!file_exists($filePath)) {
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return false;
    }

    foreach ($lines as $line) {
        list($name, $value) = explode('=', $line, 2);

        if (!getenv($name)) {
            putenv("$name=$value");
        }
    }

    return true;
}

$envFilePath = __DIR__ . '/.env';

loadEnv($envFilePath);
