<?php

declare(strict_types=1);

/**
 * Text helper class. Provides simple methods for working with text.
 *
 * @package    Kohana
 * @category   Helpers
 * @author     Kohana Team
 * @copyright  (c) 2024
 * @license    https://kohana.top/license
 */
class Kohana_Text
{
    /**
     * @var  array<int, string>   number units and text equivalents
     */
    public static array $units = [
        1000000000 => 'billion',
        1000000 => 'million',
        1000 => 'thousand',
        100 => 'hundred',
        90 => 'ninety',
        80 => 'eighty',
        70 => 'seventy',
        60 => 'sixty',
        50 => 'fifty',
        40 => 'forty',
        30 => 'thirty',
        20 => 'twenty',
        19 => 'nineteen',
        18 => 'eighteen',
        17 => 'seventeen',
        16 => 'sixteen',
        15 => 'fifteen',
        14 => 'fourteen',
        13 => 'thirteen',
        12 => 'twelve',
        11 => 'eleven',
        10 => 'ten',
        9 => 'nine',
        8 => 'eight',
        7 => 'seven',
        6 => 'six',
        5 => 'five',
        4 => 'four',
        3 => 'three',
        2 => 'two',
        1 => 'one',
    ];

    /**
     * Limits a phrase to a given number of words.
     *
     * @param string $str Phrase to limit words of
     * @param int $limit Number of words to limit to
     * @param string|null $end_char End character or entity
     * @return string
     */
    public static function limit_words(string $str, int $limit = 100, ?string $end_char = '…'): string
    {
        if (trim($str) === '') {
            return $str;
        }

        if ($limit <= 0) {
            return $end_char;
        }

        preg_match('/^\s*+(?:\S++\s*+){1,' . $limit . '}/u', $str, $matches);

        return rtrim($matches[0]) . ((strlen($matches[0]) === strlen($str)) ? '' : $end_char);
    }

    /**
     * Limits a phrase to a given number of characters.
     *
     * @param string $str Phrase to limit characters of
     * @param int $limit Number of characters to limit to
     * @param string|null $end_char End character or entity
     * @param bool $preserve_words Enable or disable the preservation of words while limiting
     * @return string
     */
    public static function limit_chars(string $str, int $limit = 100, ?string $end_char = '…', bool $preserve_words = false): string
    {
        if (trim($str) === '' || mb_strlen($str) <= $limit) {
            return $str;
        }

        if ($limit <= 0) {
            return $end_char;
        }

        if ($preserve_words === false) {
            return rtrim(mb_substr($str, 0, $limit)) . $end_char;
        }

        if (!preg_match('/^.{0,' . $limit . '}\s/us', $str, $matches)) {
            return $end_char;
        }

        return rtrim($matches[0]) . ((mb_strlen($matches[0]) === mb_strlen($str)) ? '' : $end_char);
    }

    /**
     * Alternates between two or more strings.
     *
     * @param string ...$args Strings to alternate between
     * @return string
     */
    public static function alternate(string ...$args): string
    {
        static $i = 0;

        if (empty($args)) {
            $i = 0;
            return '';
        }

        return $args[($i++ % count($args))];
    }

    /**
     * Generates a random string of a given type and length.
     *
     * @param string|null $type A type of pool, or a string of characters to use as the pool
     * @param int $length Length of string to return
     * @return string
     */
    public static function random(?string $type = 'alnum', int $length = 8): string
    {
        switch ($type) {
            case 'alpha':
                $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'hexdec':
                $pool = '0123456789abcdef';
                break;
            case 'numeric':
                $pool = '0123456789';
                break;
            case 'nozero':
                $pool = '123456789';
                break;
            case 'distinct':
                $pool = '2345679ACDEFHJKLMNPRSTUVWXYZ';
                break;
            case 'alnum':
            default:
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
        }

        $pool = str_split($pool, 1);
        $max = count($pool) - 1;
        $str = '';

        for ($i = 0; $i < $length; $i++) {
            $str .= $pool[random_int(0, $max)];
        }

        return $str;
    }

    /**
     * Uppercase words that are not separated by spaces, using a custom delimiter or the default.
     *
     * @param string $string String to transform
     * @param string $delimiter Delimiter to use
     * @return string
     */
    public static function ucfirst(string $string, string $delimiter = '-'): string
    {
        return implode($delimiter, array_map('ucfirst', explode($delimiter, $string)));
    }

