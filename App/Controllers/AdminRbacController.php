<?php

namespace App\Controllers;

use Framework\Foundation\BaseController;
use Framework\Http\Request;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;
use Framework\Support\Paginator;

class AdminRbacController extends BaseController
{
    public function rolesIndex(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $pg = Paginator::fromRequest($request, 20, 200);
        $where = array();
        $bind = array();

        $name = trim((string) $request->query('name', ''));
        if ($name !== '') {
            $where[] = 'name = :name';
            $bind['name'] = $name;
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(name LIKE :q OR title LIKE :q)';
            $bind['q'] = '%' . $q . '%';
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && ctype_digit($status)) {
            $where[] = 'status = :status';
            $bind['status'] = (int) $status;
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));
        $res = $this->app->db()->paginate(
            'SELECT id,name,title,status,created_at,updated_at FROM rbac_roles' . $whereSql . ' ORDER BY id DESC',
            'SELECT COUNT(*) FROM rbac_roles' . $whereSql,
            $bind,
            $pg['page'],
            $pg['page_size']
        );
        return $this->ok($res);
    }

    public function rolesCreate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $data = $request->all();
        $this->validate($data, array(
            'name' => 'required|string|max:64|regex:/^[A-Za-z0-9_\\-\\.]+$/',
            'title' => 'sometimes|string|max:128',
            'status' => 'sometimes|int|in:0,1',
        ));

        $name = trim((string) $data['name']);
        $title = array_key_exists('title', $data) ? (string) $data['title'] : null;
        $status = array_key_exists('status', $data) ? (int) $data['status'] : 1;
        $now = date('Y-m-d H:i:s');

        $exists = $this->app->db()->fetchOne('SELECT id FROM rbac_roles WHERE name = :n LIMIT 1', array('n' => $name));
        if (is_array($exists)) {
            return Api::fail('role_name_exists', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $this->app->db()->exec(
            'INSERT INTO rbac_roles (name,title,status,created_at,updated_at) VALUES (:n,:t,:s,:c,:u)',
            array('n' => $name, 't' => $title, 's' => $status, 'c' => $now, 'u' => $now)
        );

        $id = (int) $this->app->db()->lastId();
        $row = $this->app->db()->fetchOne('SELECT id,name,title,status,created_at,updated_at FROM rbac_roles WHERE id = :id', array('id' => $id));
        return $this->ok($row);
    }

    public function rolesUpdate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $data = $request->all();
        $this->validate($data, array(
            'title' => 'sometimes|string|max:128',
            'status' => 'sometimes|int|in:0,1',
        ));

        $row = $this->app->db()->fetchOne('SELECT id FROM rbac_roles WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $sets = array();
        $bind = array('id' => $id);
        if (array_key_exists('title', $data)) {
            $sets[] = 'title = :t';
            $bind['t'] = (string) $data['title'];
        }
        if (array_key_exists('status', $data)) {
            $sets[] = 'status = :s';
            $bind['s'] = (int) $data['status'];
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :u';
            $bind['u'] = date('Y-m-d H:i:s');
            $sql = 'UPDATE rbac_roles SET ' . implode(',', $sets) . ' WHERE id = :id';
            $this->app->db()->exec($sql, $bind);
        }

        $out = $this->app->db()->fetchOne('SELECT id,name,title,status,created_at,updated_at FROM rbac_roles WHERE id = :id', array('id' => $id));
        return $this->ok($out);
    }

    public function rolesDelete(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $db = $this->app->db();
        $row = $db->fetchOne('SELECT id FROM rbac_roles WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $db->transaction(function ($db) use ($id) {
            $db->exec('DELETE FROM rbac_role_permissions WHERE role_id = :id', array('id' => $id));
            $db->exec('DELETE FROM rbac_identity_roles WHERE role_id = :id', array('id' => $id));
            $db->exec('DELETE FROM rbac_roles WHERE id = :id', array('id' => $id));
        });

        return $this->ok(array('deleted' => true));
    }

    public function permissionsIndex(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $pg = Paginator::fromRequest($request, 20, 200);
        $where = array();
        $bind = array();

        $name = trim((string) $request->query('name', ''));
        if ($name !== '') {
            $where[] = 'name = :name';
            $bind['name'] = $name;
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(name LIKE :q OR title LIKE :q)';
            $bind['q'] = '%' . $q . '%';
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && ctype_digit($status)) {
            $where[] = 'status = :status';
            $bind['status'] = (int) $status;
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));
        $res = $this->app->db()->paginate(
            'SELECT id,name,title,status,created_at,updated_at FROM rbac_permissions' . $whereSql . ' ORDER BY id DESC',
            'SELECT COUNT(*) FROM rbac_permissions' . $whereSql,
            $bind,
            $pg['page'],
            $pg['page_size']
        );
        return $this->ok($res);
    }

    public function permissionsCreate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $data = $request->all();
        $this->validate($data, array(
            'name' => 'required|string|max:128|regex:/^[A-Za-z0-9_\\-\\.\\*]+$/',
            'title' => 'sometimes|string|max:128',
            'status' => 'sometimes|int|in:0,1',
        ));

        $name = trim((string) $data['name']);
        $title = array_key_exists('title', $data) ? (string) $data['title'] : null;
        $status = array_key_exists('status', $data) ? (int) $data['status'] : 1;
        $now = date('Y-m-d H:i:s');

        $exists = $this->app->db()->fetchOne('SELECT id FROM rbac_permissions WHERE name = :n LIMIT 1', array('n' => $name));
        if (is_array($exists)) {
            return Api::fail('permission_name_exists', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $this->app->db()->exec(
            'INSERT INTO rbac_permissions (name,title,status,created_at,updated_at) VALUES (:n,:t,:s,:c,:u)',
            array('n' => $name, 't' => $title, 's' => $status, 'c' => $now, 'u' => $now)
        );

        $id = (int) $this->app->db()->lastId();
        $row = $this->app->db()->fetchOne('SELECT id,name,title,status,created_at,updated_at FROM rbac_permissions WHERE id = :id', array('id' => $id));
        return $this->ok($row);
    }

    public function permissionsUpdate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $data = $request->all();
        $this->validate($data, array(
            'title' => 'sometimes|string|max:128',
            'status' => 'sometimes|int|in:0,1',
        ));

        $row = $this->app->db()->fetchOne('SELECT id FROM rbac_permissions WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $sets = array();
        $bind = array('id' => $id);
        if (array_key_exists('title', $data)) {
            $sets[] = 'title = :t';
            $bind['t'] = (string) $data['title'];
        }
        if (array_key_exists('status', $data)) {
            $sets[] = 'status = :s';
            $bind['s'] = (int) $data['status'];
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :u';
            $bind['u'] = date('Y-m-d H:i:s');
            $sql = 'UPDATE rbac_permissions SET ' . implode(',', $sets) . ' WHERE id = :id';
            $this->app->db()->exec($sql, $bind);
        }

        $out = $this->app->db()->fetchOne('SELECT id,name,title,status,created_at,updated_at FROM rbac_permissions WHERE id = :id', array('id' => $id));
        return $this->ok($out);
    }

    public function permissionsDelete(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $db = $this->app->db();
        $row = $db->fetchOne('SELECT id FROM rbac_permissions WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $db->transaction(function ($db) use ($id) {
            $db->exec('DELETE FROM rbac_role_permissions WHERE permission_id = :id', array('id' => $id));
            $db->exec('DELETE FROM rbac_permissions WHERE id = :id', array('id' => $id));
        });

        return $this->ok(array('deleted' => true));
    }

    public function rolePermissionsGet(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $roleId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($roleId <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $role = $this->app->db()->fetchOne('SELECT id,name FROM rbac_roles WHERE id = :id LIMIT 1', array('id' => $roleId));
        if (!is_array($role)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $rows = $this->app->db()->fetchAll(
            "SELECT p.id,p.name,p.title,p.status
             FROM rbac_permissions p
             JOIN rbac_role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :id
             ORDER BY p.name ASC",
            array('id' => $roleId)
        );

        return $this->ok(array(
            'role' => $role,
            'permissions' => is_array($rows) ? $rows : array(),
        ));
    }

    public function rolePermissionsSet(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $roleId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($roleId <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $role = $this->app->db()->fetchOne('SELECT id,name FROM rbac_roles WHERE id = :id LIMIT 1', array('id' => $roleId));
        if (!is_array($role)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $data = $request->all();
        $perms = array();
        if (isset($data['permissions'])) {
            $perms = $data['permissions'];
        } elseif (isset($data['permission_names'])) {
            $perms = $data['permission_names'];
        }

        if (is_string($perms)) {
            $perms = array_map('trim', explode(',', $perms));
        }
        if (!is_array($perms)) {
            return Api::fail('permissions_must_be_array', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $mode = isset($data['mode']) ? strtolower(trim((string) $data['mode'])) : 'replace';
        if ($mode === '') {
            $mode = 'replace';
        }
        if (!in_array($mode, array('replace', 'add', 'remove'), true)) {
            $mode = 'replace';
        }

        $perms = array_values(array_unique(array_filter(array_map('trim', $perms), function ($v) {
            return $v !== '';
        })));
        sort($perms);

        $map = $this->permissionIdsByNames($perms);
        $missing = array();
        foreach ($perms as $p) {
            if (!array_key_exists($p, $map)) {
                $missing[] = $p;
            }
        }
        if (!empty($missing)) {
            return $this->ok(array('ok' => false, 'missing_permissions' => $missing), 'missing_permissions');
        }

        $currentRows = $this->app->db()->fetchAll(
            "SELECT p.name
             FROM rbac_permissions p
             JOIN rbac_role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :id",
            array('id' => $roleId)
        );
        $current = array();
        if (is_array($currentRows)) {
            foreach ($currentRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $n = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($n !== '') {
                    $current[$n] = true;
                }
            }
        }

        $target = array();
        if ($mode === 'replace') {
            foreach ($perms as $p) {
                $target[$p] = true;
            }
        } elseif ($mode === 'add') {
            $target = $current;
            foreach ($perms as $p) {
                $target[$p] = true;
            }
        } elseif ($mode === 'remove') {
            $target = $current;
            foreach ($perms as $p) {
                if (array_key_exists($p, $target)) {
                    unset($target[$p]);
                }
            }
        }

        $added = array();
        $removed = array();
        foreach ($target as $p => $v) {
            if (!array_key_exists($p, $current)) {
                $added[] = $p;
            }
        }
        foreach ($current as $p => $v) {
            if (!array_key_exists($p, $target)) {
                $removed[] = $p;
            }
        }
        sort($added);
        sort($removed);

        $dryRun = !empty($data['dry_run']);
        if ($dryRun) {
            $keys = array_keys($target);
            sort($keys);
            return $this->ok(array(
                'role' => $role,
                'dry_run' => true,
                'mode' => $mode,
                'added' => $added,
                'removed' => $removed,
                'permissions' => $keys,
            ));
        }

        $db = $this->app->db();
        $db->transaction(function ($db) use ($roleId, $target, $map) {
            $db->exec('DELETE FROM rbac_role_permissions WHERE role_id = :id', array('id' => $roleId));
            foreach ($target as $name => $vv) {
                $pid = (int) $map[(string) $name];
                $db->exec('INSERT INTO rbac_role_permissions (role_id, permission_id) VALUES (:r,:p)', array('r' => $roleId, 'p' => $pid));
            }
        });

        return $this->ok(array(
            'role' => $role,
            'mode' => $mode,
            'added' => $added,
            'removed' => $removed,
            'permissions' => array_keys($target),
        ));
    }

    public function identitiesIndex(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $pg = Paginator::fromRequest($request, 20, 200);
        $where = array();
        $bind = array();

        $uid = trim((string) $request->query('uid', ''));
        if ($uid !== '') {
            $where[] = 'uid = :uid';
            $bind['uid'] = $uid;
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $where[] = '(uid LIKE :q OR name LIKE :q)';
            $bind['q'] = '%' . $q . '%';
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && ctype_digit($status)) {
            $where[] = 'status = :status';
            $bind['status'] = (int) $status;
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));
        $res = $this->app->db()->paginate(
            'SELECT id,uid,name,status,created_at,updated_at FROM auth_identities' . $whereSql . ' ORDER BY id DESC',
            'SELECT COUNT(*) FROM auth_identities' . $whereSql,
            $bind,
            $pg['page'],
            $pg['page_size']
        );
        return $this->ok($res);
    }

    public function identitiesCreate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $data = $request->all();
        $this->validate($data, array(
            'uid' => 'required|string|max:64|regex:/^[A-Za-z0-9_\\-\\.]+$/',
            'name' => 'sometimes|string|max:128',
            'status' => 'sometimes|int|in:0,1',
        ));

        $uid = trim((string) $data['uid']);
        $name = array_key_exists('name', $data) ? (string) $data['name'] : null;
        $status = array_key_exists('status', $data) ? (int) $data['status'] : 1;
        $now = date('Y-m-d H:i:s');

        $exists = $this->app->db()->fetchOne('SELECT id FROM auth_identities WHERE uid = :u LIMIT 1', array('u' => $uid));
        if (is_array($exists)) {
            return Api::fail('identity_uid_exists', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $this->app->db()->exec(
            'INSERT INTO auth_identities (uid,name,status,created_at,updated_at) VALUES (:u,:n,:s,:c,:up)',
            array('u' => $uid, 'n' => $name, 's' => $status, 'c' => $now, 'up' => $now)
        );

        $id = (int) $this->app->db()->lastId();
        $row = $this->app->db()->fetchOne('SELECT id,uid,name,status,created_at,updated_at FROM auth_identities WHERE id = :id', array('id' => $id));
        return $this->ok($row);
    }

    public function identitiesUpdate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $data = $request->all();
        $this->validate($data, array(
            'name' => 'sometimes|string|max:128',
            'status' => 'sometimes|int|in:0,1',
        ));

        $row = $this->app->db()->fetchOne('SELECT id FROM auth_identities WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $sets = array();
        $bind = array('id' => $id);
        if (array_key_exists('name', $data)) {
            $sets[] = 'name = :n';
            $bind['n'] = (string) $data['name'];
        }
        if (array_key_exists('status', $data)) {
            $sets[] = 'status = :s';
            $bind['s'] = (int) $data['status'];
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :u';
            $bind['u'] = date('Y-m-d H:i:s');
            $sql = 'UPDATE auth_identities SET ' . implode(',', $sets) . ' WHERE id = :id';
            $this->app->db()->exec($sql, $bind);
        }

        $out = $this->app->db()->fetchOne('SELECT id,uid,name,status,created_at,updated_at FROM auth_identities WHERE id = :id', array('id' => $id));
        return $this->ok($out);
    }

    public function identityRolesGet(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $identity = $this->app->db()->fetchOne('SELECT id,uid,name,status FROM auth_identities WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($identity)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $rows = $this->app->db()->fetchAll(
            "SELECT r.id,r.name,r.title,r.status
             FROM rbac_roles r
             JOIN rbac_identity_roles ir ON ir.role_id = r.id
             WHERE ir.identity_id = :id
             ORDER BY r.name ASC",
            array('id' => $id)
        );

        return $this->ok(array(
            'identity' => $identity,
            'roles' => is_array($rows) ? $rows : array(),
        ));
    }

    public function identityRolesSet(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $identity = $this->app->db()->fetchOne('SELECT id,uid,name,status FROM auth_identities WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($identity)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $data = $request->all();
        $roles = array();
        if (isset($data['roles'])) {
            $roles = $data['roles'];
        } elseif (isset($data['role_names'])) {
            $roles = $data['role_names'];
        }

        if (is_string($roles)) {
            $roles = array_map('trim', explode(',', $roles));
        }
        if (!is_array($roles)) {
            return Api::fail('roles_must_be_array', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $mode = isset($data['mode']) ? strtolower(trim((string) $data['mode'])) : 'replace';
        if ($mode === '') {
            $mode = 'replace';
        }
        if (!in_array($mode, array('replace', 'add', 'remove'), true)) {
            $mode = 'replace';
        }

        $roles = array_values(array_unique(array_filter(array_map('trim', $roles), function ($v) {
            return $v !== '';
        })));
        sort($roles);

        $map = $this->roleIdsByNames($roles);
        $missing = array();
        foreach ($roles as $r) {
            if (!array_key_exists($r, $map)) {
                $missing[] = $r;
            }
        }
        if (!empty($missing)) {
            return $this->ok(array('ok' => false, 'missing_roles' => $missing), 'missing_roles');
        }

        $currentRows = $this->app->db()->fetchAll(
            "SELECT r.name
             FROM rbac_roles r
             JOIN rbac_identity_roles ir ON ir.role_id = r.id
             WHERE ir.identity_id = :id",
            array('id' => $id)
        );
        $current = array();
        if (is_array($currentRows)) {
            foreach ($currentRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $n = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($n !== '') {
                    $current[$n] = true;
                }
            }
        }

        $target = array();
        if ($mode === 'replace') {
            foreach ($roles as $r) {
                $target[$r] = true;
            }
        } elseif ($mode === 'add') {
            $target = $current;
            foreach ($roles as $r) {
                $target[$r] = true;
            }
        } elseif ($mode === 'remove') {
            $target = $current;
            foreach ($roles as $r) {
                if (array_key_exists($r, $target)) {
                    unset($target[$r]);
                }
            }
        }

        $added = array();
        $removed = array();
        foreach ($target as $r => $v) {
            if (!array_key_exists($r, $current)) {
                $added[] = $r;
            }
        }
        foreach ($current as $r => $v) {
            if (!array_key_exists($r, $target)) {
                $removed[] = $r;
            }
        }
        sort($added);
        sort($removed);

        $dryRun = !empty($data['dry_run']);
        if ($dryRun) {
            $keys = array_keys($target);
            sort($keys);
            return $this->ok(array(
                'identity' => $identity,
                'dry_run' => true,
                'mode' => $mode,
                'added' => $added,
                'removed' => $removed,
                'roles' => $keys,
            ));
        }

        $db = $this->app->db();
        $db->transaction(function ($db) use ($id, $target, $map) {
            $db->exec('DELETE FROM rbac_identity_roles WHERE identity_id = :id', array('id' => $id));
            foreach ($target as $name => $vv) {
                $rid = (int) $map[(string) $name];
                $db->exec('INSERT INTO rbac_identity_roles (identity_id, role_id) VALUES (:i,:r)', array('i' => $id, 'r' => $rid));
            }
        });

        return $this->ok(array(
            'identity' => $identity,
            'mode' => $mode,
            'added' => $added,
            'removed' => $removed,
            'roles' => array_keys($target),
        ));
    }

    public function tokensIndex(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $pg = Paginator::fromRequest($request, 20, 200);
        $where = array();
        $bind = array();

        $uid = trim((string) $request->query('uid', ''));
        if ($uid !== '') {
            $where[] = 'i.uid = :uid';
            $bind['uid'] = $uid;
        }

        $prefix = trim((string) $request->query('prefix', ''));
        if ($prefix !== '') {
            $where[] = 't.token_prefix = :prefix';
            $bind['prefix'] = $prefix;
        }

        $revoked = trim((string) $request->query('revoked', ''));
        if ($revoked === '1') {
            $where[] = 't.revoked_at IS NOT NULL';
        } elseif ($revoked === '0') {
            $where[] = 't.revoked_at IS NULL';
        }

        $active = trim((string) $request->query('active', ''));
        if ($active === '1') {
            $where[] = 't.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at = "" OR t.expires_at > :now)';
            $bind['now'] = date('Y-m-d H:i:s');
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));
        $res = $this->app->db()->paginate(
            "SELECT t.id,t.identity_id,i.uid,i.name AS identity_name,t.token_prefix,t.expires_at,t.revoked_at,t.last_used_at,t.created_at
             FROM auth_tokens t
             JOIN auth_identities i ON i.id = t.identity_id" . $whereSql . "
             ORDER BY t.id DESC",
            "SELECT COUNT(*)
             FROM auth_tokens t
             JOIN auth_identities i ON i.id = t.identity_id" . $whereSql,
            $bind,
            $pg['page'],
            $pg['page_size']
        );
        return $this->ok($res);
    }

    public function tokensCreate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $data = $request->all();
        $this->validate($data, array(
            'identity_id' => 'sometimes|int',
            'uid' => 'sometimes|string|max:64|regex:/^[A-Za-z0-9_\\-\\.]+$/',
            'expires_at' => 'sometimes|string|max:32|date',
            'exp' => 'sometimes|int',
        ));

        $identityId = array_key_exists('identity_id', $data) ? (int) $data['identity_id'] : 0;
        if ($identityId <= 0 && array_key_exists('uid', $data)) {
            $uid = trim((string) $data['uid']);
            if ($uid !== '') {
                $row = $this->app->db()->fetchOne('SELECT id FROM auth_identities WHERE uid = :u LIMIT 1', array('u' => $uid));
                if (is_array($row) && isset($row['id'])) {
                    $identityId = (int) $row['id'];
                }
            }
        }

        if ($identityId <= 0) {
            return Api::fail('identity_required', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $identity = $this->app->db()->fetchOne('SELECT id,uid,name,status FROM auth_identities WHERE id = :id LIMIT 1', array('id' => $identityId));
        if (!is_array($identity)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $token = $this->generateToken(32);
        $hash = hash('sha256', $token);
        $prefix = substr($token, 0, 8);

        $expiresAt = null;
        if (array_key_exists('expires_at', $data)) {
            $expiresAt = trim((string) $data['expires_at']);
        } elseif (array_key_exists('exp', $data)) {
            $exp = (int) $data['exp'];
            if ($exp > 0) {
                $expiresAt = date('Y-m-d H:i:s', $exp);
            }
        }
        if ($expiresAt === '') {
            $expiresAt = null;
        }

        $now = date('Y-m-d H:i:s');
        $this->app->db()->exec(
            'INSERT INTO auth_tokens (identity_id,token_hash,token_prefix,expires_at,revoked_at,last_used_at,created_at,meta) VALUES (:i,:h,:p,:e,NULL,NULL,:c,NULL)',
            array('i' => $identityId, 'h' => $hash, 'p' => $prefix, 'e' => $expiresAt, 'c' => $now)
        );

        $id = (int) $this->app->db()->lastId();
        return $this->ok(array(
            'id' => $id,
            'identity' => $identity,
            'token' => $token,
            'token_prefix' => $prefix,
            'expires_at' => $expiresAt,
        ));
    }

    public function tokensRevoke(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $row = $this->app->db()->fetchOne('SELECT id,revoked_at FROM auth_tokens WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $now = date('Y-m-d H:i:s');
        $this->app->db()->exec('UPDATE auth_tokens SET revoked_at = :t WHERE id = :id', array('t' => $now, 'id' => $id));

        return $this->ok(array('revoked' => true, 'revoked_at' => $now));
    }

    public function tokensRotate(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $row = $this->app->db()->fetchOne('SELECT id,identity_id,expires_at,revoked_at FROM auth_tokens WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $identityId = isset($row['identity_id']) ? (int) $row['identity_id'] : 0;
        if ($identityId <= 0) {
            return Api::fail('invalid_identity', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $expiresAt = isset($row['expires_at']) ? trim((string) $row['expires_at']) : null;
        if ($expiresAt === '') {
            $expiresAt = null;
        }

        $token = $this->generateToken(32);
        $hash = hash('sha256', $token);
        $prefix = substr($token, 0, 8);
        $now = date('Y-m-d H:i:s');

        $db = $this->app->db();
        $db->transaction(function ($db) use ($id, $identityId, $hash, $prefix, $expiresAt, $now) {
            $db->exec('UPDATE auth_tokens SET revoked_at = :t WHERE id = :id AND revoked_at IS NULL', array('t' => $now, 'id' => $id));
            $db->exec(
                'INSERT INTO auth_tokens (identity_id,token_hash,token_prefix,expires_at,revoked_at,last_used_at,created_at,meta) VALUES (:i,:h,:p,:e,NULL,NULL,:c,NULL)',
                array('i' => $identityId, 'h' => $hash, 'p' => $prefix, 'e' => $expiresAt, 'c' => $now)
            );
        });

        $newId = (int) $db->lastId();

        return $this->ok(array(
            'rotated' => true,
            'revoked_id' => $id,
            'new_id' => $newId,
            'token' => $token,
            'token_prefix' => $prefix,
            'expires_at' => $expiresAt,
        ));
    }

    public function tokensDelete(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $row = $this->app->db()->fetchOne('SELECT id,revoked_at FROM auth_tokens WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($row)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $revokedAt = isset($row['revoked_at']) ? trim((string) $row['revoked_at']) : '';
        if ($revokedAt === '') {
            return Api::fail('token_must_be_revoked_first', ErrorCodes::CONFLICT, 409, null);
        }

        $this->app->db()->exec('DELETE FROM auth_tokens WHERE id = :id', array('id' => $id));
        return $this->ok(array('deleted' => true));
    }

    public function identityTokensRevokeAll(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        if ($id <= 0) {
            return Api::fail('invalid_id', ErrorCodes::VALIDATION_FAILED, 422, null);
        }

        $identity = $this->app->db()->fetchOne('SELECT id,uid,name,status FROM auth_identities WHERE id = :id LIMIT 1', array('id' => $id));
        if (!is_array($identity)) {
            return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $now = date('Y-m-d H:i:s');
        $count = $this->app->db()->exec('UPDATE auth_tokens SET revoked_at = :t WHERE identity_id = :id AND revoked_at IS NULL', array('t' => $now, 'id' => $id));

        return $this->ok(array(
            'identity' => $identity,
            'revoked_count' => (int) $count,
            'revoked_at' => $now,
        ));
    }

    public function snapshot(Request $request, array $params = array())
    {
        if (!$this->hasDb()) {
            return Api::fail('db_not_configured', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $roles = $this->app->db()->fetchAll('SELECT id,name,title,status,created_at,updated_at FROM rbac_roles ORDER BY id ASC');
        $perms = $this->app->db()->fetchAll('SELECT id,name,title,status,created_at,updated_at FROM rbac_permissions ORDER BY id ASC');
        $rolePerms = $this->app->db()->fetchAll(
            "SELECT r.name AS role_name, p.name AS permission_name
             FROM rbac_role_permissions rp
             JOIN rbac_roles r ON r.id = rp.role_id
             JOIN rbac_permissions p ON p.id = rp.permission_id
             ORDER BY r.name ASC, p.name ASC"
        );
        $identityRoles = $this->app->db()->fetchAll(
            "SELECT i.uid AS uid, r.name AS role_name
             FROM rbac_identity_roles ir
             JOIN auth_identities i ON i.id = ir.identity_id
             JOIN rbac_roles r ON r.id = ir.role_id
             ORDER BY i.uid ASC, r.name ASC"
        );

        return $this->ok(array(
            'format' => 'dokephp-rbac-export',
            'format_version' => 1,
            'exported_at' => date('Y-m-d H:i:s'),
            'config' => array(
                'auth_driver' => (string) $this->app->config('auth.driver', 'file'),
                'rbac_driver' => (string) $this->app->config('rbac.driver', 'config'),
                'rbac_enabled' => (bool) $this->app->config('rbac.enabled', false),
            ),
            'roles' => is_array($roles) ? $roles : array(),
            'permissions' => is_array($perms) ? $perms : array(),
            'role_permissions' => is_array($rolePerms) ? $rolePerms : array(),
            'identity_roles' => is_array($identityRoles) ? $identityRoles : array(),
        ));
    }

    private function hasDb()
    {
        $cfg = $this->app->config('db', array());
        if (!is_array($cfg)) {
            return false;
        }
        $dsn = isset($cfg['dsn']) ? trim((string) $cfg['dsn']) : '';
        return $dsn !== '';
    }

    private function roleIdsByNames(array $names)
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names), function ($v) {
            return $v !== '';
        })));
        if (empty($names)) {
            return array();
        }

        $params = array();
        $holders = array();
        $i = 0;
        foreach ($names as $n) {
            $k = 'n' . $i;
            $params[$k] = $n;
            $holders[] = ':' . $k;
            $i++;
        }

        $sql = 'SELECT id,name FROM rbac_roles WHERE name IN (' . implode(',', $holders) . ')';
        $rows = $this->app->db()->fetchAll($sql, $params);
        $out = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['name']) && isset($row['id'])) {
                    $out[(string) $row['name']] = (int) $row['id'];
                }
            }
        }
        return $out;
    }

    private function permissionIdsByNames(array $names)
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names), function ($v) {
            return $v !== '';
        })));
        if (empty($names)) {
            return array();
        }

        $params = array();
        $holders = array();
        $i = 0;
        foreach ($names as $n) {
            $k = 'n' . $i;
            $params[$k] = $n;
            $holders[] = ':' . $k;
            $i++;
        }

        $sql = 'SELECT id,name FROM rbac_permissions WHERE name IN (' . implode(',', $holders) . ')';
        $rows = $this->app->db()->fetchAll($sql, $params);
        $out = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['name']) && isset($row['id'])) {
                    $out[(string) $row['name']] = (int) $row['id'];
                }
            }
        }
        return $out;
    }

    private function generateToken($bytes)
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
}
