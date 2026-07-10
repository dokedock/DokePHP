<?php

/**
 * 文件作用：示例控制器（演示 JSON 输出与路由参数接收）。
 */

namespace App\Controllers;

use Framework\Foundation\BaseController;
use Framework\Http\Request;

class HomeController extends BaseController
{
    public function index(Request $request, array $params = array())
    {
        return $this->ok(array(
            'name' => 'pure-php-framework',
            'time' => date('Y-m-d H:i:s'),
        ));
    }

    public function ping(Request $request, array $params = array())
    {
        return $this->ok('pong');
    }

    public function hello(Request $request, array $params = array())
    {
        $name = isset($params['name']) ? (string) $params['name'] : 'World';
        return $this->ok(array('hello' => $name));
    }

    public function echoData(Request $request, array $params = array())
    {
        $data = $request->all();
        $this->validate($data, array(
            'name' => 'required|string|max:20',
            'age' => 'int|min:0|max:150',
        ));

        return $this->ok(array(
            'name' => (string) $request->input('name'),
            'age' => $request->input('age', null),
        ));
    }

    public function me(Request $request, array $params = array())
    {
        $auth = method_exists($request, 'attribute') ? $request->attribute('auth', null) : null;
        return $this->ok(array(
            'ok' => true,
            'time' => date('Y-m-d H:i:s'),
            'auth' => $auth,
        ));
    }

    public function adminStats(Request $request, array $params = array())
    {
        return $this->ok(array(
            'ok' => true,
            'name' => 'admin',
            'time' => date('Y-m-d H:i:s'),
        ));
    }

    public function adminRbac(Request $request, array $params = array())
    {
        $auth = method_exists($request, 'attribute') ? $request->attribute('auth', null) : null;
        $roles = is_array($auth) && isset($auth['roles']) && is_array($auth['roles']) ? $auth['roles'] : array();

        $cfg = $this->app->config('rbac', array());
        if (!is_array($cfg)) {
            $cfg = array();
        }

        $driver = isset($cfg['driver']) ? strtolower(trim((string) $cfg['driver'])) : 'config';
        if ($driver === '') {
            $driver = 'config';
        }

        $allowConfig = array();
        if ($driver === 'config' || $driver === 'hybrid') {
            $map = isset($cfg['roles']) && is_array($cfg['roles']) ? $cfg['roles'] : array();
            foreach ($roles as $r) {
                $r = (string) $r;
                if ($r === '' || !array_key_exists($r, $map) || !is_array($map[$r])) {
                    continue;
                }
                foreach ($map[$r] as $p) {
                    $p = trim((string) $p);
                    if ($p !== '') {
                        $allowConfig[$p] = true;
                    }
                }
            }
        }

        $allowDb = array();
        if ($driver === 'db' || $driver === 'hybrid') {
            $svc = $this->app->make('App\\Services\\RbacService');
            $tmp = $svc->permissionsForRoles($roles);
            if (is_array($tmp)) {
                $allowDb = $tmp;
            }
        }

        $merged = array_merge(array_keys($allowConfig), array_keys($allowDb));
        $merged = array_values(array_unique(array_filter(array_map('trim', $merged), function ($v) {
            return $v !== '';
        })));
        sort($merged);

        return $this->ok(array(
            'auth' => $auth,
            'rbac' => array(
                'enabled' => !empty($cfg['enabled']),
                'driver' => $driver,
                'roles' => $roles,
                'permissions_config' => array_keys($allowConfig),
                'permissions_db' => array_keys($allowDb),
                'permissions_merged' => $merged,
            ),
        ));
    }
}
