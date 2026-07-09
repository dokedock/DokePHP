<?php

/**
 * 文件作用：参数校验异常，携带字段级错误，供全局异常处理器统一输出。
 */

namespace Framework\Exception;

class ValidationException extends \Exception
{
    private $errors;

    public function __construct(array $errors, $message = 'validation_failed', $code = 42200)
    {
        parent::__construct((string) $message, (int) $code);
        $this->errors = $errors;
    }

    public function errors()
    {
        return $this->errors;
    }
}