    /**
     * Reduces multiple slashes in a string to single slashes.
     *
     * @param string $str String to reduce slashes of
     * @return string
     */
    public static function reduce_slashes(string $str): string
    {
        return preg_replace('#(?<!:)//+#', '/', $str);
    }

    /**
     * Replaces the given words with a string.
     *
     * @param string $str Phrase to replace words in
     * @param array $badwords Words to replace
     * @param string $replacement Replacement string
     * @param bool $replace_partial_words Replace words across word boundaries (space, period, etc)
     * @return string
     */
    public static function censor(string $str, array $badwords, string $replacement = '#', bool $replace_partial_words = true): string
    {
        foreach ($badwords as $key => $badword) {
            $badwords[$key] = str_replace('\*', '\S*?', preg_quote($badword));
        }

        $regex = '(' . implode('|', $badwords) . ')';

        if ($replace_partial_words === false) {
            $regex = '(?<=\b|\s|^)' . $regex . '(?=\b|\s|$)';
        }

        $regex = '!' . $regex . '!ui';

        if (strlen($replacement) == 1) {
            return preg_replace_callback($regex, function ($matches) use ($replacement) {
                return str_repeat($replacement, strlen($matches[1]));
            }, $str);
        }

        return preg_replace($regex, $replacement, $str);
    }

    /**
     * Finds the text that is similar between a set of words.
     *
     * @param array $words Words to find similar text of
     * @return string
     */
    public static function similar(array $words): string
    {
        $word = current($words);

        for ($i = 0, $max = strlen($word); $i < $max; ++$i) {
            foreach ($words as $w) {
                if (!isset($w[$i]) || $w[$i] !== $word[$i]) {
                    break 2;
                }
            }
        }

        return substr($word, 0, $i);
    }

    /**
     * Converts text email addresses and anchors into links. Existing links will not be altered.
     *
     * @param string $text Text to auto link
     * @return string
     */
    public static function auto_link(string $text): string
    {
        return self::auto_link_urls(self::auto_link_emails($text));
    }

    /**
     * Converts text anchors into links. Existing links will not be altered.
     *
     * @param string $text Text to auto link
     * @return string
     */
    public static function auto_link_urls(string $text): string
    {
        $text = preg_replace_callback('~\b(?<!href="|">)(?:ht|f)tps?://[^<\s]+(?:/|\b)~i', [self::class, '_auto_link_urls_callback1'], $text);
        return preg_replace_callback('~\b(?<!://|">)www(?:\.[a-z0-9][-a-z0-9]*+)+\.[a-z]{2,6}[^<\s]*\b~i', [self::class, '_auto_link_urls_callback2'], $text);
    }

    protected static function _auto_link_urls_callback1(array $matches): string
    {
        return '<a href="' . $matches[0] . '">' . $matches[0] . '</a>';
    }

    protected static function _auto_link_urls_callback2(array $matches): string
    {
        return '<a href="http://' . $matches[0] . '">' . $matches[0] . '</a>';
    }

    /**
     * Converts text email addresses into links. Existing links will not be altered.
     *
     * @param string $text Text to auto link
     * @return string
     */
    public static function auto_link_emails(string $text): string
    {
        return preg_replace_callback('~\b(?<!href="mailto:|58;)(?!\.)[-+_a-z0-9.]++(?<!\.)@(?![-.])[-a-z0-9.]+(?<!\.)\.[a-z]{2,6}\b(?!</a>)~i', [self::class, '_auto_link_emails_callback'], $text);
    }

    protected static function _auto_link_emails_callback(array $matches): string
    {
        return '<a href="mailto:' . $matches[0] . '">' . $matches[0] . '</a>';
    }

    /**
     * Automatically applies "p" and "br" markup to text.
     *
     * @param string $str Subject
     * @param bool $br Convert single linebreaks to <br />
     * @return string
     */
    public static function auto_p(string $str, bool $br = true): string
    {
        if (($str = trim($str)) === '') {
            return '';
        }

        $str = str_replace(["\r\n", "\r"], "\n", $str);
        $str = preg_replace('~^[ \t]+~m', '', $str);
        $str = preg_replace('~[ \t]+$~m', '', $str);

        $html_found = (strpos($str, '<') !== false);

        if ($html_found) {
            $no_p = '(?:p|div|h[1-6r]|ul|ol|li|blockquote|d[dlt]|pre|t[dhr]|t(?:able|body|foot|head)|c(?:aption|olgroup)|form|s(?:elect|tyle)|a(?:ddress|rea)|ma(?:p|th))';
            $str = preg_replace('~^<' . $no_p . '[^>]*+>~im', "\n$0", $str);
            $str = preg_replace('~</' . $no_p . '\s*+>$~im', "$0\n", $str);
        }

        $str = '<p>' . trim($str) . '</p>';
        $str = preg_replace('~\n{2,}~', "</p>\n\n<p>", $str);

        if ($html_found) {
            $str = preg_replace('~<p>(?=</?' . $no_p . '[^>]*+>)~i', '', $str);
            $str = preg_replace('~(</?' . $no_p . '[^>]*+>)</p>~i', '$1', $str);
        }

        if ($br) {
            $str = preg_replace('~(?<!\n)\n(?!\n)~', "<br />\n", $str);
        }

        return $str;
    }

