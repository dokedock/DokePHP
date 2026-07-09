<?php

/**
 * 文件作用：迁移脚本（migrate/status/rollback），用于管理 database/migrations 下的 SQL 文件。
 */

$basePath = dirname(__DIR__);

require $basePath . DIRECTORY_SEPARATOR . 'Framework' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Autoloader.php';

$loader = new \Framework\Foundation\Autoloader();
$loader->addPsr4('Framework', $basePath . DIRECTORY_SEPARATOR . 'Framework');
$loader->addPsr4('App', $basePath . DIRECTORY_SEPARATOR . 'App');
$loader->register();

$cfgDir = $basePath . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Config';
$config = \Framework\Support\Config::load($cfgDir);
$app = new \Framework\Foundation\Application($basePath, $config);

$cmd = isset($argv[1]) ? (string) $argv[1] : 'migrate';
$dir = $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$arg2 = isset($argv[2]) ? (string) $argv[2] : '';
$arg3 = isset($argv[3]) ? (string) $argv[3] : '';

if ($cmd !== 'migrate' && $cmd !== 'status' && $cmd !== 'rollback') {
    $dir = $cmd !== '' ? $cmd : $dir;
    $cmd = 'migrate';
}

$migrator = new \Framework\Database\Migrator($app);

if ($cmd === 'status') {
    $rows = $migrator->status($dir);
    foreach ($rows as $r) {
        $name = isset($r['filename']) ? (string) $r['filename'] : '';
        $applied = !empty($r['applied']) ? 'yes' : 'no';
        echo $applied . "\t" . $name . "\n";
    }
    exit(0);
}

if ($cmd === 'rollback') {
    $steps = $arg2 !== '' ? (int) $arg2 : 1;
    $res = $migrator->rollback($dir, $steps);
    echo "rolled_back:\n";
    foreach ($res['rolled_back'] as $f) {
        echo "  - " . $f . "\n";
    }
    echo "skipped:\n";
    foreach ($res['skipped'] as $f) {
        echo "  - " . $f . "\n";
    }
    exit(0);
}

if ($arg2 !== '') {
    $dir = $arg2;
}

$res = $migrator->migrate($dir);
echo "applied:\n";
foreach ($res['applied'] as $f) {
    echo "  - " . $f . "\n";
}
echo "skipped:\n";
foreach ($res['skipped'] as $f) {
    echo "  - " . $f . "\n";
}
