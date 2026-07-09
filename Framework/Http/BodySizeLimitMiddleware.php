<?php

/**
 * 文件作用：请求体大小限制中间件，避免超大请求占用内存/带宽导致服务不稳定。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;

class BodySizeLimitMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $cfg = $this->app->config('body_limit', array());
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return call_user_func($next, $request);
        }

        $max = isset($cfg['max_bytes']) ? (int) $cfg['max_bytes'] : 0;
        if ($max <= 0) {
            return call_user_func($next, $request);
        }

        $len = (int) $request->server('CONTENT_LENGTH', 0);
        if ($len > 0 && $len > $max) {
            return Api::fail('', ErrorCodes::PAYLOAD_TOO_LARGE, 413, null);
        }

        $raw = (string) $request->rawBody();
        if ($raw !== '' && strlen($raw) > $max) {
            return Api::fail('', ErrorCodes::PAYLOAD_TOO_LARGE, 413, null);
        }

        return call_user_func($next, $request);
    }
}
