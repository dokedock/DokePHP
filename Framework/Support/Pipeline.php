<?php

/**
 * 文件作用：中间件管线执行器，将多个 middleware 串联成单一执行链。
 */

namespace Framework\Support;

class Pipeline
{
    private $app;
    private $middlewares;

    public function __construct($app, array $middlewares)
    {
        $this->app = $app;
        $this->middlewares = $middlewares;
    }

    public function then($destination)
    {
        $next = $destination;

        for ($i = count($this->middlewares) - 1; $i >= 0; $i--) {
            $mw = $this->middlewares[$i];
            $app = $this->app;

            $next = function ($request) use ($mw, $next, $app) {
                if (is_string($mw)) {
                    $mw = ltrim($mw, '\\');
                    if (strpos($mw, '\\') === false && is_object($app) && method_exists($app, 'config')) {
                        $aliases = $app->config('middleware_alias', array());
                        if (is_array($aliases) && array_key_exists($mw, $aliases) && is_string($aliases[$mw]) && $aliases[$mw] !== '') {
                            $mw = (string) $aliases[$mw];
                        }
                    }
                    if (is_object($app) && method_exists($app, 'make')) {
                        $mw = $app->make($mw);
                    } else {
                        $mw = new $mw($app);
                    }
                }

                if (is_object($mw) && method_exists($mw, 'handle')) {
                    return $mw->handle($request, $next);
                }

                if (is_callable($mw)) {
                    return call_user_func($mw, $request, $next, $app);
                }

                return call_user_func($next, $request);
            };
        }

        return $next;
    }
}
