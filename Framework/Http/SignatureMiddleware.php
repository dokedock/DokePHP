<?php

/**
 * 文件作用：签名验签中间件（X-Signature/X-Timestamp/X-Nonce），支持时间窗与 nonce 防重放。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;
use Framework\Support\FileCache;

class SignatureMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $cfg = $this->app->config('signature', array());
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return call_user_func($next, $request);
        }

        $path = $request->path();
        $except = isset($cfg['except']) && is_array($cfg['except']) ? $cfg['except'] : array();
        if ($this->isExcept($path, $except)) {
            return call_user_func($next, $request);
        }

        $secret = isset($cfg['secret']) ? (string) $cfg['secret'] : '';
        if ($secret === '') {
            return Api::fail('signature_secret_missing', ErrorCodes::SERVER_ERROR, 500, null);
        }

        $sig = (string) $request->header('X-Signature', '');
        $ts = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');

        if ($sig === '' || $ts === '' || $nonce === '') {
            return Api::fail('signature_headers_missing', ErrorCodes::BAD_REQUEST, 400, null);
        }

        if (!ctype_digit($ts)) {
            return Api::fail('signature_timestamp_invalid', ErrorCodes::BAD_REQUEST, 400, null);
        }

        $maxSkew = isset($cfg['max_skew']) ? (int) $cfg['max_skew'] : 300;
        if ($maxSkew <= 0) {
            $maxSkew = 300;
        }

        $now = time();
        $tsInt = (int) $ts;
        if (abs($now - $tsInt) > $maxSkew) {
            return Api::fail('signature_timestamp_expired', ErrorCodes::FORBIDDEN, 403, null);
        }

        $cacheDir = (string) $this->app->config('cache.dir', 'storage/cache');
        if (strpos($cacheDir, DIRECTORY_SEPARATOR) !== 0) {
            $cacheDir = $this->app->basePath() . DIRECTORY_SEPARATOR . ltrim($cacheDir, '/\\');
        }
        $cache = new FileCache($cacheDir);

        $nonceKey = 'sig:' . $nonce;
        if (!$cache->add($nonceKey, $tsInt, $maxSkew)) {
            return Api::fail('signature_replay', ErrorCodes::FORBIDDEN, 403, null);
        }

        $base = $request->method() . "\n" . $request->path() . "\n" . $ts . "\n" . $nonce . "\n" . (string) $request->rawBody();
        $expected = hash_hmac('sha256', $base, $secret);

        if (!$this->hashEquals($expected, $sig)) {
            return Api::fail('signature_invalid', ErrorCodes::FORBIDDEN, 403, null);
        }

        return call_user_func($next, $request);
    }

    private function hashEquals($a, $b)
    {
        $a = (string) $a;
        $b = (string) $b;
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $res = 0;
        $len = strlen($a);
        for ($i = 0; $i < $len; $i++) {
            $res |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $res === 0;
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
