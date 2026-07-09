<?php

/**
 * 文件作用：访问日志中间件（记录 request_id、耗时、状态码、IP、方法与路径）。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Logger;

class AccessLogMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $start = microtime(true);

        $rid = (string) $request->header('X-Request-Id', '');
        if ($rid === '') {
            $rid = $this->makeRequestId();
        }

        if (method_exists($request, 'setAttribute')) {
            $request->setAttribute('request_id', $rid);
        }

        $resp = call_user_func($next, $request);

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $status = $resp instanceof Response ? $resp->status() : 200;
        $ip = method_exists($request, 'ip') ? (string) $request->ip((array) $this->app->config('app.trusted_proxies', array())) : '';

        $this->write($rid, $ip, $request->method(), $request->path(), $status, $durationMs);

        if ($resp instanceof Response) {
            $resp->header('X-Request-Id', $rid);
        }

        return $resp;
    }

    private function makeRequestId()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $b = openssl_random_pseudo_bytes(16);
            if ($b !== false) {
                return bin2hex($b);
            }
        }

        return md5(uniqid('', true));
    }

    private function write($rid, $ip, $method, $path, $status, $durationMs)
    {
        $file = $this->app->basePath() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'access.jsonl';
        $logger = new Logger($file);
        $logger->info('access', array(
            'request_id' => (string) $rid,
            'ip' => (string) $ip,
            'method' => (string) $method,
            'path' => (string) $path,
            'status' => (int) $status,
            'duration_ms' => (int) $durationMs,
        ));
    }
}
