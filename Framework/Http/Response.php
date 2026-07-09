<?php

/**
 * 文件作用：响应对象封装，支持输出状态码/响应头，并提供 JSON 快捷输出。
 */

namespace Framework\Http;

class Response
{
    private $status = 200;
    private $headers = array();
    private $body = '';

    public function __construct($body = '', $status = 200, array $headers = array())
    {
        $this->body = (string) $body;
        $this->status = (int) $status;
        $this->headers = $headers;
    }

    public static function json($payload, $status = 200, array $headers = array())
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"code":500,"message":"json_encode_failed","data":null}';
            $status = 500;
        }

        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
        }

        return new self($json, $status, $headers);
    }

    public function setStatus($status)
    {
        $this->status = (int) $status;
        return $this;
    }

    public function status()
    {
        return $this->status;
    }

    public function allHeaders()
    {
        return $this->headers;
    }

    public function header($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function headers(array $headers)
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }
        return $this;
    }

    public function write($content)
    {
        $this->body .= (string) $content;
        return $this;
    }

    public function send($withBody = true)
    {
        if (!headers_sent()) {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? (string) $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            $phrases = array(
                422 => 'Unprocessable Entity',
                429 => 'Too Many Requests',
                413 => 'Payload Too Large',
            );

            if (isset($phrases[$this->status])) {
                header($protocol . ' ' . $this->status . ' ' . $phrases[$this->status], true, $this->status);
            } else {
                http_response_code($this->status);
            }
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        if ($withBody) {
            echo $this->body;
        }
    }
}
