<?php

/**
 * 文件作用：控制器基类，统一继承入口与 JSON 成功/失败返回。
 */

namespace Framework\Foundation;

use Framework\Exception\ValidationException;
use Framework\Support\Api;
use Framework\Support\Validator;

abstract class BaseController
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function ok($data = null, $message = 'ok', array $extra = array())
    {
        return Api::ok($data, $message, 0, 200, $extra);
    }

    protected function fail($message = 'error', $code = 500, $httpStatus = 500, $data = null, array $extra = array())
    {
        return Api::fail($message, $code, $httpStatus, $data, $extra);
    }

    protected function validate(array $data, array $rules, array $messages = array())
    {
        $res = Validator::validate($data, $rules, $messages);
        if (isset($res['ok']) && $res['ok']) {
            return $data;
        }

        $errors = isset($res['errors']) && is_array($res['errors']) ? $res['errors'] : array();
        throw new ValidationException($errors);
    }
}
