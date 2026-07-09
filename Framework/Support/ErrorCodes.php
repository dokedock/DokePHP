<?php

/**
 * 文件作用：统一错误码与默认消息映射，保证线上返回结构稳定可控。
 */

namespace Framework\Support;

class ErrorCodes
{
    const OK = 0;

    const BAD_REQUEST = 40000;
    const UNAUTHORIZED = 40100;
    const FORBIDDEN = 40300;
    const NOT_FOUND = 40400;
    const METHOD_NOT_ALLOWED = 40500;
    const VALIDATION_FAILED = 42200;
    const TOO_MANY_REQUESTS = 42900;
    const PAYLOAD_TOO_LARGE = 41300;

    const SERVER_ERROR = 50000;

    public static function message($code)
    {
        $map = array(
            self::OK => 'ok',
            self::BAD_REQUEST => 'bad_request',
            self::UNAUTHORIZED => 'unauthorized',
            self::FORBIDDEN => 'forbidden',
            self::NOT_FOUND => 'not_found',
            self::METHOD_NOT_ALLOWED => 'method_not_allowed',
            self::VALIDATION_FAILED => 'validation_failed',
            self::TOO_MANY_REQUESTS => 'too_many_requests',
            self::PAYLOAD_TOO_LARGE => 'payload_too_large',
            self::SERVER_ERROR => 'server_error',
        );

        $code = (int) $code;
        return array_key_exists($code, $map) ? $map[$code] : 'error';
    }
}

