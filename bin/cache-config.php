<?php

$basePath = dirname(__DIR__);

require $basePath . DIRECTORY_SEPARATOR . 'Framework' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Autoloader.php';

$loader = new \Framework\Foundation\Autoloader();
$loader->addPsr4('Framework', $basePath . DIRECTORY_SEPARATOR . 'Framework');
$loader->addPsr4('App', $basePath . DIRECTORY_SEPARATOR . 'App');
$loader->register();

$cfgDir = $basePath . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Config';
$config = \Framework\Support\Config::load($cfgDir);

$dir = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$file = $dir . DIRECTORY_SEPARATOR . 'config.php';
$export = var_export($config, true);
$php = "<?php\n\nreturn " . $export . ";\n";

file_put_contents($file, $php, LOCK_EX);
echo "config cached: " . $file . "\n";

