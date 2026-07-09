<?php

/**
 * 文件作用：接口鉴权中间件（Bearer Token），支持白名单路径与配置开关。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\Auth;
use Framework\Support\ErrorCodes;

class AuthMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $cfg = $this->app->config('auth', array());
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return call_user_func($next, $request);
        }

        $path = $request->path();
        $except = isset($cfg['except']) && is_array($cfg['except']) ? $cfg['except'] : array();
        if ($this->isExcept($path, $except)) {
            return call_user_func($next, $request);
        }

        $auth = method_exists($request, 'authorization') ? (string) $request->authorization('') : (string) $request->header('Authorization', '');
        $token = $this->parseBearer($auth);

        $tokenMap = Auth::loadTokenMap($this->app, $cfg);
        $payload = Auth::validateToken($token, $tokenMap);
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

    private function isExcept($path, array $except)
    {
        foreach ($except as $p) {
            $p = (string) $p;
            if ($p === '') {
                continue;
            }
            if ($p === $path) {
                return true;
            }
            if (substr($p, -1) === '*') {
                $prefix = rtrim(substr($p, 0, -1), '/');
                if ($prefix === '') {
                    continue;
                }
                if (strpos($path, $prefix) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
