<?php

if (php_sapi_name() !== 'cli') {
    echo "cli_only\n";
    exit(1);
}

$basePath = dirname(__DIR__);
$target = $basePath . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'app.local.php';

$force = false;
$args = array_slice($argv, 1);
foreach ($args as $a) {
    if ($a === '--force') {
        $force = true;
    }
}

if (is_file($target) && !$force) {
    echo "app.local.php_exists\n";
    echo "use --force to overwrite\n";
    exit(1);
}

$kv = array();
foreach ($args as $a) {
    if ($a === '--force') {
        continue;
    }
    $a = (string) $a;
    if (strpos($a, '--') === 0) {
        $a = substr($a, 2);
    }
    $pos = strpos($a, '=');
    if ($pos === false) {
        continue;
    }
    $k = trim(substr($a, 0, $pos));
    $v = trim(substr($a, $pos + 1));
    if ($k === '') {
        continue;
    }
    $kv[$k] = $v;
}

$cfg = array();
foreach ($kv as $k => $v) {
    set_by_dot($cfg, $k, normalize_value($v));
}

$php = "<?php\n\nreturn " . var_export($cfg, true) . ";\n";
if (@file_put_contents($target, $php) === false) {
    echo "write_failed\n";
    exit(1);
}

echo "ok\n";
echo "file=" . $target . "\n";

function normalize_value($v)
{
    $v = (string) $v;
    $lv = strtolower($v);
    if ($lv === 'true' || $lv === '1') {
        return true;
    }
    if ($lv === 'false' || $lv === '0') {
        return false;
    }
    if (preg_match('/^-?\d+$/', $v)) {
        return (int) $v;
    }
    return $v;
}

function set_by_dot(&$arr, $key, $val)
{
    $parts = explode('.', (string) $key);
    $ref =& $arr;
    foreach ($parts as $i => $p) {
        $p = (string) $p;
        if ($p === '') {
            continue;
        }
        if ($i === count($parts) - 1) {
            $ref[$p] = $val;
            return;
        }
        if (!isset($ref[$p]) || !is_array($ref[$p])) {
            $ref[$p] = array();
        }
        $ref =& $ref[$p];
    }
}

