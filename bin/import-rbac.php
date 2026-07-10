<?php

if (php_sapi_name() !== 'cli') {
    echo "cli_only\n";
    exit(1);
}

$basePath = dirname(__DIR__);

$input = isset($argv[1]) ? trim((string) $argv[1]) : '';
if ($input === '') {
    echo "usage: php bin/import-rbac.php <file.json> [--mode=merge|replace] [--purge=0|1] [--dry_run=0|1]\n";
    exit(1);
}

if (strpos($input, DIRECTORY_SEPARATOR) !== 0) {
    $input = $basePath . DIRECTORY_SEPARATOR . ltrim($input, '/\\');
}
if (!is_file($input) || !is_readable($input)) {
    echo "file_not_readable\n";
    exit(1);
}

$mode = 'merge';
$purge = false;
$dryRun = false;
$args = array_slice($argv, 2);
foreach ($args as $a) {
    $a = (string) $a;
    if (strpos($a, '--mode=') === 0) {
        $mode = strtolower(trim(substr($a, 7)));
        continue;
    }
    if (strpos($a, '--purge=') === 0) {
        $purge = trim(substr($a, 8)) === '1';
        continue;
    }
    if (strpos($a, '--dry_run=') === 0) {
        $dryRun = trim(substr($a, 10)) === '1';
        continue;
    }
}
if (!in_array($mode, array('merge', 'replace'), true)) {
    $mode = 'merge';
}

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

$json = file_get_contents($input);
if (!is_string($json) || $json === '') {
    echo "empty_file\n";
    exit(1);
}

$payload = json_decode($json, true);
if (!is_array($payload)) {
    echo "invalid_json\n";
    exit(1);
}

$format = isset($payload['format']) ? trim((string) $payload['format']) : '';
if ($format !== '' && $format !== 'dokephp-rbac-export') {
    echo "invalid_format\n";
    exit(1);
}

$roles = isset($payload['roles']) && is_array($payload['roles']) ? $payload['roles'] : array();
$perms = isset($payload['permissions']) && is_array($payload['permissions']) ? $payload['permissions'] : array();
$rolePerms = isset($payload['role_permissions']) && is_array($payload['role_permissions']) ? $payload['role_permissions'] : array();
$identityRoles = isset($payload['identity_roles']) && is_array($payload['identity_roles']) ? $payload['identity_roles'] : array();

$db = $app->db();
$now = date('Y-m-d H:i:s');

