<?php

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;

class RbacRequiredMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next)
    {
        $cfg = $this->app->config('rbac', array());
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return Api::fail('rbac_disabled', ErrorCodes::FORBIDDEN, 403, null);
        }

        return call_user_func($next, $request);
    }
}

