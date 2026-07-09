<?php

/**
 * 文件作用：分页工具（page/page_size），用于统一列表接口的分页入参与分页元信息输出。
 */

namespace Framework\Support;

use Framework\Http\Request;

class Paginator
{
    public static function fromRequest(Request $request, $defaultPageSize = 20, $maxPageSize = 100)
    {
        $page = (int) $request->query('page', 1);
        $pageSize = (int) $request->query('page_size', $defaultPageSize);

        if ($page <= 0) {
            $page = 1;
        }
        if ($pageSize <= 0) {
            $pageSize = (int) $defaultPageSize;
        }
        if ($maxPageSize > 0 && $pageSize > $maxPageSize) {
            $pageSize = (int) $maxPageSize;
        }

        $offset = ($page - 1) * $pageSize;
        if ($offset < 0) {
            $offset = 0;
        }

        return array(
            'page' => $page,
            'page_size' => $pageSize,
            'offset' => $offset,
            'limit' => $pageSize,
        );
    }

    public static function wrap($items, $total, $page, $pageSize, array $extra = array())
    {
        $payload = array(
            'items' => is_array($items) ? $items : array(),
            'total' => (int) $total,
            'page' => (int) $page,
            'page_size' => (int) $pageSize,
        );

        foreach ($extra as $k => $v) {
            if (!array_key_exists($k, $payload)) {
                $payload[$k] = $v;
            }
        }

        return $payload;
    }
}

