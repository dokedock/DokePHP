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

        $tokens = isset($cfg['tokens']) && is_array($cfg['tokens']) ? $cfg['tokens'] : array();
        $tokenFile = isset($cfg['token_file']) ? (string) $cfg['token_file'] : '';
        if ($tokenFile !== '') {
            if (strpos($tokenFile, DIRECTORY_SEPARATOR) !== 0) {
                $tokenFile = $this->app->basePath() . DIRECTORY_SEPARATOR . ltrim($tokenFile, '/\\');
            }

            if (is_file($tokenFile) && is_readable($tokenFile)) {
                $lines = file($tokenFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim((string) $line);
                        if ($line === '' || strpos($line, '#') === 0) {
                            continue;
                        }
                        $tokens[] = $line;
                    }
                    $tokens = array_values(array_unique($tokens));
                }
            }
        }

        $ok = $token !== '' && in_array($token, $tokens, true);
        if (!$ok) {
            return Api::fail('', ErrorCodes::UNAUTHORIZED, 401, null);
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
