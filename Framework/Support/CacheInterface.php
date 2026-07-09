<?php

/**
 * 文件作用：缓存接口约定（get/set/add/forget），用于限流/验签/业务缓存等场景。
 */

namespace Framework\Support;

interface CacheInterface
{
    public function get($key, $default = null);

    public function set($key, $value, $ttlSeconds = 0);

    public function add($key, $value, $ttlSeconds = 0);

    public function forget($key);
}
