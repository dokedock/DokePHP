<?php

/**
 * 文件作用：统一 JSON 返回格式封装（code/message/data）。
 */

namespace Framework\Support;

use Framework\Http\Response;

class Api
{
    public static function ok($data, $message = 'ok', $code = 0, $httpStatus = 200, array $extra = array())
    {
        if ($message === null || $message === '') {
            $message = ErrorCodes::message($code);
        }

        $payload = array(
            'code' => (int) $code,
            'message' => (string) $message,
            'data' => $data,
        );

        foreach ($extra as $k => $v) {
            if (!array_key_exists($k, $payload)) {
                $payload[$k] = $v;
            }
        }

        return Response::json($payload, $httpStatus);
    }

    public static function fail($message = 'error', $code = 500, $httpStatus = 500, $data = null, array $extra = array())
    {
        if ($message === null || $message === '' || $message === 'error') {
            $message = ErrorCodes::message($code);
        }
        return self::ok($data, $message, $code, $httpStatus, $extra);
    }
}
