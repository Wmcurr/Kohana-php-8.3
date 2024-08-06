<?php
declare(strict_types=1);

class Kohana_Debug
{
    /**
     * Returns an HTML string of debugging information about any number of
     * variables, each wrapped in a "pre" tag.
     *
     * @param   mixed   ...$vars    variables to debug
     * @return  string
     */
    public static function vars(...$vars): string
    {
        $output = array_map(fn($var) => self::_dump($var, 1024), $vars);
        return '<pre class="debug">' . implode("\n", $output) . '</pre>';
    }

    /**
     * Returns an HTML string of information about a single variable.
     *
     * @param   mixed   $value              variable to dump
     * @param   int     $length             maximum length of strings
     * @param   int     $level_recursion    recursion limit
     * @return  string
     */
    public static function dump($value, int $length = 128, int $level_recursion = 10): string
    {
        return self::_dump($value, $length, $level_recursion);
    }

    /**
     * Helper for Debug::dump(), handles recursion in arrays and objects.
     *
     * @param   mixed   $var    variable to dump
     * @param   int     $length maximum length of strings
     * @param   int     $limit  recursion limit
     * @param   int     $level  current recursion level (internal usage only!)
     * @return  string
     */
    protected static function _dump(&$var, int $length = 128, int $limit = 10, int $level = 0): string
    {
        switch (true) {
            case is_null($var):
                return '<small>NULL</small>';
            case is_bool($var):
                return '<small>bool</small> ' . ($var ? 'TRUE' : 'FALSE');
            case is_float($var):
                return '<small>float</small> ' . $var;
            case is_resource($var):
                $type = get_resource_type($var);
                if ($type === 'stream' && $meta = stream_get_meta_data($var)) {
                    $file = $meta['uri'] ?? '';
                    if (function_exists('stream_is_local') && stream_is_local($file)) {
                        $file = self::path($file);
                    }
                    return '<small>resource</small><span>(' . $type . ')</span> ' . htmlspecialchars($file, ENT_NOQUOTES);
                }
                return '<small>resource</small><span>(' . $type . ')</span>';
            case is_string($var):
                $var = mb_convert_encoding($var, 'UTF-8', 'UTF-8');
                $str = mb_strlen($var) > $length ? htmlspecialchars(mb_substr($var, 0, $length), ENT_NOQUOTES) . '&nbsp;&hellip;' : htmlspecialchars($var, ENT_NOQUOTES);
                return '<small>string</small><span>(' . mb_strlen($var) . ')</span> "' . $str . '"';
            case is_array($var):
                return self::_dumpArray($var, $length, $limit, $level);
            case is_object($var):
                return self::_dumpObject($var, $length, $limit, $level);
            default:
                return '<small>' . gettype($var) . '</small> ' . htmlspecialchars(print_r($var, true), ENT_NOQUOTES);
        }
    }

    /**
     * Dumps an array.
     *
     * @param   array   $var
     * @param   int     $length
     * @param   int     $limit
     * @param   int     $level
     * @return  string
     */
    protected static function _dumpArray(array &$var, int $length, int $limit, int $level): string
    {
        $output = [];
        $space = str_repeat('    ', $level);
        static $marker;

        if ($marker === null) {
            $marker = uniqid("\x00") . "x";
        }

        if (empty($var)) {
            return '<small>array</small><span>(' . count($var) . ')</span> []';
        } elseif (isset($var[$marker])) {
            $output[] = "*RECURSION*";
        } elseif ($level < $limit) {
            $output[] = '<span>(';
            $var[$marker] = true;
            foreach ($var as $key => &$val) {
                if ($key === $marker) continue;
                $key = is_int($key) ? $key : '"' . htmlspecialchars((string) $key, ENT_NOQUOTES) . '"';
                $output[] = "$space    $key => " . self::_dump($val, $length, $limit, $level + 1);
            }
            unset($var[$marker]);
            $output[] = "$space)</span>";
        } else {
            $output[] = "(\n$space    ...\n$space)";
        }

        return '<small>array</small><span>(' . count($var) . ')</span> ' . implode("\n", $output);
    }

