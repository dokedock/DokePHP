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

$db = $app->db();

$roles = $db->fetchAll('SELECT id,name,title,status,created_at,updated_at FROM rbac_roles ORDER BY id ASC');
$perms = $db->fetchAll('SELECT id,name,title,status,created_at,updated_at FROM rbac_permissions ORDER BY id ASC');
$rolePerms = $db->fetchAll(
    "SELECT r.name AS role_name, p.name AS permission_name
     FROM rbac_role_permissions rp
     JOIN rbac_roles r ON r.id = rp.role_id
     JOIN rbac_permissions p ON p.id = rp.permission_id
     ORDER BY r.name ASC, p.name ASC"
);
$identityRoles = $db->fetchAll(
    "SELECT i.uid AS uid, r.name AS role_name
     FROM rbac_identity_roles ir
     JOIN auth_identities i ON i.id = ir.identity_id
     JOIN rbac_roles r ON r.id = ir.role_id
     ORDER BY i.uid ASC, r.name ASC"
);

$payload = array(
    'format' => 'dokephp-rbac-export',
    'format_version' => 1,
    'exported_at' => date('Y-m-d H:i:s'),
    'config' => array(
        'auth_driver' => (string) $app->config('auth.driver', 'file'),
        'rbac_driver' => (string) $app->config('rbac.driver', 'config'),
        'rbac_enabled' => (bool) $app->config('rbac.enabled', false),
    ),
    'roles' => is_array($roles) ? $roles : array(),
    'permissions' => is_array($perms) ? $perms : array(),
    'role_permissions' => is_array($rolePerms) ? $rolePerms : array(),
    'identity_roles' => is_array($identityRoles) ? $identityRoles : array(),
);

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
if (!is_string($json)) {
    echo "json_encode_failed\n";
    exit(1);
}

$out = isset($argv[1]) ? trim((string) $argv[1]) : '';
if ($out !== '') {
    if (strpos($out, DIRECTORY_SEPARATOR) !== 0) {
        $out = $basePath . DIRECTORY_SEPARATOR . ltrim($out, '/\\');
    }
    if (@file_put_contents($out, $json) === false) {
        echo "write_failed\n";
        exit(1);
    }
    echo "ok\n";
    echo "file=" . $out . "\n";
    exit(0);
}

echo $json . "\n";
