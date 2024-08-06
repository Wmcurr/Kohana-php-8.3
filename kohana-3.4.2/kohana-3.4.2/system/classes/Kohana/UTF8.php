<?php

declare(strict_types=1);
/**
 * A port of [phputf8](http://phputf8.sourceforge.net/) to a unified set
 * of files. Provides multi-byte aware replacement string functions.
 *
 * For UTF-8 support to work correctly, the following requirements must be met:
 *
 * - PCRE needs to be compiled with UTF-8 support (--enable-utf8)
 * - Support for [Unicode properties](http://php.net/manual/reference.pcre.pattern.modifiers.php)
 *   is highly recommended (--enable-unicode-properties)
 * - The [mbstring extension](http://php.net/mbstring) is highly recommended,
 *   but must not be overloading string functions
 *
 * [!!] This file is licensed differently from the rest of Kohana. As a port of
 * [phputf8](http://phputf8.sourceforge.net/), this file is released under the LGPL.
 *
 * @package    Kohana
 * @category   Base
 * @author     Kohana Team
 * @version    PHP 8.3
 * @copyright  (c) 2007-2024 Kohana Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */
class Kohana_UTF8
{
    /**
     * @var  boolean  Does the server support UTF-8 natively?
     */
    public static ?bool $server_utf8 = null;

    /**
     * @var  array  List of called methods that have had their required file included.
     */
    public static array $called = [];

    /**
     * Recursively cleans arrays, objects, and strings. Removes ASCII control
     * codes and converts to the requested charset while silently discarding
     * incompatible characters.
     *
     *     UTF8::clean($_GET); // Clean GET data
     *
     * @param   mixed   $var        variable to clean
     * @param   string|null  $charset    character set, defaults to Kohana::$charset
     * @return  mixed
     * @uses    UTF8::clean
     * @uses    UTF8::strip_ascii_ctrl
     * @uses    UTF8::is_ascii
     */
    public static function clean(mixed $var, ?string $charset = null): mixed
    {
        $charset = $charset ?? Kohana::$charset;

        if (is_array($var) || is_object($var)) {
            foreach ($var as $key => $val) {
                // Recursion!
                $clean_key = UTF8::clean($key);
                $var[$clean_key] = UTF8::clean($val);
            }
        } elseif (is_string($var) && $var !== '') {
            // Remove control characters
            $var = UTF8::strip_ascii_ctrl($var);

            if (!UTF8::is_ascii($var)) {
                // Temporarily save the mb_substitute_character() value into a variable
                $mb_substitute_character = mb_substitute_character();

                // Disable substituting illegal characters with the default '?' character
                mb_substitute_character('none');

                // Convert encoding, this is expensive, used when $var is not ASCII
                $var = mb_convert_encoding($var, $charset, $charset);

                // Reset mb_substitute_character() value back to the original setting
                mb_substitute_character($mb_substitute_character);
            }
        }

        return $var;
    }

    /**
     * Tests whether a string contains only 7-bit ASCII bytes. This is used to
     * determine when to use native functions or UTF-8 functions.
     *
     *     $ascii = UTF8::is_ascii($str);
     *
     * @param   mixed   $str    string or array of strings to check
     * @return  boolean
     */
    public static function is_ascii(mixed $str): bool
    {
        if (is_array($str)) {
            $str = implode($str);
        }

        return !preg_match('/[^\x00-\x7F]/S', $str);
    }

