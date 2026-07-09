<?php

/**
 * 文件作用：应用核心容器，负责启动流程、CORS 预检处理、路由分发与异常兜底。
 */

namespace Framework\Foundation;

use Framework\Database\Db;
use Framework\Exception\Handler as ExceptionHandler;
use Framework\Http\Cors;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Router;
use Framework\Support\Config;
use Framework\Support\Pipeline;

class Application
{
    private $basePath;
    private $config;
    private $router;
    private $exceptionHandler;
    private $db;

    public function __construct($basePath, array $config)
    {
        $this->basePath = rtrim((string) $basePath, DIRECTORY_SEPARATOR);
        $this->config = $config;
        $this->router = new Router();
        $this->exceptionHandler = new ExceptionHandler($this);
        $this->db = null;
    }

    public function basePath()
    {
        return $this->basePath;
    }

    public function config($key = null, $default = null)
    {
        return Config::get($this->config, $key, $default);
    }

    public function router()
    {
        return $this->router;
    }

    public function db()
    {
        if ($this->db instanceof Db) {
            return $this->db;
        }

        $cfg = $this->config('db', array());
        $this->db = new Db(is_array($cfg) ? $cfg : array());
        return $this->db;
    }

    public function registerErrorHandling()
    {
        $this->exceptionHandler->register();
        return $this;
    }

    public function run()
    {
        $req = Request::capture();
        $corsCfg = $this->config('cors', array());
        $corsHeaders = Cors::makeHeaders(is_array($corsCfg) ? $corsCfg : array(), $req);

        if ($req->isOptions()) {
            Cors::preflight(is_array($corsCfg) ? $corsCfg : array(), $req)->send();
            return;
        }

        if (method_exists($req, 'jsonError') && $req->jsonError() !== '') {
            $e = new \Framework\Exception\BadRequestException($req->jsonError());
            $this->exceptionHandler->render($e, $req)->headers($corsHeaders)->send(!$req->isHead());
            return;
        }

        try {
            $this->ensureStorageDirs();
        } catch (\Exception $e) {
            $this->exceptionHandler->render($e, $req)->headers($corsHeaders)->send(!$req->isHead());
            return;
        } catch (\Throwable $e) {
            $this->exceptionHandler->render($e, $req)->headers($corsHeaders)->send(!$req->isHead());
            return;
        }

        $middlewares = $this->config('middleware', array());
        if (!is_array($middlewares)) {
            $middlewares = array();
        }

        $core = function ($request) {
            try {
                return $this->router()->dispatch($request, $this);
            } catch (\Exception $e) {
                return $this->exceptionHandler->render($e, $request);
            } catch (\Throwable $e) {
                return $this->exceptionHandler->render($e, $request);
            }
        };

        $pipeline = new Pipeline($this, $middlewares);
        $runner = $pipeline->then($core);

        $resp = call_user_func($runner, $req);
        if ($resp instanceof Response) {
            $resp->headers($corsHeaders)->send(!$req->isHead());
            return;
        }

        (new Response((string) $resp))->headers($corsHeaders)->send(!$req->isHead());
    }

    private function ensureStorageDirs()
    {
        $base = $this->basePath . DIRECTORY_SEPARATOR . 'storage';
        $dirs = array(
            $base,
            $base . DIRECTORY_SEPARATOR . 'logs',
            $base . DIRECTORY_SEPARATOR . 'rate_limit',
        );

        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                if (!@mkdir($d, 0755, true) && !is_dir($d)) {
                    throw new \RuntimeException('storage_dir_create_failed');
                }
            }
        }
    }
}