$stats = array();
if ($dryRun) {
    $stats = dry_run_stats($db, $mode, $purge, $roles, $perms, $rolePerms, $identityRoles);
    $stats['dry_run'] = 1;
    echo json_encode($stats, JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

$stats = array(
    'mode' => $mode,
    'purge' => $purge ? 1 : 0,
    'roles_upserted' => 0,
    'permissions_upserted' => 0,
    'role_permissions_set' => 0,
    'identity_roles_set' => 0,
    'identities_created' => 0,
    'roles_deleted' => 0,
    'permissions_deleted' => 0,
);

$db->transaction(function ($db) use (&$stats, $mode, $purge, $roles, $perms, $rolePerms, $identityRoles, $now) {
    $roleNameToId = array();
    $permNameToId = array();

    foreach ($roles as $r) {
        if (!is_array($r)) {
            continue;
        }
        $name = isset($r['name']) ? trim((string) $r['name']) : '';
        if ($name === '') {
            continue;
        }
        $title = array_key_exists('title', $r) ? (string) $r['title'] : null;
        $status = array_key_exists('status', $r) ? (int) $r['status'] : 1;
        if ($status !== 0 && $status !== 1) {
            $status = 1;
        }

        $row = $db->fetchOne('SELECT id FROM rbac_roles WHERE name = :n LIMIT 1', array('n' => $name));
        if (is_array($row) && isset($row['id'])) {
            $id = (int) $row['id'];
            $db->exec('UPDATE rbac_roles SET title = :t, status = :s, updated_at = :u WHERE id = :id', array(
                't' => $title,
                's' => $status,
                'u' => $now,
                'id' => $id,
            ));
            $roleNameToId[$name] = $id;
            $stats['roles_upserted']++;
            continue;
        }

        $db->exec('INSERT INTO rbac_roles (name,title,status,created_at,updated_at) VALUES (:n,:t,:s,:c,:u)', array(
            'n' => $name,
            't' => $title,
            's' => $status,
            'c' => $now,
            'u' => $now,
        ));
        $id = (int) $db->lastId();
        $roleNameToId[$name] = $id;
        $stats['roles_upserted']++;
    }

    foreach ($perms as $p) {
        if (!is_array($p)) {
            continue;
        }
        $name = isset($p['name']) ? trim((string) $p['name']) : '';
        if ($name === '') {
            continue;
        }
        $title = array_key_exists('title', $p) ? (string) $p['title'] : null;
        $status = array_key_exists('status', $p) ? (int) $p['status'] : 1;
        if ($status !== 0 && $status !== 1) {
            $status = 1;
        }

        $row = $db->fetchOne('SELECT id FROM rbac_permissions WHERE name = :n LIMIT 1', array('n' => $name));
        if (is_array($row) && isset($row['id'])) {
            $id = (int) $row['id'];
            $db->exec('UPDATE rbac_permissions SET title = :t, status = :s, updated_at = :u WHERE id = :id', array(
                't' => $title,
                's' => $status,
                'u' => $now,
                'id' => $id,
            ));
            $permNameToId[$name] = $id;
            $stats['permissions_upserted']++;
            continue;
        }

        $db->exec('INSERT INTO rbac_permissions (name,title,status,created_at,updated_at) VALUES (:n,:t,:s,:c,:u)', array(
            'n' => $name,
            't' => $title,
            's' => $status,
            'c' => $now,
            'u' => $now,
        ));
        $id = (int) $db->lastId();
        $permNameToId[$name] = $id;
        $stats['permissions_upserted']++;
    }

    if ($mode === 'replace') {
        $db->exec('DELETE FROM rbac_role_permissions');
        $db->exec('DELETE FROM rbac_identity_roles');
    }

    foreach ($rolePerms as $rp) {
        if (!is_array($rp)) {
            continue;
        }
        $roleName = isset($rp['role_name']) ? trim((string) $rp['role_name']) : '';
        $permName = isset($rp['permission_name']) ? trim((string) $rp['permission_name']) : '';
        if ($roleName === '' || $permName === '') {
            continue;
        }

        $roleId = array_key_exists($roleName, $roleNameToId) ? (int) $roleNameToId[$roleName] : 0;
        if ($roleId <= 0) {
            $row = $db->fetchOne('SELECT id FROM rbac_roles WHERE name = :n LIMIT 1', array('n' => $roleName));
            $roleId = is_array($row) && isset($row['id']) ? (int) $row['id'] : 0;
            if ($roleId > 0) {
                $roleNameToId[$roleName] = $roleId;
            }
        }

        $permId = array_key_exists($permName, $permNameToId) ? (int) $permNameToId[$permName] : 0;
        if ($permId <= 0) {
            $row = $db->fetchOne('SELECT id FROM rbac_permissions WHERE name = :n LIMIT 1', array('n' => $permName));
            $permId = is_array($row) && isset($row['id']) ? (int) $row['id'] : 0;
            if ($permId > 0) {
                $permNameToId[$permName] = $permId;
            }
        }

        if ($roleId <= 0 || $permId <= 0) {
            continue;
        }

        $row = $db->fetchOne('SELECT role_id FROM rbac_role_permissions WHERE role_id = :r AND permission_id = :p LIMIT 1', array('r' => $roleId, 'p' => $permId));
        if (!is_array($row)) {
            $db->exec('INSERT INTO rbac_role_permissions (role_id, permission_id) VALUES (:r,:p)', array('r' => $roleId, 'p' => $permId));
        }
        $stats['role_permissions_set']++;
    }

    foreach ($identityRoles as $ir) {
        if (!is_array($ir)) {
            continue;
        }
        $uid = isset($ir['uid']) ? trim((string) $ir['uid']) : '';
        $roleName = isset($ir['role_name']) ? trim((string) $ir['role_name']) : '';
        if ($uid === '' || $roleName === '') {
            continue;
        }

        $identity = $db->fetchOne('SELECT id FROM auth_identities WHERE uid = :u LIMIT 1', array('u' => $uid));
        $identityId = is_array($identity) && isset($identity['id']) ? (int) $identity['id'] : 0;
        if ($identityId <= 0) {
            $db->exec('INSERT INTO auth_identities (uid,name,status,created_at,updated_at) VALUES (:u,:n,1,:c,:up)', array(
                'u' => $uid,
                'n' => $uid,
                'c' => $now,
                'up' => $now,
            ));
            $identityId = (int) $db->lastId();
            $stats['identities_created']++;
        }

        $roleId = array_key_exists($roleName, $roleNameToId) ? (int) $roleNameToId[$roleName] : 0;
        if ($roleId <= 0) {
            $row = $db->fetchOne('SELECT id FROM rbac_roles WHERE name = :n LIMIT 1', array('n' => $roleName));
            $roleId = is_array($row) && isset($row['id']) ? (int) $row['id'] : 0;
            if ($roleId > 0) {
                $roleNameToId[$roleName] = $roleId;
            }
        }
        if ($roleId <= 0) {
            continue;
        }

        $row = $db->fetchOne('SELECT identity_id FROM rbac_identity_roles WHERE identity_id = :i AND role_id = :r LIMIT 1', array('i' => $identityId, 'r' => $roleId));
        if (!is_array($row)) {
            $db->exec('INSERT INTO rbac_identity_roles (identity_id, role_id) VALUES (:i,:r)', array('i' => $identityId, 'r' => $roleId));
        }
        $stats['identity_roles_set']++;
    }

    if ($purge) {
        $roleNames = array();
        foreach ($roles as $r) {
            if (is_array($r) && isset($r['name'])) {
                $n = trim((string) $r['name']);
                if ($n !== '') {
                    $roleNames[$n] = true;
                }
            }
        }
        $permNames = array();
        foreach ($perms as $p) {
            if (is_array($p) && isset($p['name'])) {
                $n = trim((string) $p['name']);
                if ($n !== '') {
                    $permNames[$n] = true;
                }
            }
        }

        $allRoles = $db->fetchAll('SELECT id,name FROM rbac_roles');
        if (is_array($allRoles)) {
            foreach ($allRoles as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $name = isset($r['name']) ? (string) $r['name'] : '';
                $id = isset($r['id']) ? (int) $r['id'] : 0;
                if ($id > 0 && $name !== '' && !array_key_exists($name, $roleNames)) {
                    $db->exec('DELETE FROM rbac_role_permissions WHERE role_id = :id', array('id' => $id));
                    $db->exec('DELETE FROM rbac_identity_roles WHERE role_id = :id', array('id' => $id));
                    $db->exec('DELETE FROM rbac_roles WHERE id = :id', array('id' => $id));
                    $stats['roles_deleted']++;
                }
            }
        }

        $allPerms = $db->fetchAll('SELECT id,name FROM rbac_permissions');
        if (is_array($allPerms)) {
            foreach ($allPerms as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $name = isset($p['name']) ? (string) $p['name'] : '';
                $id = isset($p['id']) ? (int) $p['id'] : 0;
                if ($id > 0 && $name !== '' && !array_key_exists($name, $permNames)) {
                    $db->exec('DELETE FROM rbac_role_permissions WHERE permission_id = :id', array('id' => $id));
                    $db->exec('DELETE FROM rbac_permissions WHERE id = :id', array('id' => $id));
                    $stats['permissions_deleted']++;
                }
            }
        }
    }
});

echo json_encode($stats, JSON_UNESCAPED_UNICODE) . "\n";

function dry_run_stats($db, $mode, $purge, $roles, $perms, $rolePerms, $identityRoles)
{
    $stats = array(
        'mode' => (string) $mode,
        'purge' => $purge ? 1 : 0,
        'roles_insert' => 0,
        'roles_update' => 0,
        'permissions_insert' => 0,
        'permissions_update' => 0,
        'role_permissions_insert' => 0,
        'identity_roles_insert' => 0,
        'identities_create' => 0,
        'purge_roles_delete' => 0,
        'purge_permissions_delete' => 0,
        'replace_role_permissions_delete' => 0,
        'replace_identity_roles_delete' => 0,
    );

    $roleNamesIn = array();
    foreach ((array) $roles as $r) {
        if (is_array($r) && isset($r['name'])) {
            $n = trim((string) $r['name']);
            if ($n !== '') {
                $roleNamesIn[$n] = true;
            }
        }
    }
    $permNamesIn = array();
    foreach ((array) $perms as $p) {
        if (is_array($p) && isset($p['name'])) {
            $n = trim((string) $p['name']);
            if ($n !== '') {
                $permNamesIn[$n] = true;
            }
        }
    }

    $existingRoles = $db->fetchAll('SELECT id,name FROM rbac_roles');
    $existingRoleNames = array();
    if (is_array($existingRoles)) {
        foreach ($existingRoles as $r) {
            if (!is_array($r)) {
                continue;
            }
            $n = isset($r['name']) ? trim((string) $r['name']) : '';
            if ($n !== '') {
                $existingRoleNames[$n] = true;
            }
        }
    }

    $existingPerms = $db->fetchAll('SELECT id,name FROM rbac_permissions');
    $existingPermNames = array();
    if (is_array($existingPerms)) {
        foreach ($existingPerms as $p) {
            if (!is_array($p)) {
                continue;
            }
            $n = isset($p['name']) ? trim((string) $p['name']) : '';
            if ($n !== '') {
                $existingPermNames[$n] = true;
            }
        }
    }

    foreach ($roleNamesIn as $n => $v) {
        if (array_key_exists($n, $existingRoleNames)) {
            $stats['roles_update']++;
        } else {
            $stats['roles_insert']++;
        }
    }
    foreach ($permNamesIn as $n => $v) {
        if (array_key_exists($n, $existingPermNames)) {
            $stats['permissions_update']++;
        } else {
            $stats['permissions_insert']++;
        }
    }

    $roleNameExists = $existingRoleNames + $roleNamesIn;
    $permNameExists = $existingPermNames + $permNamesIn;

    $rpPairs = array();
    foreach ((array) $rolePerms as $rp) {
        if (!is_array($rp)) {
            continue;
        }
        $r = isset($rp['role_name']) ? trim((string) $rp['role_name']) : '';
        $p = isset($rp['permission_name']) ? trim((string) $rp['permission_name']) : '';
        if ($r === '' || $p === '') {
            continue;
        }
        if (!array_key_exists($r, $roleNameExists) || !array_key_exists($p, $permNameExists)) {
            continue;
        }
        $rpPairs[$r . '|' . $p] = true;
    }

    $irPairs = array();
    $uids = array();
    foreach ((array) $identityRoles as $ir) {
        if (!is_array($ir)) {
            continue;
        }
        $u = isset($ir['uid']) ? trim((string) $ir['uid']) : '';
        $r = isset($ir['role_name']) ? trim((string) $ir['role_name']) : '';
        if ($u === '' || $r === '') {
            continue;
        }
        if (!array_key_exists($r, $roleNameExists)) {
            continue;
        }
        $uids[$u] = true;
        $irPairs[$u . '|' . $r] = true;
    }

    if ($mode === 'replace') {
        $row1 = $db->fetchOne('SELECT COUNT(*) AS c FROM rbac_role_permissions');
        $row2 = $db->fetchOne('SELECT COUNT(*) AS c FROM rbac_identity_roles');
        $stats['replace_role_permissions_delete'] = is_array($row1) && isset($row1['c']) ? (int) $row1['c'] : 0;
        $stats['replace_identity_roles_delete'] = is_array($row2) && isset($row2['c']) ? (int) $row2['c'] : 0;
        $stats['role_permissions_insert'] = count($rpPairs);
        $stats['identity_roles_insert'] = count($irPairs);
    } else {
        $existingRp = $db->fetchAll(
            "SELECT r.name AS role_name, p.name AS permission_name
             FROM rbac_role_permissions rp
             JOIN rbac_roles r ON r.id = rp.role_id
             JOIN rbac_permissions p ON p.id = rp.permission_id"
        );
        $existingRpPairs = array();
        if (is_array($existingRp)) {
            foreach ($existingRp as $one) {
                if (!is_array($one)) {
                    continue;
                }
                $r = isset($one['role_name']) ? (string) $one['role_name'] : '';
                $p = isset($one['permission_name']) ? (string) $one['permission_name'] : '';
                if ($r !== '' && $p !== '') {
                    $existingRpPairs[$r . '|' . $p] = true;
                }
            }
        }
        foreach ($rpPairs as $k => $v) {
            if (!array_key_exists($k, $existingRpPairs)) {
                $stats['role_permissions_insert']++;
            }
        }

        $existingIr = $db->fetchAll(
            "SELECT i.uid AS uid, r.name AS role_name
             FROM rbac_identity_roles ir
             JOIN auth_identities i ON i.id = ir.identity_id
             JOIN rbac_roles r ON r.id = ir.role_id"
        );
        $existingIrPairs = array();
        if (is_array($existingIr)) {
            foreach ($existingIr as $one) {
                if (!is_array($one)) {
                    continue;
                }
                $u = isset($one['uid']) ? (string) $one['uid'] : '';
                $r = isset($one['role_name']) ? (string) $one['role_name'] : '';
                if ($u !== '' && $r !== '') {
                    $existingIrPairs[$u . '|' . $r] = true;
                }
            }
        }
        foreach ($irPairs as $k => $v) {
            if (!array_key_exists($k, $existingIrPairs)) {
                $stats['identity_roles_insert']++;
            }
        }
    }

    if (!empty($uids)) {
        $exist = $db->fetchAll('SELECT uid FROM auth_identities');
        $uidExists = array();
        if (is_array($exist)) {
            foreach ($exist as $one) {
                if (is_array($one) && isset($one['uid'])) {
                    $uidExists[(string) $one['uid']] = true;
                }
            }
        }
        foreach ($uids as $u => $v) {
            if (!array_key_exists($u, $uidExists)) {
                $stats['identities_create']++;
            }
        }
    }

    if ($purge) {
        foreach ($existingRoleNames as $n => $v) {
            if (!array_key_exists($n, $roleNamesIn)) {
                $stats['purge_roles_delete']++;
            }
        }
        foreach ($existingPermNames as $n => $v) {
            if (!array_key_exists($n, $permNamesIn)) {
                $stats['purge_permissions_delete']++;
            }
        }
    }

    return $stats;
}
