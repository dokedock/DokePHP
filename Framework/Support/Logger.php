<?php

/**
 * 文件作用：极简日志器（JSONL），用于访问日志/异常日志等可观测性基础。
 */

namespace Framework\Support;

class Logger
{
    private $file;

    public function __construct($file)
    {
        $this->file = (string) $file;
    }

    public function info($message, array $context = array())
    {
        return $this->write('info', $message, $context);
    }

    public function error($message, array $context = array())
    {
        return $this->write('error', $message, $context);
    }

    public function write($level, $message, array $context = array())
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $row = array(
            'ts' => date('c'),
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        );

        $line = json_encode($row, JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $line = '{"ts":"' . date('c') . '","level":"error","message":"json_encode_failed","context":{}}';
        }

        return @file_put_contents($this->file, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    }
}
