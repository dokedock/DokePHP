<?php

namespace Framework\Http;

use Framework\Foundation\Application;
use Framework\Foundation\MiddlewareInterface;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;

class FeatureRequiredMiddleware implements MiddlewareInterface
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($request, $next, $feature = '')
    {
        $feature = strtolower(trim((string) $feature));
        if ($feature === '') {
            return call_user_func($next, $request);
        }

        if ($feature === 'rbac_db') {
            $cfg = $this->app->config('rbac', array());
            $driver = is_array($cfg) && isset($cfg['driver']) ? strtolower(trim((string) $cfg['driver'])) : 'config';
            if ($driver !== 'db' && $driver !== 'hybrid') {
                return Api::fail('rbac_db_required', ErrorCodes::CONFLICT, 409, null);
            }
            return call_user_func($next, $request);
        }

        if ($feature === 'auth_db') {
            $cfg = $this->app->config('auth', array());
            $driver = is_array($cfg) && isset($cfg['driver']) ? strtolower(trim((string) $cfg['driver'])) : 'file';
            if ($driver !== 'db' && $driver !== 'hybrid') {
                return Api::fail('auth_db_required', ErrorCodes::CONFLICT, 409, null);
            }
            return call_user_func($next, $request);
        }

        if ($feature === 'audit_db') {
            $audit = $this->app->config('audit', array());
            if (!is_array($audit) || empty($audit['enabled'])) {
                return Api::fail('audit_disabled', ErrorCodes::CONFLICT, 409, null);
            }
            $dbCfg = $this->app->config('db', array());
            $dsn = is_array($dbCfg) && isset($dbCfg['dsn']) ? trim((string) $dbCfg['dsn']) : '';
            if ($dsn === '') {
                return Api::fail('db_not_configured', ErrorCodes::CONFLICT, 409, null);
            }
            return call_user_func($next, $request);
        }

        return Api::fail('feature_not_supported', ErrorCodes::BAD_REQUEST, 400, null);
    }
}

