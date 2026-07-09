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

                $fail = self::checkRule($name, $arg, $exists, $value, $data, $field);
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

    private static function checkRule($name, $arg, $exists, $value, array $data, $field)
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

        if ($name === 'required_if') {
            if ($arg === null || $arg === '') {
                return null;
            }
            $tmp = explode(',', (string) $arg, 2);
            $other = isset($tmp[0]) ? (string) $tmp[0] : '';
            $expected = isset($tmp[1]) ? (string) $tmp[1] : '';
            if ($other === '') {
                return null;
            }
            $otherExists = array_key_exists($other, $data);
            $otherVal = $otherExists ? $data[$other] : null;
            if ((string) $otherVal === $expected) {
                $fail = self::checkRule('required', null, $exists, $value, $data, $field);
                return $fail === null ? null : 'required_if';
            }
            return null;
        }

        if ($name === 'required_with') {
            if ($arg === null || $arg === '') {
                return null;
            }
            $others = array_map('trim', explode(',', (string) $arg));
            $others = array_values(array_filter($others, function ($v) {
                return $v !== '';
            }));
            if (empty($others)) {
                return null;
            }
            foreach ($others as $o) {
                if (array_key_exists($o, $data)) {
                    $fail = self::checkRule('required', null, $exists, $value, $data, $field);
                    return $fail === null ? null : 'required_with';
                }
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

        if ($name === 'numeric') {
            return is_numeric($value) ? null : 'must_be_numeric';
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

        if ($name === 'between') {
            $tmp = explode(',', (string) $arg, 2);
            $min = isset($tmp[0]) ? (int) $tmp[0] : 0;
            $max = isset($tmp[1]) ? (int) $tmp[1] : 0;
            if ($max < $min) {
                $max = $min;
            }
            if (is_string($value)) {
                $len = strlen($value);
                return ($len >= $min && $len <= $max) ? null : 'between';
            }
            if (is_numeric($value)) {
                $num = (float) $value;
                return ($num >= $min && $num <= $max) ? null : 'between';
            }
            if (is_array($value)) {
                $cnt = count($value);
                return ($cnt >= $min && $cnt <= $max) ? null : 'between';
            }
            return 'between';
        }

        if ($name === 'in') {
            $opts = array();
            if ($arg !== null && $arg !== '') {
                $opts = array_map('trim', explode(',', $arg));
            }
            return in_array((string) $value, $opts, true) ? null : 'not_in';
        }

        if ($name === 'same') {
            $other = (string) $arg;
            if ($other === '') {
                return null;
            }
            $otherVal = array_key_exists($other, $data) ? $data[$other] : null;
            return ((string) $value === (string) $otherVal) ? null : 'not_same';
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
