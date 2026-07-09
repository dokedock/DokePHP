<?php

/**
 * 文件作用：文件缓存实现（serialize 存储，带 TTL），用于无 Redis 环境的基本缓存/防重放等。
 */

namespace Framework\Support;

class FileCache implements CacheInterface
{
    private $dir;

    public function __construct($dir)
    {
        $this->dir = rtrim((string) $dir, DIRECTORY_SEPARATOR);
    }

    public function get($key, $default = null)
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return $default;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return $default;
        }

        $data = @unserialize($raw);
        if (!is_array($data) || !isset($data['exp'])) {
            @unlink($file);
            return $default;
        }

        $exp = (int) $data['exp'];
        if ($exp !== 0 && time() > $exp) {
            @unlink($file);
            return $default;
        }

        return array_key_exists('val', $data) ? $data['val'] : $default;
    }

    public function set($key, $value, $ttlSeconds = 0)
    {
        $this->ensureDir();

        $file = $this->filePath($key);
        $exp = 0;
        $ttlSeconds = (int) $ttlSeconds;
        if ($ttlSeconds > 0) {
            $exp = time() + $ttlSeconds;
        }

        $data = array('exp' => $exp, 'val' => $value);
        $raw = serialize($data);
        return @file_put_contents($file, $raw, LOCK_EX) !== false;
    }

    public function add($key, $value, $ttlSeconds = 0)
    {
        $file = $this->filePath($key);
        if (is_file($file)) {
            $v = $this->get($key, null);
            if ($v !== null) {
                return false;
            }
        }
        return $this->set($key, $value, $ttlSeconds);
    }

    public function forget($key)
    {
        $file = $this->filePath($key);
        if (is_file($file)) {
            return @unlink($file);
        }
        return true;
    }

    private function ensureDir()
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    private function filePath($key)
    {
        $name = md5((string) $key);
        return $this->dir . DIRECTORY_SEPARATOR . $name . '.cache';
    }
}
