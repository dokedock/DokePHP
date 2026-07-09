<?php

/**
 * 文件作用：内置诊断/文档接口（仅建议在 debug=true 环境使用）。
 */

namespace App\Controllers;

use Framework\Foundation\BaseController;
use Framework\Http\Request;
use Framework\Support\ErrorCodes;

class DocsController extends BaseController
{
    public function routes(Request $request, array $params = array())
    {
        $debug = (bool) $this->app->config('app.debug', false);
        if (!$debug) {
            return $this->fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        $router = $this->app->router();
        $routes = method_exists($router, 'dumpRoutes') ? $router->dumpRoutes() : array();
        return $this->ok($routes);
    }

    public function requestInfo(Request $request, array $params = array())
    {
        $debug = (bool) $this->app->config('app.debug', false);
        if (!$debug) {
            return $this->fail('', ErrorCodes::NOT_FOUND, 404, null);
        }

        return $this->ok(array(
            'method' => $request->method(),
            'uri' => $request->uri(),
            'path' => $request->path(),
            'server' => array(
                'REQUEST_URI' => $request->server('REQUEST_URI', ''),
                'SCRIPT_NAME' => $request->server('SCRIPT_NAME', ''),
                'PATH_INFO' => $request->server('PATH_INFO', ''),
            ),
        ));
    }
}
