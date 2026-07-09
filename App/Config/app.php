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
        'except' => array('/health', '/ping'),
    ),
    'middleware' => array(
        'Framework\\Http\\AccessLogMiddleware',
        'Framework\\Http\\SecurityHeadersMiddleware',
        'Framework\\Http\\BodySizeLimitMiddleware',
        'Framework\\Http\\RateLimitMiddleware',
        'Framework\\Http\\AuthMiddleware',
    ),
);
