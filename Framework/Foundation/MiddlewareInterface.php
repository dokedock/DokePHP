<?php

/**
 * 文件作用：中间件接口约定（可选），统一 handle(Request $request, callable $next) 写法。
 */

namespace Framework\Foundation;

interface MiddlewareInterface
{
    public function handle($request, $next);
}