    /**
     * Returns human readable sizes.
     *
     * @param int $bytes Size in bytes
     * @param string|null $force_unit A definitive unit
     * @param string|null $format The return string format
     * @param bool $si Whether to use SI prefixes or IEC
     * @return string
     */
    public static function bytes(int $bytes, ?string $force_unit = null, ?string $format = '%01.2f %s', bool $si = true): string
    {
        if ($si === false || strpos($force_unit, 'i') !== false) {
            $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
            $mod = 1024;
        } else {
            $units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];
            $mod = 1000;
        }

        $power = ($force_unit && in_array($force_unit, $units)) ? array_search($force_unit, $units) : (($bytes > 0) ? floor(log($bytes, $mod)) : 0);

        return sprintf($format, $bytes / pow($mod, $power), $units[$power]);
    }

    /**
     * Format a number to human-readable text.
     *
     * @param int $number Number to format
     * @return string
     */
    public static function number(int $number): string
    {
        $text = [];
        $last_unit = null;
        $last_item = '';

        foreach (self::$units as $unit => $name) {
            if ($number / $unit >= 1) {
                $number -= $unit * ($value = (int) floor($number / $unit));
                $item = '';

                if ($unit < 100) {
                    if ($last_unit < 100 && $last_unit >= 20) {
                        $last_item .= '-' . $name;
                    } else {
                        $item = $name;
                    }
                } else {
                    $item = self::number($value) . ' ' . $name;
                }

                if (empty($item)) {
                    array_pop($text);
                    $item = $last_item;
                }

                $last_item = $text[] = $item;
                $last_unit = $unit;
            }
        }

        if (count($text) > 1) {
            $and = array_pop($text);
        }

        $text = implode(', ', $text);

        if (isset($and)) {
            $text .= ' and ' . $and;
        }

        return $text;
    }

    /**
     * Prevents widow words by inserting a non-breaking space between the last two words.
     *
     * @param string $str Text to remove widows from
     * @return string
     */
    public static function widont(string $str): string
    {
        $widont_regex = "%
			((?:</?(?:a|em|span|strong|i|b)[^>]*>)|[^<>\s]) # must be preceded by an approved inline opening or closing tag or a non-tag/non-space
			\s+                                             # the space to replace
			([^<>\s]+                                       # must be followed by non-tag non-space characters
			\s*                                             # optional white space!
			(</(a|em|span|strong|i|b)>\s*)*                 # optional closing inline tags with optional white space after each
			((</(p|h[1-6]|li|dt|dd)>)|$))                   # end with a closing p, h1-6, li or the end of the string
		%x";

        return preg_replace($widont_regex, '$1&nbsp;$2', $str);
    }

    /**
     * Returns information about the client user agent.
     *
     * @param string $agent User agent string
     * @param string|array $value Array or string to return: browser, version, robot, mobile, platform
     * @return mixed Requested information, false if nothing is found
     */
    public static function user_agent(string $agent, $value)
    {
        if (is_array($value)) {
            $data = [];
            foreach ($value as $part) {
                $data[$part] = self::user_agent($agent, $part);
            }

            return $data;
        }

        if ($value === 'browser' || $value == 'version') {
            $info = [];
            $browsers = Kohana::$config->load('user_agents')->browser;

            foreach ($browsers as $search => $name) {
                if (stripos($agent, $search) !== false) {
                    $info['browser'] = $name;

                    if (preg_match('#' . preg_quote($search) . '[^0-9.]*+([0-9.][0-9.a-z]*)#i', $agent, $matches)) {
                        $info['version'] = $matches[1];
                    } else {
                        $info['version'] = false;
                    }

                    return $info[$value];
                }
            }
        } else {
            $group = Kohana::$config->load('user_agents')->$value;

            foreach ($group as $search => $name) {
                if (stripos($agent, $search) !== false) {
                    return $name;
                }
            }
        }

        return false;
    }
}