    /**
     * Strips out device control codes in the ASCII range.
     *
     *     $str = UTF8::strip_ascii_ctrl($str);
     *
     * @param   string  $str    string to clean
     * @return  string
     */
    public static function strip_ascii_ctrl(string $str): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $str);
    }

    /**
     * Strips out all non-7bit ASCII bytes.
     *
     *     $str = UTF8::strip_non_ascii($str);
     *
     * @param   string  $str    string to clean
     * @return  string
     */
    public static function strip_non_ascii(string $str): string
    {
        return preg_replace('/[^\x00-\x7F]+/S', '', $str);
    }

    /**
     * Replaces special/accented UTF-8 characters by ASCII-7 "equivalents".
     *
     *     $ascii = UTF8::transliterate_to_ascii($utf8);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string  $str    string to transliterate
     * @param   int $case   -1 lowercase only, +1 uppercase only, 0 both cases
     * @return  string
     */
    public static function transliterate_to_ascii(string $str, int $case = 0): string
    {
        return _transliterate_to_ascii($str, $case);
    }

    /**
     * Returns the length of the given string. This is a UTF8-aware version
     * of [strlen](http://php.net/strlen).
     *
     *     $length = UTF8::strlen($str);
     *
     * @param   string  $str    string being measured for length
     * @return  int
     * @uses    UTF8::$server_utf8
     * @uses    Kohana::$charset
     */
    public static function strlen(string $str): int
    {
        if (UTF8::$server_utf8) {
            return mb_strlen($str, Kohana::$charset);
        }

        return _strlen($str);
    }

    /**
     * Finds position of first occurrence of a UTF-8 string. This is a
     * UTF8-aware version of [strpos](http://php.net/strpos).
     *
     *     $position = UTF8::strpos($str, $search);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str    haystack
     * @param   string  $search needle
     * @param   int $offset offset from which character in haystack to start searching
     * @return  int|false position of needle
     * @uses    UTF8::$server_utf8
     * @uses    Kohana::$charset
     */
    public static function strpos(string $str, string $search, int $offset = 0): int|false
    {
        if (UTF8::$server_utf8) {
            return mb_strpos($str, $search, $offset, Kohana::$charset);
        }

        return _strpos($str, $search, $offset);
    }

    /**
     * Finds position of last occurrence of a char in a UTF-8 string. This is
     * a UTF8-aware version of [strrpos](http://php.net/strrpos).
     *
     *     $position = UTF8::strrpos($str, $search);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str    haystack
     * @param   string  $search needle
     * @param   int $offset offset from which character in haystack to start searching
     * @return  int|false position of needle
     * @uses    UTF8::$server_utf8
     */
    public static function strrpos(string $str, string $search, int $offset = 0): int|false
    {
        if (UTF8::$server_utf8) {
            return mb_strrpos($str, $search, $offset, Kohana::$charset);
        }

        return _strrpos($str, $search, $offset);
    }

    /**
     * Returns part of a UTF-8 string. This is a UTF8-aware version
     * of [substr](http://php.net/substr).
     *
     *     $sub = UTF8::substr($str, $offset);
     *
     * @author  Chris Smith <chris@jalakai.co.uk>
     * @param   string  $str    input string
     * @param   int $offset offset
     * @param   int|null $length length limit
     * @return  string
     * @uses    UTF8::$server_utf8
     * @uses    Kohana::$charset
     */
    public static function substr(string $str, int $offset, ?int $length = null): string
    {
        if (UTF8::$server_utf8) {
            return ($length === null) ? mb_substr($str, $offset, mb_strlen($str), Kohana::$charset) : mb_substr($str, $offset, $length, Kohana::$charset);
        }

        return _substr($str, $offset, $length);
    }

    /**
     * Replaces text within a portion of a UTF-8 string. This is a UTF8-aware
     * version of [substr_replace](http://php.net/substr_replace).
     *
     *     $str = UTF8::substr_replace($str, $replacement, $offset);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str            input string
     * @param   string  $replacement    replacement string
     * @param   int $offset         offset
     * @param   int|null $length length limit
     * @return  string
     */
    public static function substr_replace(string $str, string $replacement, int $offset, ?int $length = null): string
    {
        return _substr_replace($str, $replacement, $offset, $length);
    }

    /**
     * Makes a UTF-8 string lowercase. This is a UTF8-aware version
     * of [strtolower](http://php.net/strtolower).
     *
     *     $str = UTF8::strtolower($str);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string  $str mixed case string
     * @return  string
     * @uses    UTF8::$server_utf8
     * @uses    Kohana::$charset
     */
    public static function strtolower(string $str): string
    {
        if (UTF8::$server_utf8) {
            return mb_strtolower($str, Kohana::$charset);
        }

        return _strtolower($str);
    }

    /**
     * Makes a UTF-8 string uppercase. This is a UTF8-aware version
     * of [strtoupper](http://php.net/strtoupper).
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string  $str mixed case string
     * @return  string
     * @uses    UTF8::$server_utf8
     * @uses    Kohana::$charset
     */
    public static function strtoupper(string $str): string
    {
        if (UTF8::$server_utf8) {
            return mb_strtoupper($str, Kohana::$charset);
        }

        return _strtoupper($str);
    }

    /**
     * Makes a UTF-8 string's first character uppercase. This is a UTF8-aware
     * version of [ucfirst](http://php.net/ucfirst).
     *
     *     $str = UTF8::ucfirst($str);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str mixed case string
     * @return  string
     */
    public static function ucfirst(string $str): string
    {
        return _ucfirst($str);
    }

    /**
     * Makes the first character of every word in a UTF-8 string uppercase.
     * This is a UTF8-aware version of [ucwords](http://php.net/ucwords).
     *
     *     $str = UTF8::ucwords($str);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str mixed case string
     * @return  string
     */
    public static function ucwords(string $str): string
    {
        return _ucwords($str);
    }

    /**
     * Case-insensitive UTF-8 string comparison. This is a UTF8-aware version
     * of [strcasecmp](http://php.net/strcasecmp).
     *
     *     $compare = UTF8::strcasecmp($str1, $str2);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str1   string to compare
     * @param   string  $str2   string to compare
     * @return  int less than 0 if str1 is less than str2
     * @return  int greater than 0 if str1 is greater than str2
     * @return  int 0 if they are equal
     */
    public static function strcasecmp(string $str1, string $str2): int
    {
        return _strcasecmp($str1, $str2);
    }

    /**
     * Returns a string or an array with all occurrences of search in subject
     * (ignoring case) and replaced with the given replace value. This is a
     * UTF8-aware version of [str_ireplace](http://php.net/str_ireplace).
     *
     * [!!] This function is very slow compared to the native version. Avoid
     * using it when possible.
     *
     * @author  Harry Fuecks <hfuecks@gmail.com
     * @param   string|array    $search     text to replace
     * @param   string|array    $replace    replacement text
     * @param   string|array    $str        subject text
     * @param   int|null         $count      number of matched and replaced needles will be returned via this parameter which is passed by reference
     * @return  string|array
     */
    public static function str_ireplace(string|array $search, string|array $replace, string|array $str, ?int &$count = null): string|array
    {
        return _str_ireplace($search, $replace, $str, $count);
    }

    /**
     * Case-insensitive UTF-8 version of strstr. Returns all of input string
     * from the first occurrence of needle to the end. This is a UTF8-aware
     * version of [stristr](http://php.net/stristr).
     *
     *     $found = UTF8::stristr($str, $search);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str    input string
     * @param   string  $search needle
     * @return  string|false  matched substring if found
     */
    public static function stristr(string $str, string $search): string|false
    {
        return _stristr($str, $search);
    }

    /**
     * Finds the length of the initial segment matching mask. This is a
     * UTF8-aware version of [strspn](http://php.net/strspn).
     *
     *     $found = UTF8::strspn($str, $mask);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str    input string
     * @param   string  $mask   mask for search
     * @param   int|null $offset start position of the string to examine
     * @param   int|null $length length of the string to examine
     * @return  int length of the initial segment that contains characters in the mask
     */
    public static function strspn(string $str, string $mask, ?int $offset = null, ?int $length = null): int
    {
        return _strspn($str, $mask, $offset, $length);
    }

    /**
     * Finds the length of the initial segment not matching mask. This is a
     * UTF8-aware version of [strcspn](http://php.net/strcspn).
     *
     *     $found = UTF8::strcspn($str, $mask);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str    input string
     * @param   string  $mask   mask for search
     * @param   int|null $offset start position of the string to examine
     * @param   int|null $length length of the string to examine
     * @return  int length of the initial segment that contains characters not in the mask
     */
    public static function strcspn(string $str, string $mask, ?int $offset = null, ?int $length = null): int
    {
        return _strcspn($str, $mask, $offset, $length);
    }

    /**
     * Pads a UTF-8 string to a certain length with another string. This is a
     * UTF8-aware version of [str_pad](http://php.net/str_pad).
     *
     *     $str = UTF8::str_pad($str, $length);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str                input string
     * @param   int $final_str_length   desired string length after padding
     * @param   string  $pad_str            string to use as padding
     * @param   int  $pad_type           padding type: STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH
     * @return  string
     */
    public static function str_pad(string $str, int $final_str_length, string $pad_str = ' ', int $pad_type = STR_PAD_RIGHT): string
    {
        return _str_pad($str, $final_str_length, $pad_str, $pad_type);
    }

    /**
     * Converts a UTF-8 string to an array. This is a UTF8-aware version of
     * [str_split](http://php.net/str_split).
     *
     *     $array = UTF8::str_split($str);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str            input string
     * @param   int $split_length   maximum length of each chunk
     * @return  array
     */
    public static function str_split(string $str, int $split_length = 1): array
    {
        return _str_split($str, $split_length);
    }

    /**
     * Reverses a UTF-8 string. This is a UTF8-aware version of [strrev](http://php.net/strrev).
     *
     *     $str = UTF8::strrev($str);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $str string to be reversed
     * @return  string
     */
    public static function strrev(string $str): string
    {
        return _strrev($str);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning and
     * end of a string. This is a UTF8-aware version of [trim](http://php.net/trim).
     *
     *     $str = UTF8::trim($str);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string  $str        input string
     * @param   string|null  $charlist   string of characters to remove
     * @return  string
     */
    public static function trim(string $str, ?string $charlist = null): string
    {
        return _trim($str, $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning of
     * a string. This is a UTF8-aware version of [ltrim](http://php.net/ltrim).
     *
     *     $str = UTF8::ltrim($str);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string  $str        input string
     * @param   string|null  $charlist   string of characters to remove
     * @return  string
     */
    public static function ltrim(string $str, ?string $charlist = null): string
    {
        return _ltrim($str, $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the end of a string.
     * This is a UTF8-aware version of [rtrim](http://php.net/rtrim).
     *
     *     $str = UTF8::rtrim($str);
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     * @param   string  $str        input string
     * @param   string|null  $charlist   string of characters to remove
     * @return  string
     */
    public static function rtrim(string $str, ?string $charlist = null): string
    {
        return _rtrim($str, $charlist);
    }

    /**
     * Returns the unicode ordinal for a character. This is a UTF8-aware
     * version of [ord](http://php.net/ord).
     *
     *     $digit = UTF8::ord($character);
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     * @param   string  $chr    UTF-8 encoded character
     * @return  int
     */
    public static function ord(string $chr): int
    {
        return _ord($chr);
    }

    /**
     * Takes an UTF-8 string and returns an array of ints representing the Unicode characters.
     * Astral planes are supported i.e. the ints in the output can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     *     $array = UTF8::to_unicode($str);
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see <http://hsivonen.iki.fi/php-utf8/>
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>
     *
     * @param   string  $str    UTF-8 encoded string
     * @return  array|false   unicode code points
     */
    public static function to_unicode(string $str): array|false
    {
        return _to_unicode($str);
    }

    /**
     * Takes an array of ints representing the Unicode characters and returns a UTF-8 string.
     * Astral planes are supported i.e. the ints in the input can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     *     $str = UTF8::to_unicode($array);
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see http://hsivonen.iki.fi/php-utf8/
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>.
     *
     * @param   array   $str    unicode code points representing a string
     * @return  string|false  utf8 string of characters
     */
    public static function from_unicode(array $arr): string|false
    {
        return _from_unicode($arr);
    }
}

if (Kohana_UTF8::$server_utf8 === null) {
    // Determine if this server supports UTF-8 natively
    Kohana_UTF8::$server_utf8 = extension_loaded('mbstring');
}
