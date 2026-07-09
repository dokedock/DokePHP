<?php

/**
 * 文件作用：鉴权辅助（Token 载入/校验/过期/吊销），并把 token payload 注入到 Request attributes。
 */

namespace Framework\Support;

use Framework\Foundation\Application;

class Auth
{
    public static function loadTokenMap(Application $app, array $cfg)
    {
        $tokens = array();

        if (isset($cfg['tokens']) && is_array($cfg['tokens'])) {
            foreach ($cfg['tokens'] as $t) {
                $t = trim((string) $t);
                if ($t === '') {
                    continue;
                }
                $tokens[$t] = array('token' => $t, 'exp' => 0, 'uid' => null);
            }
        }

        $tokenFile = isset($cfg['token_file']) ? (string) $cfg['token_file'] : '';
        if ($tokenFile !== '') {
            $path = self::absPath($app, $tokenFile);
            if (is_file($path) && is_readable($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $parsed = self::parseTokenLine($line);
                        if (!$parsed) {
                            continue;
                        }
                        $tokens[$parsed['token']] = $parsed;
                    }
                }
            }
        }

        $revoked = array();
        if (isset($cfg['revoked']) && is_array($cfg['revoked'])) {
            foreach ($cfg['revoked'] as $t) {
                $t = trim((string) $t);
                if ($t !== '') {
                    $revoked[$t] = true;
                }
            }
        }

        $revokedFile = isset($cfg['revoked_file']) ? (string) $cfg['revoked_file'] : '';
        if ($revokedFile !== '') {
            $path = self::absPath($app, $revokedFile);
            if (is_file($path) && is_readable($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim((string) $line);
                        if ($line === '' || strpos($line, '#') === 0) {
                            continue;
                        }
                        $revoked[$line] = true;
                    }
                }
            }
        }

        return array('tokens' => $tokens, 'revoked' => $revoked);
    }

    public static function validateToken($token, array $tokenMap)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $tokens = isset($tokenMap['tokens']) && is_array($tokenMap['tokens']) ? $tokenMap['tokens'] : array();
        if (!array_key_exists($token, $tokens)) {
            return false;
        }

        $revoked = isset($tokenMap['revoked']) && is_array($tokenMap['revoked']) ? $tokenMap['revoked'] : array();
        if (array_key_exists($token, $revoked)) {
            return false;
        }

        $payload = $tokens[$token];
        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        if ($exp !== 0 && time() > $exp) {
            return false;
        }

        return $payload;
    }

    private static function parseTokenLine($line)
    {
        $line = trim((string) $line);
        if ($line === '' || strpos($line, '#') === 0) {
            return false;
        }

        $parts = array();
        if (strpos($line, '|') !== false) {
            $parts = explode('|', $line);
        } elseif (strpos($line, ',') !== false) {
            $parts = explode(',', $line);
        } else {
            $parts = preg_split('/\s+/', $line);
        }

        $parts = array_map('trim', $parts);
        $parts = array_values(array_filter($parts, function ($v) {
            return $v !== '';
        }));

        if (empty($parts)) {
            return false;
        }

        $token = (string) $parts[0];
        $exp = 0;
        $uid = null;

        if (isset($parts[1]) && $parts[1] !== '') {
            $exp = ctype_digit((string) $parts[1]) ? (int) $parts[1] : 0;
        }
        if (isset($parts[2]) && $parts[2] !== '') {
            $uid = (string) $parts[2];
        }

        if ($token === '') {
            return false;
        }

        return array('token' => $token, 'exp' => $exp, 'uid' => $uid);
    }

    private static function absPath(Application $app, $path)
    {
        $path = (string) $path;
        if ($path === '') {
            return '';
        }
        if (strpos($path, DIRECTORY_SEPARATOR) === 0) {
            return $path;
        }
        return $app->basePath() . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