    /**
     * Dumps an object.
     *
     * @param   object  $var
     * @param   int     $length
     * @param   int     $limit
     * @param   int     $level
     * @return  string
     */
    protected static function _dumpObject(object &$var, int $length, int $limit, int $level): string
    {
        $output = [];
        $space = str_repeat('    ', $level);
        $hash = spl_object_hash($var);
        static $objects = [];

        if (empty($var)) {
            return '<small>object</small> <span>(' . get_class($var) . ')</span> {}';
        } elseif (isset($objects[$hash])) {
            $output[] = "*RECURSION*";
        } elseif ($level < $limit) {
            $output[] = "<code>{";
            $objects[$hash] = true;
            foreach ((array) $var as $key => &$val) {
                $access = $key[0] === "\x00" ? ($key[1] === '*' ? '<small>protected</small>' : '<small>private</small>') : '<small>public</small>';
                $key = $key[0] === "\x00" ? substr($key, strrpos($key, "\x00") + 1) : $key;
                $output[] = "$space    $access $key => " . self::_dump($val, $length, $limit, $level + 1);
            }
            unset($objects[$hash]);
            $output[] = "$space}</code>";
        } else {
            $output[] = "{\n$space    ...\n$space}";
        }

        return '<small>object</small> <span>' . get_class($var) . '(' . count((array) $var) . ')</span> ' . implode("\n", $output);
    }

    /**
     * Removes application, system, modpath, or docroot from a filename,
     * replacing them with the plain text equivalents.
     *
     * @param   string  $file   path to debug
     * @return  string
     */
    public static function path(string $file): string
    {
        if (strpos($file, APPPATH) === 0) {
            return 'APPPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(APPPATH));
        } elseif (strpos($file, SYSPATH) === 0) {
            return 'SYSPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(SYSPATH));
        } elseif (strpos($file, MODPATH) === 0) {
            return 'MODPATH' . DIRECTORY_SEPARATOR . substr($file, strlen(MODPATH));
        } elseif (strpos($file, DOCROOT) === 0) {
            return 'DOCROOT' . DIRECTORY_SEPARATOR . substr($file, strlen(DOCROOT));
        }
        return $file;
    }

    /**
     * Returns an HTML string, highlighting a specific line of a file, with some
     * number of lines padded above and below.
     *
     * @param   string  $file           file to open
     * @param   int     $line_number    line number to highlight
     * @param   int     $padding        number of padding lines
     * @return  string
     * @return  false   File is unreadable
     */
    public static function source(string $file, int $line_number, int $padding = 5): ?string
    {
        if (!is_readable($file)) {
            return null;
        }

        $lines = file($file);
        $start = max($line_number - $padding - 1, 0);
        $end = min($line_number + $padding, count($lines));
        $source = '';

        for ($i = $start; $i < $end; $i++) {
            $line = htmlspecialchars($lines[$i], ENT_NOQUOTES);
            $number = str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            if ($i === $line_number - 1) {
                $source .= '<span class="line highlight">' . $number . ': ' . $line . '</span>';
            } else {
                $source .= '<span class="line">' . $number . ': ' . $line . '</span>';
            }
        }

        return '<pre class="source"><code>' . $source . '</code></pre>';
    }

    /**
     * Returns an array of HTML strings that represent each step in the backtrace.
     *
     * @param   array|null  $trace
     * @return  array
     */
    public static function trace(array $trace = null): array
    {
        $trace = $trace ?? debug_backtrace();
        $output = [];

        foreach ($trace as $step) {
            if (!isset($step['function'])) {
                continue;
            }

            $file = $step['file'] ?? '[internal function]';
            $line = $step['line'] ?? null;
            $function = $step['function'];
            $args = $step['args'] ?? [];

            if (isset($step['class'])) {
                $function = $step['class'] . $step['type'] . $step['function'];
            }

            // Добавляем источник и строку ошибки
            $source = isset($step['file']) ? self::source($step['file'], $step['line']) : null;

            $output[] = [
                'file' => $file,
                'line' => $line,
                'function' => $function,
                'args' => $args,
                'source' => $source,
            ];
        }

        return $output;
    }
}
