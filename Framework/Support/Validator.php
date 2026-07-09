<?php

/**
 * 文件作用：轻量参数校验器（规则字符串），用于接口参数验证。
 */

namespace Framework\Support;

class Validator
{
    public static function validate(array $data, array $rules, array $messages = array())
    {
        $errors = array();

        foreach ($rules as $field => $ruleStr) {
            $ruleStr = (string) $ruleStr;
            $list = array_filter(array_map('trim', explode('|', $ruleStr)));

            $exists = array_key_exists($field, $data);
            $value = $exists ? $data[$field] : null;
            $skipIfMissing = in_array('sometimes', $list, true);
            if ($skipIfMissing && !$exists) {
                continue;
            }

            foreach ($list as $rule) {
                $name = $rule;
                $arg = null;
                $pos = strpos($rule, ':');
                if ($pos !== false) {
                    $name = substr($rule, 0, $pos);
                    $arg = substr($rule, $pos + 1);
                }

                $name = strtolower(trim($name));
                if ($name === '') {
                    continue;
                }

                $fail = self::checkRule($name, $arg, $exists, $value);
                if ($fail !== null) {
                    $key = $field . '.' . $name;
                    $msg = array_key_exists($key, $messages) ? $messages[$key] : $fail;
                    if (!isset($errors[$field])) {
                        $errors[$field] = array();
                    }
                    $errors[$field][] = $msg;
                }
            }
        }

        return array(
            'ok' => empty($errors),
            'errors' => $errors,
        );
    }

    private static function checkRule($name, $arg, $exists, $value)
    {
        if ($name === 'sometimes') {
            return null;
        }

        if ($name === 'required') {
            if (!$exists) {
                return 'required';
            }
            if ($value === null) {
                return 'required';
            }
            if (is_string($value) && trim($value) === '') {
                return 'required';
            }
            if (is_array($value) && empty($value)) {
                return 'required';
            }
            return null;
        }

        if ($name === 'nullable') {
            return null;
        }

        if (!$exists || $value === null) {
            return null;
        }

        if ($name === 'bool' || $name === 'boolean') {
            if (is_bool($value)) {
                return null;
            }
            if (is_int($value) && ($value === 0 || $value === 1)) {
                return null;
            }
            if (is_string($value)) {
                $v = strtolower(trim($value));
                if (in_array($v, array('0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'), true)) {
                    return null;
                }
            }
            return 'must_be_bool';
        }

        if ($name === 'string') {
            return is_string($value) ? null : 'must_be_string';
        }

        if ($name === 'int' || $name === 'integer') {
            if (is_int($value)) {
                return null;
            }
            if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
                return null;
            }
            return 'must_be_int';
        }

        if ($name === 'array') {
            return is_array($value) ? null : 'must_be_array';
        }

        if ($name === 'url') {
            if (!is_string($value)) {
                return 'invalid_url';
            }
            return filter_var($value, FILTER_VALIDATE_URL) ? null : 'invalid_url';
        }

        if ($name === 'date') {
            if (!is_string($value)) {
                return 'invalid_date';
            }
            $t = strtotime($value);
            return $t === false ? 'invalid_date' : null;
        }

        if ($name === 'min') {
            $n = (int) $arg;
            if (is_string($value)) {
                return strlen($value) >= $n ? null : 'min';
            }
            if (is_numeric($value)) {
                return ((float) $value) >= $n ? null : 'min';
            }
            if (is_array($value)) {
                return count($value) >= $n ? null : 'min';
            }
            return 'min';
        }

        if ($name === 'max') {
            $n = (int) $arg;
            if (is_string($value)) {
                return strlen($value) <= $n ? null : 'max';
            }
            if (is_numeric($value)) {
                return ((float) $value) <= $n ? null : 'max';
            }
            if (is_array($value)) {
                return count($value) <= $n ? null : 'max';
            }
            return 'max';
        }

        if ($name === 'in') {
            $opts = array();
            if ($arg !== null && $arg !== '') {
                $opts = array_map('trim', explode(',', $arg));
            }
            return in_array((string) $value, $opts, true) ? null : 'not_in';
        }

        if ($name === 'email') {
            if (!is_string($value)) {
                return 'invalid_email';
            }
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'invalid_email';
        }

        if ($name === 'regex') {
            if (!is_string($value)) {
                return 'invalid_format';
            }
            $pattern = (string) $arg;
            if ($pattern === '') {
                return 'invalid_format';
            }
            return preg_match($pattern, $value) ? null : 'invalid_format';
        }

        return null;
    }
}
