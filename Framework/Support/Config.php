<?php

/**
 * 文件作用：配置加载与读取工具（加载 App/Config 下的 php 数组配置）。
 */

namespace Framework\Support;

class Config
{
    public static function load($configDir)
    {
        $configDir = rtrim($configDir, DIRECTORY_SEPARATOR);
        if (!is_dir($configDir)) {
            return array();
        }

        $merged = array();
        $files = glob($configDir . DIRECTORY_SEPARATOR . '*.php');
        if (!is_array($files)) {
            return array();
        }

        sort($files);
        foreach ($files as $file) {
            $cfg = include $file;
            if (!is_array($cfg)) {
                continue;
            }
            $merged = self::merge($merged, $cfg);
        }

        return $merged;
    }

    public static function merge(array $a, array $b)
    {
        foreach ($b as $k => $v) {
            if (isset($a[$k]) && is_array($a[$k]) && is_array($v)) {
                $a[$k] = self::merge($a[$k], $v);
                continue;
            }
            $a[$k] = $v;
        }
        return $a;
    }

    public static function get(array $config, $key, $default = null)
    {
        if ($key === null || $key === '') {
            return $config;
        }

        $parts = explode('.', $key);
        $value = $config;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }
}
