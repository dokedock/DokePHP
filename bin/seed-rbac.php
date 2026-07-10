<?php

if (php_sapi_name() !== 'cli') {
    echo "cli_only\n";
    exit(1);
}

$basePath = dirname(__DIR__);

require $basePath . DIRECTORY_SEPARATOR . 'Framework' . DIRECTORY_SEPARATOR . 'Foundation' . DIRECTORY_SEPARATOR . 'Autoloader.php';

$loader = new \Framework\Foundation\Autoloader();
$loader->addPsr4('Framework', $basePath . DIRECTORY_SEPARATOR . 'Framework');
$loader->addPsr4('App', $basePath . DIRECTORY_SEPARATOR . 'App');
$loader->register();

$cfgDir = $basePath . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Config';
$config = \Framework\Support\Config::load($cfgDir);
$app = new \Framework\Foundation\Application($basePath, $config);

$dbCfg = $app->config('db', array());
$dsn = is_array($dbCfg) && isset($dbCfg['dsn']) ? trim((string) $dbCfg['dsn']) : '';
if ($dsn === '') {
    echo "db_not_configured\n";
    exit(1);
}

$uid = isset($argv[1]) ? trim((string) $argv[1]) : 'admin';
if ($uid === '') {
    $uid = 'admin';
}

$expSeconds = isset($argv[2]) ? (int) $argv[2] : 0;
if ($expSeconds < 0) {
    $expSeconds = 0;
}

try {
    $migrator = new \Framework\Database\Migrator($app);
    $migrator->migrate($basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
} catch (\Exception $e) {
    echo "migrate_failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Throwable $e) {
    echo "migrate_failed: " . $e->getMessage() . "\n";
    exit(1);
}

$db = $app->db();
$now = date('Y-m-d H:i:s');

$roleId = 0;
$role = $db->fetchOne('SELECT id FROM rbac_roles WHERE name = :n LIMIT 1', array('n' => 'admin'));
if (is_array($role) && isset($role['id'])) {
    $roleId = (int) $role['id'];
} else {
    $db->exec('INSERT INTO rbac_roles (name,title,status,created_at,updated_at) VALUES (:n,:t,1,:c,:u)', array(
        'n' => 'admin',
        't' => 'Administrator',
        'c' => $now,
        'u' => $now,
    ));
    $roleId = (int) $db->lastId();
}

$permId = 0;
$perm = $db->fetchOne('SELECT id FROM rbac_permissions WHERE name = :n LIMIT 1', array('n' => '*'));
if (is_array($perm) && isset($perm['id'])) {
    $permId = (int) $perm['id'];
} else {
    $db->exec('INSERT INTO rbac_permissions (name,title,status,created_at,updated_at) VALUES (:n,:t,1,:c,:u)', array(
        'n' => '*',
        't' => 'All permissions',
        'c' => $now,
        'u' => $now,
    ));
    $permId = (int) $db->lastId();
}

$rp = $db->fetchOne('SELECT role_id FROM rbac_role_permissions WHERE role_id = :r AND permission_id = :p LIMIT 1', array('r' => $roleId, 'p' => $permId));
if (!is_array($rp)) {
    $db->exec('INSERT INTO rbac_role_permissions (role_id, permission_id) VALUES (:r,:p)', array('r' => $roleId, 'p' => $permId));
}

$identityId = 0;
$identity = $db->fetchOne('SELECT id FROM auth_identities WHERE uid = :u LIMIT 1', array('u' => $uid));
if (is_array($identity) && isset($identity['id'])) {
    $identityId = (int) $identity['id'];
} else {
    $db->exec('INSERT INTO auth_identities (uid,name,status,created_at,updated_at) VALUES (:u,:n,1,:c,:up)', array(
        'u' => $uid,
        'n' => $uid,
        'c' => $now,
        'up' => $now,
    ));
    $identityId = (int) $db->lastId();
}

$ir = $db->fetchOne('SELECT identity_id FROM rbac_identity_roles WHERE identity_id = :i AND role_id = :r LIMIT 1', array('i' => $identityId, 'r' => $roleId));
if (!is_array($ir)) {
    $db->exec('INSERT INTO rbac_identity_roles (identity_id, role_id) VALUES (:i,:r)', array('i' => $identityId, 'r' => $roleId));
}

$token = generate_token(32);
$hash = hash('sha256', $token);
$prefix = substr($token, 0, 8);

$expiresAt = null;
if ($expSeconds > 0) {
    $expiresAt = date('Y-m-d H:i:s', time() + $expSeconds);
}

$db->exec(
    'INSERT INTO auth_tokens (identity_id,token_hash,token_prefix,expires_at,revoked_at,last_used_at,created_at,meta) VALUES (:i,:h,:p,:e,NULL,NULL,:c,NULL)',
    array('i' => $identityId, 'h' => $hash, 'p' => $prefix, 'e' => $expiresAt, 'c' => $now)
);

echo "seed_ok\n";
echo "uid=" . $uid . "\n";
echo "identity_id=" . $identityId . "\n";
echo "role=admin\n";
echo "token=" . $token . "\n";
echo "token_hash=" . $hash . "\n";
echo "expires_at=" . ($expiresAt === null ? '' : $expiresAt) . "\n";
echo "bearer=Bearer " . $token . "\n";

function generate_token($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        $bytes = 32;
    }

    $raw = false;
    if (function_exists('random_bytes')) {
        try {
            $raw = random_bytes($bytes);
        } catch (\Exception $e) {
            $raw = false;
        } catch (\Throwable $e) {
            $raw = false;
        }
    }

    if ($raw === false && function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $raw = openssl_random_pseudo_bytes($bytes, $strong);
        if ($raw === false) {
            $raw = false;
        }
    }

    if (!is_string($raw) || $raw === '') {
        $raw = sha1(uniqid('', true) . mt_rand());
        return hash('sha256', $raw . uniqid('', true));
    }

    return bin2hex($raw);
}

