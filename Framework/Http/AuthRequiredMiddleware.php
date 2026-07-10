<?php

/**
 * 文件作用：路由级强制鉴权中间件（Bearer Token），无视 auth.enabled，用于对特定路由强制启用鉴权。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;

class AuthRequiredMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $cfg = $this->app->config('auth', array());
        if (!is_array($cfg)) {
            $cfg = array();
        }

        $auth = method_exists($request, 'authorization') ? (string) $request->authorization('') : (string) $request->header('Authorization', '');
        $token = $this->parseBearer($auth);

        $svc = $this->app->make('App\\Services\\AuthService');
        $payload = $svc->authenticateBearer($token);
        if (!$payload) {
            return Api::fail('', ErrorCodes::UNAUTHORIZED, 401, null);
        }

        if (method_exists($request, 'setAttribute')) {
            $request->setAttribute('auth', $payload);
        }

        return call_user_func($next, $request);
    }

    private function parseBearer($auth)
    {
        $auth = trim((string) $auth);
        if ($auth === '') {
            return '';
        }

        if (stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }

        return '';
    }
}
