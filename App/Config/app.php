<?php

/**
 * 文件作用：应用基础配置（调试、时区、CORS、数据库连接等）。
 */

return array(
    'app' => array(
        'debug' => false,
        'timezone' => 'Asia/Shanghai',
        'trusted_proxies' => array(),
    ),
    'cors' => array(
        'enabled' => true,
        'allow_origin' => '*',
        'allow_methods' => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
        'allow_headers' => 'Content-Type, Authorization, X-Requested-With',
        'max_age' => 86400,
        'allow_credentials' => false,
    ),
    'db' => array(
        'dsn' => '',
        'username' => '',
        'password' => '',
        'options' => array(),
    ),
    'security' => array(
        'hsts' => false,
        'csp' => "default-src 'self'; frame-ancestors 'self'; base-uri 'self'",
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
    ),
    'signature' => array(
        'enabled' => false,
        'secret' => '',
        'max_skew' => 300,
        'except' => array('/health', '/ping'),
    ),
    'cache' => array(
        'dir' => 'storage/cache',
    ),
    'rate_limit' => array(
        'enabled' => false,
        'max' => 120,
        'window' => 60,
        'by_path' => true,
    ),
    'body_limit' => array(
        'enabled' => true,
        'max_bytes' => 2097152,
    ),
    'auth' => array(
        'enabled' => false,
        'tokens' => array(),
        'token_file' => '',
        'revoked' => array(),
        'revoked_file' => '',
        'except' => array('/health', '/ping'),
    ),
    'rbac' => array(
        'enabled' => false,
        'roles' => array(
            'admin' => array('*'),
        ),
    ),
    'middleware_alias' => array(
        'access_log' => 'Framework\\Http\\AccessLogMiddleware',
        'security_headers' => 'Framework\\Http\\SecurityHeadersMiddleware',
        'body_limit' => 'Framework\\Http\\BodySizeLimitMiddleware',
        'signature' => 'Framework\\Http\\SignatureMiddleware',
        'rate_limit' => 'Framework\\Http\\RateLimitMiddleware',
        'auth' => 'Framework\\Http\\AuthMiddleware',
        'auth_required' => 'Framework\\Http\\AuthRequiredMiddleware',
        'permission' => 'Framework\\Http\\PermissionMiddleware',
    ),
    'middleware' => array(
        'Framework\\Http\\AccessLogMiddleware',
        'Framework\\Http\\SecurityHeadersMiddleware',
        'Framework\\Http\\BodySizeLimitMiddleware',
        'Framework\\Http\\SignatureMiddleware',
        'Framework\\Http\\RateLimitMiddleware',
        'Framework\\Http\\AuthMiddleware',
    ),
);
