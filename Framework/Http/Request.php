<?php

/**
 * 文件作用：请求对象封装，统一读取 GET/POST/JSON Body/Header/Server 信息。
 */

namespace Framework\Http;

class Request
{
    private static $capturedRaw = null;
    private static $capturedJson = null;
    private static $capturedJsonError = null;

    private $get;
    private $post;
    private $cookie;
    private $files;
    private $server;
    private $rawBody;
    private $jsonBody;
    private $formBody;
    private $jsonError;

    public function __construct(array $get, array $post, array $cookie, array $files, array $server, $rawBody, $jsonBody, array $formBody, $jsonError)
    {
        $this->get = $get;
        $this->post = $post;
        $this->cookie = $cookie;
        $this->files = $files;
        $this->server = $server;
        $this->rawBody = $rawBody;
        $this->jsonBody = $jsonBody;
        $this->formBody = $formBody;
        $this->jsonError = (string) $jsonError;
    }

    public static function capture()
    {
        $raw = self::$capturedRaw;
        $json = self::$capturedJson;
        $jsonError = self::$capturedJsonError;

        if ($raw === null) {
            $raw = file_get_contents('php://input');
            self::$capturedRaw = $raw;
            $json = null;
            $jsonError = '';

            $ct = strtolower((string) self::serverFromGlobals('HTTP_CONTENT_TYPE', self::serverFromGlobals('CONTENT_TYPE', '')));
            if (strpos($ct, 'application/json') !== false) {
                $rawStr = (string) $raw;
                if ($rawStr === '') {
                    $json = array();
                } else {
                    $decoded = json_decode($rawStr, true);
                    $err = function_exists('json_last_error') ? json_last_error() : 0;
                    if ($err !== JSON_ERROR_NONE) {
                        $json = null;
                        $jsonError = 'invalid_json';
                        if (function_exists('json_last_error_msg')) {
                            $jsonError .= ': ' . json_last_error_msg();
                        }
                    } elseif (!is_array($decoded)) {
                        $json = null;
                        $jsonError = 'invalid_json: root_must_be_object';
                    } else {
                        $json = $decoded;
                    }
                }
            }

            self::$capturedJson = $json;
            self::$capturedJsonError = $jsonError;
        }

        $form = array();
        $method = strtoupper((string) self::serverFromGlobals('REQUEST_METHOD', 'GET'));
        if ($method !== 'POST') {
            $ct2 = strtolower((string) self::serverFromGlobals('HTTP_CONTENT_TYPE', self::serverFromGlobals('CONTENT_TYPE', '')));
            if (strpos($ct2, 'application/x-www-form-urlencoded') !== false && is_string($raw) && $raw !== '') {
                $tmp = array();
                parse_str($raw, $tmp);
                if (is_array($tmp)) {
                    $form = $tmp;
                }
            }
        }

        return new self($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, $raw, $json, $form, $jsonError);
    }

    private static function serverFromGlobals($key, $default)
    {
        return array_key_exists($key, $_SERVER) ? $_SERVER[$key] : $default;
    }

    public function method()
    {
        $m = $this->server('REQUEST_METHOD', 'GET');
        return strtoupper((string) $m);
    }

    public function isOptions()
    {
        return $this->method() === 'OPTIONS';
    }

    public function isHead()
    {
        return $this->method() === 'HEAD';
    }

    public function uri()
    {
        return (string) $this->server('REQUEST_URI', '/');
    }

    public function path()
    {
        $uri = $this->uri();
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $path = rawurldecode($path);
        $path = '/' . ltrim($path, '/');

        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        return $path;
    }

    public function query($key = null, $default = null)
    {
        if ($key === null) {
            return $this->get;
        }
        return array_key_exists($key, $this->get) ? $this->get[$key] : $default;
    }

    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        return array_key_exists($key, $this->post) ? $this->post[$key] : $default;
    }

    public function json($key = null, $default = null)
    {
        if (!is_array($this->jsonBody)) {
            return $key === null ? array() : $default;
        }
        if ($key === null) {
            return $this->jsonBody;
        }
        return array_key_exists($key, $this->jsonBody) ? $this->jsonBody[$key] : $default;
    }

    public function jsonError()
    {
        return (string) $this->jsonError;
    }

    public function input($key, $default = null)
    {
        if (is_array($this->jsonBody) && array_key_exists($key, $this->jsonBody)) {
            return $this->jsonBody[$key];
        }
        if (is_array($this->formBody) && array_key_exists($key, $this->formBody)) {
            return $this->formBody[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->get)) {
            return $this->get[$key];
        }
        return $default;
    }

    public function header($name, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server($key, $default);
    }

    public function authorization($default = '')
    {
        $v = $this->server('HTTP_AUTHORIZATION', null);
        if ($v !== null && $v !== '') {
            return (string) $v;
        }

        $v = $this->server('REDIRECT_HTTP_AUTHORIZATION', null);
        if ($v !== null && $v !== '') {
            return (string) $v;
        }

        $v = $this->server('Authorization', null);
        if ($v !== null && $v !== '') {
            return (string) $v;
        }

        if (function_exists('getallheaders')) {
            $headers = @getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $k => $vv) {
                    if (strtolower((string) $k) === 'authorization') {
                        return (string) $vv;
                    }
                }
            }
        }

        return (string) $default;
    }

    public function ip(array $trustedProxies = array())
    {
        $remote = (string) $this->server('REMOTE_ADDR', '');
        if ($remote === '' || empty($trustedProxies)) {
            return $remote;
        }

        if (!in_array($remote, $trustedProxies, true)) {
            return $remote;
        }

        $xff = (string) $this->header('X-Forwarded-For', '');
        if ($xff === '') {
            return $remote;
        }

        $parts = explode(',', $xff);
        $parts = array_map('trim', $parts);
        $parts = array_values(array_filter($parts, function ($v) {
            return $v !== '';
        }));

        if (empty($parts)) {
            return $remote;
        }

        return (string) $parts[0];
    }

    public function all()
    {
        $data = array();
        if (is_array($this->get)) {
            $data = $this->get;
        }
        if (is_array($this->post)) {
            foreach ($this->post as $k => $v) {
                $data[$k] = $v;
            }
        }
        if (is_array($this->formBody)) {
            foreach ($this->formBody as $k => $v) {
                $data[$k] = $v;
            }
        }
        if (is_array($this->jsonBody)) {
            foreach ($this->jsonBody as $k => $v) {
                $data[$k] = $v;
            }
        }
        return $data;
    }

    public function server($key, $default = null)
    {
        return array_key_exists($key, $this->server) ? $this->server[$key] : $default;
    }

    public function rawBody()
    {
        return $this->rawBody;
    }
}
