<?php

/**
 * 文件作用：示例控制器（演示 JSON 输出与路由参数接收）。
 */

namespace App\Controllers;

use Framework\Foundation\BaseController;
use Framework\Http\Request;

class HomeController extends BaseController
{
    public function index(Request $request, array $params = array())
    {
        return $this->ok(array(
            'name' => 'pure-php-framework',
            'time' => date('Y-m-d H:i:s'),
        ));
    }

    public function ping(Request $request, array $params = array())
    {
        return $this->ok('pong');
    }

    public function hello(Request $request, array $params = array())
    {
        $name = isset($params['name']) ? (string) $params['name'] : 'World';
        return $this->ok(array('hello' => $name));
    }

    public function echoData(Request $request, array $params = array())
    {
        $data = $request->all();
        $this->validate($data, array(
            'name' => 'required|string|max:20',
            'age' => 'int|min:0|max:150',
        ));

        return $this->ok(array(
            'name' => (string) $request->input('name'),
            'age' => $request->input('age', null),
        ));
    }

    public function me(Request $request, array $params = array())
    {
        $auth = method_exists($request, 'attribute') ? $request->attribute('auth', null) : null;
        return $this->ok(array(
            'ok' => true,
            'time' => date('Y-m-d H:i:s'),
            'auth' => $auth,
        ));
    }

    public function adminStats(Request $request, array $params = array())
    {
        return $this->ok(array(
            'ok' => true,
            'name' => 'admin',
            'time' => date('Y-m-d H:i:s'),
        ));
    }
}
