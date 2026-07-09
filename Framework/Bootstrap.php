<?php

/**
 * 文件作用：框架启动引导文件，负责初始化常量、注册自动加载、加载配置与路由，然后启动应用。
 */

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

if (!interface_exists('Throwable', false)) {
    interface Throwable
    {
    }
}

$basePath = dirname(__DIR__);

require $basePath . DS . 'Framework' . DS . 'Foundation' . DS . 'Autoloader.php';

$loader = new \Framework\Foundation\Autoloader();
$loader->addPsr4('Framework', $basePath . DS . 'Framework');
$loader->addPsr4('App', $basePath . DS . 'App');
$loader->register();

$cfgDir = $basePath . DS . 'App' . DS . 'Config';
$config = \Framework\Support\Config::load($cfgDir);

$tz = \Framework\Support\Config::get($config, 'app.timezone', '');
if ($tz !== '') {
    @date_default_timezone_set($tz);
}

$debug = (bool) \Framework\Support\Config::get($config, 'app.debug', false);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

$app = new \Framework\Foundation\Application($basePath, $config);
$app->registerErrorHandling();

$router = $app->router();
$routesFile = $basePath . DS . 'App' . DS . 'Routes' . DS . 'web.php';
if (is_file($routesFile)) {
    include $routesFile;
}

$app->run();
