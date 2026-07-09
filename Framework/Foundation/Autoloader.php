<?php

/**
 * 文件作用：PSR-4 思路的自动加载器（手写实现，不依赖 Composer）。
 */

namespace Framework\Foundation;

class Autoloader
{
    private $prefixMap = array();

    public function addPsr4($prefix, $baseDir)
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!isset($this->prefixMap[$prefix])) {
            $this->prefixMap[$prefix] = array();
        }

        $this->prefixMap[$prefix][] = $baseDir;
        return $this;
    }

    public function register()
    {
        spl_autoload_register(array($this, 'load'));
        return $this;
    }

    public function load($class)
    {
        $class = ltrim($class, '\\');

        foreach ($this->prefixMap as $prefix => $dirs) {
            if (strpos($class, $prefix) !== 0) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            foreach ($dirs as $dir) {
                $file = $dir . $relativePath;
                if (is_file($file)) {
                    require $file;
                    return true;
                }
            }
        }

        return false;
    }
}
