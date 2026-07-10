<?php

if (php_sapi_name() !== 'cli') {
    echo "cli_only\n";
    exit(1);
}

$token = isset($argv[1]) ? (string) $argv[1] : '';
$token = trim($token);
if ($token === '') {
    echo "usage: php bin/hash-token.php <token>\n";
    exit(1);
}

$hash = hash('sha256', $token);
$prefix = substr($token, 0, 8);

echo "token_prefix=" . $prefix . "\n";
echo "token_hash=" . $hash . "\n";

