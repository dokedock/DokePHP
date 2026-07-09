<?php

/**
 * 文件作用：RBAC 权限中间件，配合路由级 middleware 使用（例如 permission:admin 或 permission:order.read）。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;

class PermissionMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next, $perm = '')
    {
        $cfg = $this->app->config('rbac', array());
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return call_user_func($next, $request);
        }

        $auth = method_exists($request, 'attribute') ? $request->attribute('auth', null) : null;
        if (!is_array($auth)) {
            return Api::fail('', ErrorCodes::UNAUTHORIZED, 401, null);
        }

        $perm = trim((string) $perm);
        if ($perm === '') {
            return call_user_func($next, $request);
        }

        $need = array_map('trim', explode(',', $perm));
        $need = array_values(array_filter($need, function ($v) {
            return $v !== '';
        }));
        if (empty($need)) {
            return call_user_func($next, $request);
        }

        $roles = isset($auth['roles']) && is_array($auth['roles']) ? $auth['roles'] : array();
        $allowed = $this->permissionsForRoles($roles, $cfg);

        foreach ($need as $p) {
            if ($this->isAllowed($p, $allowed)) {
                continue;
            }
            if (!array_key_exists($p, $allowed)) {
                return Api::fail('', ErrorCodes::FORBIDDEN, 403, null);
            }
        }

        return call_user_func($next, $request);
    }

    private function isAllowed($perm, array $allowed)
    {
        $perm = (string) $perm;
        if ($perm === '') {
            return true;
        }
        if (array_key_exists('*', $allowed)) {
            return true;
        }
        if (array_key_exists($perm, $allowed)) {
            return true;
        }
        foreach ($allowed as $p => $v) {
            $p = (string) $p;
            if ($p === '' || $p === '*' || substr($p, -1) !== '*') {
                continue;
            }
            $prefix = substr($p, 0, -1);
            if ($prefix !== '' && strpos($perm, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    private function permissionsForRoles(array $roles, array $cfg)
    {
        $map = isset($cfg['roles']) && is_array($cfg['roles']) ? $cfg['roles'] : array();
        $out = array();

        foreach ($roles as $r) {
            $r = (string) $r;
            if ($r === '') {
                continue;
            }
            if (!array_key_exists($r, $map)) {
                continue;
            }
            $perms = $map[$r];
            if (!is_array($perms)) {
                continue;
            }
            foreach ($perms as $p) {
                $p = trim((string) $p);
                if ($p !== '') {
                    $out[$p] = true;
                }
            }
        }

        return $out;
    }
}
