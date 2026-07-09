<?php

$basePath = dirname(__DIR__);

require $basePath . DIRECTORY_SEPARATOR . 'Framework' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Autoloader.php';

$loader = new \Framework\Foundation\Autoloader();
$loader->addPsr4('Framework', $basePath . DIRECTORY_SEPARATOR . 'Framework');
$loader->addPsr4('App', $basePath . DIRECTORY_SEPARATOR . 'App');
$loader->register();

$cfgDir = $basePath . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Config';
$config = \Framework\Support\Config::load($cfgDir);
$app = new \Framework\Foundation\Application($basePath, $config);

$dir = $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
if (isset($argv[1]) && $argv[1] !== '') {
    $dir = (string) $argv[1];
}

$migrator = new \Framework\Database\Migrator($app);
$res = $migrator->migrate($dir);

echo "applied:\n";
foreach ($res['applied'] as $f) {
    echo "  - " . $f . "\n";
}
echo "skipped:\n";
foreach ($res['skipped'] as $f) {
    echo "  - " . $f . "\n";
}

