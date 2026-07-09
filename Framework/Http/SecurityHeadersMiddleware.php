<?php

/**
 * 文件作用：统一补充常见安全响应头，降低浏览器侧可利用面（可作为全局中间件启用）。
 */

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $resp = call_user_func($next, $request);
        if (!$resp instanceof Response) {
            return $resp;
        }

        $headers = array(
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Permissions-Policy' => (string) $this->app->config('security.permissions_policy', 'geolocation=(), microphone=(), camera=()'),
        );

        $csp = (string) $this->app->config('security.csp', '');
        if ($csp !== '') {
            $headers['Content-Security-Policy'] = $csp;
        }

        $hsts = $this->app->config('security.hsts', false);
        if ($hsts) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $resp->headers($headers);
    }
}
