<?php

/**
 * 文件作用：路由表与分发器，负责匹配路径并调用控制器方法（支持 /path/{param}）。
 */

namespace Framework\Routing;

use Framework\Foundation\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Support\Pipeline;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;

class Router
{
    private $routes = array();
    private $groupStack = array();

    public function get($path, $handler, array $options = array())
    {
        return $this->add('GET', $path, $handler, $options);
    }

    public function post($path, $handler, array $options = array())
    {
        return $this->add('POST', $path, $handler, $options);
    }

    public function any($path, $handler, array $options = array())
    {
        return $this->add('ANY', $path, $handler, $options);
    }

    public function group(array $attrs, $callback)
    {
        $parent = $this->currentGroup();

        $prefix = isset($attrs['prefix']) ? (string) $attrs['prefix'] : '';
        $prefix = $this->normalizeGroupPrefix($prefix);
        $prefix = $this->joinPaths($parent['prefix'], $prefix);

        $mws = array();
        if (isset($parent['middleware']) && is_array($parent['middleware'])) {
            $mws = $parent['middleware'];
        }
        if (isset($attrs['middleware'])) {
            $m = $attrs['middleware'];
            if (is_string($m) && $m !== '') {
                $mws[] = $m;
            } elseif (is_array($m)) {
                foreach ($m as $one) {
                    if (is_string($one) && $one !== '') {
                        $mws[] = $one;
                    }
                }
            }
        }

        $this->groupStack[] = array(
            'prefix' => $prefix,
            'middleware' => array_values(array_unique($mws)),
        );

        call_user_func($callback, $this);
        array_pop($this->groupStack);

        return $this;
    }

    public function add($method, $path, $handler, array $options = array())
    {
        $method = strtoupper((string) $method);
        $path = '/' . ltrim((string) $path, '/');

        $group = $this->currentGroup();
        $path = $this->joinPaths($group['prefix'], $path);

        $compiled = $this->compile($path);

        $mws = array();
        if (isset($group['middleware']) && is_array($group['middleware'])) {
            $mws = $group['middleware'];
        }
        if (isset($options['middleware'])) {
            $m = $options['middleware'];
            if (is_string($m) && $m !== '') {
                $mws[] = $m;
            } elseif (is_array($m)) {
                foreach ($m as $one) {
                    if (is_string($one) && $one !== '') {
                        $mws[] = $one;
                    }
                }
            }
        }

        $this->routes[] = array(
            'method' => $method,
            'path' => $path,
            'regex' => $compiled['regex'],
            'params' => $compiled['params'],
            'handler' => $handler,
            'middleware' => array_values(array_unique($mws)),
        );

        return $this;
    }

    public function dispatch(Request $request, Application $app)
    {
        $path = $request->path();
        $method = $request->method();
        $matchMethod = $method === 'HEAD' ? 'GET' : $method;
        $allowed = array();

        foreach ($this->routes as $r) {
            $m = array();
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }

            if ($r['method'] !== 'ANY' && $r['method'] !== $matchMethod) {
                $allowed[] = $r['method'];
                continue;
            }

            $params = array();
            foreach ($r['params'] as $name) {
                if (isset($m[$name])) {
                    $params[$name] = $m[$name];
                }
            }

            $out = $this->runRoute($r, $request, $params, $app);
            if ($out instanceof Response) {
                return $out;
            }
            return new Response((string) $out, 200, array('Content-Type' => 'text/plain; charset=utf-8'));
        }

        if (!empty($allowed)) {
            if ($matchMethod === 'GET' || in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }
            $allowed[] = 'OPTIONS';
            $allowed = array_values(array_unique($allowed));
            sort($allowed);
            $headers = array(
                'Allow' => implode(', ', $allowed),
            );
            return Api::fail('', ErrorCodes::METHOD_NOT_ALLOWED, 405, null)->headers($headers);
        }

        return Api::fail('', ErrorCodes::NOT_FOUND, 404, null);
    }

    private function runRoute(array $route, Request $request, array $params, Application $app)
    {
        $mws = isset($route['middleware']) && is_array($route['middleware']) ? $route['middleware'] : array();
        if (empty($mws)) {
            return $this->runHandler($route['handler'], $request, $params, $app);
        }

        $core = function ($req) use ($route, $params, $app) {
            return $this->runHandler($route['handler'], $req, $params, $app);
        };

        $pipeline = new Pipeline($app, $mws);
        $runner = $pipeline->then($core);
        return call_user_func($runner, $request);
    }

    private function compile($path)
    {
        $params = array();
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);

        return array(
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
        );
    }

    private function runHandler($handler, Request $request, array $params, Application $app)
    {
        if (is_callable($handler)) {
            return call_user_func($handler, $request, $params, $app);
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $method) = explode('@', $handler, 2);
            $class = ltrim($class, '\\');
            if (method_exists($app, 'make')) {
                $obj = $app->make($class);
            } else {
                $obj = new $class($app);
            }
            return call_user_func(array($obj, $method), $request, $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            $target = $handler[0];
            $method = $handler[1];
            if (is_string($target)) {
                $target = ltrim($target, '\\');
                if (method_exists($app, 'make')) {
                    $target = $app->make($target);
                } else {
                    $target = new $target($app);
                }
            }
            return call_user_func(array($target, $method), $request, $params);
        }

        return Api::fail('invalid_handler', ErrorCodes::SERVER_ERROR, 500, null);
    }

    private function currentGroup()
    {
        if (empty($this->groupStack)) {
            return array('prefix' => '', 'middleware' => array());
        }
        return $this->groupStack[count($this->groupStack) - 1];
    }

    private function normalizeGroupPrefix($prefix)
    {
        $prefix = trim((string) $prefix);
        if ($prefix === '' || $prefix === '/') {
            return '';
        }

        $prefix = '/' . trim($prefix, '/');
        return $prefix;
    }

    private function joinPaths($prefix, $path)
    {
        $prefix = (string) $prefix;
        $path = (string) $path;

        if ($prefix === '' || $prefix === '/') {
            return $path;
        }

        if ($path === '' || $path === '/') {
            return $prefix;
        }

        $joined = rtrim($prefix, '/') . '/' . ltrim($path, '/');
        $joined = preg_replace('#/+#', '/', $joined);
        if ($joined === '') {
            return '/';
        }
        if ($joined[0] !== '/') {
            $joined = '/' . $joined;
        }
        return $joined;
    }
}
