<?php

/**
 * 文件作用：全局异常处理器（记录日志 + 统一输出 JSON 错误响应）。
 */

namespace Framework\Exception;

use Framework\Foundation\Application;
use Framework\Http\Cors;
use Framework\Http\Request;
use Framework\Support\Api;
use Framework\Support\ErrorCodes;
use Framework\Support\Logger;

class Handler
{
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function register()
    {
        set_exception_handler(array($this, 'handleUncaught'));
        set_error_handler(array($this, 'handleError'));
        register_shutdown_function(array($this, 'handleShutdown'));
        return $this;
    }

    public function handleUncaught($e)
    {
        $req = Request::capture();
        $resp = $this->render($e, $req);
        $corsCfg = $this->app->config('cors', array());
        $corsHeaders = Cors::makeHeaders(is_array($corsCfg) ? $corsCfg : array(), $req);
        $resp->headers($corsHeaders)->send();
    }

    public function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleShutdown()
    {
        $err = error_get_last();
        if (!is_array($err)) {
            return;
        }

        $type = isset($err['type']) ? (int) $err['type'] : 0;
        $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (!in_array($type, $fatalTypes, true)) {
            return;
        }

        $msg = isset($err['message']) ? (string) $err['message'] : 'fatal_error';
        $file = isset($err['file']) ? (string) $err['file'] : '';
        $line = isset($err['line']) ? (int) $err['line'] : 0;

        $e = new \ErrorException($msg, 0, $type, $file, $line);
        $this->handleUncaught($e);
    }

    public function render($e, Request $request)
    {
        $this->writeLog($e, $request);

        if ($e instanceof ValidationException) {
            return Api::fail('', ErrorCodes::VALIDATION_FAILED, 422, null, array('errors' => $e->errors()));
        }

        if ($e instanceof BadRequestException || $e instanceof \InvalidArgumentException) {
            return Api::fail('', ErrorCodes::BAD_REQUEST, 400, null);
        }

        $debug = $this->app->config('app.debug', false);
        if ($debug) {
            return Api::fail($e->getMessage(), ErrorCodes::SERVER_ERROR, 500, array(
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ));
        }

        return Api::fail('', ErrorCodes::SERVER_ERROR, 500, null);
    }

    private function writeLog($e, Request $request)
    {
        $file = $this->app->basePath() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.jsonl';
        $logger = new Logger($file);
        $rid = method_exists($request, 'attribute') ? (string) $request->attribute('request_id', '') : '';
        $logger->error('exception', array(
            'request_id' => $rid,
            'method' => $request->method(),
            'path' => $request->path(),
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ));
    }
}
