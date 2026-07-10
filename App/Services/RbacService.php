<?php

namespace App\Services;

use Framework\Foundation\Application;

class RbacService
{
    private $app;
    private $roleCacheByIdentityId = array();
    private $permCacheByRoleKey = array();

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function rolesForIdentityId($identityId)
    {
        $identityId = (int) $identityId;
        if ($identityId <= 0) {
            return array();
        }

        if (array_key_exists($identityId, $this->roleCacheByIdentityId)) {
            return $this->roleCacheByIdentityId[$identityId];
        }

        $sql = "SELECT r.name
                FROM rbac_roles r
                JOIN rbac_identity_roles ir ON ir.role_id = r.id
                WHERE ir.identity_id = :id AND r.status = 1";
        $rows = $this->app->db()->fetchAll($sql, array('id' => $identityId));

        $out = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($name !== '') {
                    $out[] = $name;
                }
            }
        }

        $out = array_values(array_unique($out));
        $this->roleCacheByIdentityId[$identityId] = $out;
        return $out;
    }

    public function permissionsForRoles(array $roles)
    {
        $roles = array_values(array_filter(array_map('trim', $roles), function ($v) {
            return $v !== '';
        }));
        if (empty($roles)) {
            return array();
        }

        sort($roles);
        $key = implode('|', $roles);
        if (array_key_exists($key, $this->permCacheByRoleKey)) {
            return $this->permCacheByRoleKey[$key];
        }

        $params = array();
        $holders = array();
        $i = 0;
        foreach ($roles as $r) {
            $k = 'r' . $i;
            $params[$k] = $r;
            $holders[] = ':' . $k;
            $i++;
        }

        $sql = "SELECT DISTINCT p.name
                FROM rbac_permissions p
                JOIN rbac_role_permissions rp ON rp.permission_id = p.id
                JOIN rbac_roles r ON r.id = rp.role_id
                WHERE r.name IN (" . implode(',', $holders) . ") AND r.status = 1 AND p.status = 1";
        $rows = $this->app->db()->fetchAll($sql, $params);

        $out = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($name !== '') {
                    $out[$name] = true;
                }
            }
        }

        $this->permCacheByRoleKey[$key] = $out;
        return $out;
    }
}

