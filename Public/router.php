<?php

/**
 * 文件作用：PHP 内置 Server 的路由脚本，确保所有“非真实文件”的请求都进入 index.php。
 */

$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';

