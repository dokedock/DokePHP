<?php

/**
 * 文件作用：简单限流中间件（按 IP+Path 计数，窗口内超限返回 429）。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;

class RateLimitMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $cfg = $this->app->config('rate_limit', array());
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return call_user_func($next, $request);
        }

        $max = isset($cfg['max']) ? (int) $cfg['max'] : 120;
        $window = isset($cfg['window']) ? (int) $cfg['window'] : 60;
        $keyByPath = isset($cfg['by_path']) ? (bool) $cfg['by_path'] : true;

        if ($max <= 0 || $window <= 0) {
            return call_user_func($next, $request);
        }

        $ip = method_exists($request, 'ip') ? (string) $request->ip((array) $this->app->config('app.trusted_proxies', array())) : '';
        if ($ip === '') {
            $ip = 'unknown';
        }

        $path = $keyByPath ? $request->path() : '*';
        $now = time();
        $bucket = (int) floor($now / $window);

        $dir = $this->app->basePath() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate_limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            if (!is_dir($dir)) {
                return call_user_func($next, $request);
            }
        }

        if (mt_rand(1, 100) === 1) {
            $ttl = max(1, $window) * 10;
            $this->gc($dir, $ttl);
        }

        $file = $dir . DIRECTORY_SEPARATOR . md5($ip . '|' . $path) . '.json';
        $data = array('bucket' => $bucket, 'count' => 0);

        $fp = @fopen($file, 'c+');
        if ($fp) {
            if (function_exists('flock')) {
                @flock($fp, LOCK_EX);
            }

            $raw = stream_get_contents($fp);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['bucket']) && isset($decoded['count'])) {
                    $data = $decoded;
                }
            }

            if ((int) $data['bucket'] !== $bucket) {
                $data['bucket'] = $bucket;
                $data['count'] = 0;
            }

            $data['count'] = (int) $data['count'] + 1;

            ftruncate($fp, 0);
            rewind($fp);
            $encoded = json_encode($data);
            if ($encoded === false) {
                $encoded = '{}';
            }
            fwrite($fp, $encoded);
            fflush($fp);

            if (function_exists('flock')) {
                @flock($fp, LOCK_UN);
            }
            fclose($fp);
        } else {
            return call_user_func($next, $request);
        }

        if ((int) $data['count'] > $max) {
            $reset = ($bucket + 1) * $window;
            $retryAfter = $reset - $now;
            $headers = array(
                'Retry-After' => (string) $retryAfter,
            );
            return Api::fail('', ErrorCodes::TOO_MANY_REQUESTS, 429, null)->headers($headers);
        }

        return call_user_func($next, $request);
    }

    private function gc($dir, $ttl)
    {
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
        if (!is_array($files)) {
            return;
        }

        $now = time();
        foreach ($files as $f) {
            if (!is_file($f)) {
                continue;
            }
            $mt = @filemtime($f);
            if ($mt && ($now - $mt) > $ttl) {
                @unlink($f);
            }
        }
    }
}
