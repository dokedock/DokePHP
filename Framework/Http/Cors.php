<?php

/**
 * 文件作用：跨域处理（CORS）封装，负责生成响应 Header 并处理 OPTIONS 预检请求。
 */

namespace Framework\Http;

class Cors
{
    public static function makeHeaders(array $config, Request $request)
    {
        $on = isset($config['enabled']) ? (bool) $config['enabled'] : true;
        if (!$on) {
            return array();
        }

        $allowOrigin = isset($config['allow_origin']) ? (string) $config['allow_origin'] : '*';
        $allowMethods = isset($config['allow_methods']) ? (string) $config['allow_methods'] : 'GET,POST,PUT,PATCH,DELETE,OPTIONS';
        $allowHeaders = isset($config['allow_headers']) ? (string) $config['allow_headers'] : 'Content-Type, Authorization, X-Requested-With';
        $maxAge = isset($config['max_age']) ? (int) $config['max_age'] : 86400;
        $credentials = isset($config['allow_credentials']) ? (bool) $config['allow_credentials'] : false;

        $origin = (string) $request->header('Origin', '');
        if ($allowOrigin !== '*' && $origin !== '' && $origin !== $allowOrigin) {
            return array();
        }

        $finalOrigin = $allowOrigin;
        $vary = '';

        if ($credentials && $allowOrigin === '*') {
            if ($origin !== '') {
                $finalOrigin = $origin;
                $vary = 'Origin';
            } else {
                $finalOrigin = '';
            }
        }

        $out = array(
            'Access-Control-Allow-Origin' => $finalOrigin === '*' ? '*' : $finalOrigin,
            'Access-Control-Allow-Methods' => $allowMethods,
            'Access-Control-Allow-Headers' => $allowHeaders,
            'Access-Control-Max-Age' => (string) $maxAge,
        );

        if ($credentials) {
            $out['Access-Control-Allow-Credentials'] = 'true';
        }

        if ($vary !== '') {
            $out['Vary'] = $vary;
        }

        if ($out['Access-Control-Allow-Origin'] === '') {
            unset($out['Access-Control-Allow-Origin']);
        }

        return $out;
    }

    public static function preflight(array $config, Request $request)
    {
        return new Response('', 204, self::makeHeaders($config, $request));
    }
}
