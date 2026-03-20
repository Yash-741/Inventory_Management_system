<?php
/**
 * Runtime helpers for local PHP and serverless platforms such as Vercel.
 */

function getRuntimeStorageDir() {
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'inventory-management-system';

    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    return $dir;
}

function getRuntimeDataPath($filename, $seedPath) {
    $storageDir = getRuntimeStorageDir();
    $runtimePath = $storageDir . DIRECTORY_SEPARATOR . $filename;

    if (!file_exists($runtimePath) && file_exists($seedPath)) {
        @copy($seedPath, $runtimePath);
    }

    if (file_exists($runtimePath) && is_readable($runtimePath)) {
        return $runtimePath;
    }

    return $seedPath;
}

function appendRuntimeLog($filename, $content) {
    $storageDir = getRuntimeStorageDir();
    $logPath = $storageDir . DIRECTORY_SEPARATOR . $filename;

    @file_put_contents($logPath, $content, FILE_APPEND);
}
