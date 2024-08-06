<?php

declare(strict_types=1);

/**
 * Number helper class. Provides additional formatting methods for working
 * with numbers.
 *
 * @package    Kohana
 * @category   Helpers
 */
class Kohana_Num
{
    public const ROUND_HALF_UP = 1;
    public const ROUND_HALF_DOWN = 2;
    public const ROUND_HALF_EVEN = 3;
    public const ROUND_HALF_ODD = 4;

    /**
     * @var array<string, int> Valid byte units => power of 2 that defines the unit's size
     */
    public static array $byte_units = [
        'B' => 0,
        'K' => 10,
        'Ki' => 10,
        'KB' => 10,
        'KiB' => 10,
        'M' => 20,
        'Mi' => 20,
        'MB' => 20,
        'MiB' => 20,
        'G' => 30,
        'Gi' => 30,
        'GB' => 30,
        'GiB' => 30,
        'T' => 40,
        'Ti' => 40,
        'TB' => 40,
        'TiB' => 40,
        'P' => 50,
        'Pi' => 50,
        'PB' => 50,
        'PiB' => 50,
        'E' => 60,
        'Ei' => 60,
        'EB' => 60,
        'EiB' => 60,
        'Z' => 70,
        'Zi' => 70,
        'ZB' => 70,
        'ZiB' => 70,
        'Y' => 80,
        'Yi' => 80,
        'YB' => 80,
        'YiB' => 80,
    ];

    /**
     * Returns the English ordinal suffix (th, st, nd, etc) of a number.
     *
     *     echo 2, Num::ordinal(2);   // "2nd"
     *     echo 10, Num::ordinal(10); // "10th"
     *     echo 33, Num::ordinal(33); // "33rd"
     *
     * @param int $number
     * @return string
     */
    public static function ordinal(int $number): string
    {
        if ($number % 100 > 10 && $number % 100 < 14) {
            return 'th';
        }

        switch ($number % 10) {
            case 1:
                return 'st';
            case 2:
                return 'nd';
            case 3:
                return 'rd';
            default:
                return 'th';
        }
    }

    /**
     * Locale-aware number and monetary formatting.
     *
     *     // In English, "1,200.05"
     *     // In Spanish, "1200,05"
     *     // In Portuguese, "1 200,05"
     *     echo Num::format(1200.05, 2);
     *
     *     // In English, "1,200.05"
     *     // In Spanish, "1.200,05"
     *     // In Portuguese, "1.200.05"
     *     echo Num::format(1200.05, 2, true);
     *
     * @param float $number Number to format
     * @param int $places Decimal places
     * @param bool $monetary Monetary formatting?
     * @return string
     */
    public static function format(float $number, int $places, bool $monetary = false): string
    {
        $info = localeconv();

        $decimal = $monetary ? $info['mon_decimal_point'] : $info['decimal_point'];
        $thousands = $monetary ? $info['mon_thousands_sep'] : $info['thousands_sep'];

        return number_format($number, $places, $decimal, $thousands);
    }

    /**
     * Round a number to a specified precision, using a specified tie-breaking technique.
     *
     * @param float $value Number to round
     * @param int $precision Desired precision
     * @param int $mode Tie breaking mode, accepts the PHP_ROUND_HALF_* constants
     * @return float Rounded number
     */
    public static function round(float $value, int $precision = 0, int $mode = self::ROUND_HALF_UP): float
    {
        return round($value, $precision, $mode);
    }

    /**
     * Converts a file size number to a byte value.
     * File sizes are defined in the format: SB, where S is the size (1, 8.5, 300, etc.)
     * and B is the byte unit (K, MiB, GB, etc.). All valid byte units are defined in Num::$byte_units.
     *
     *     echo Num::bytes('200K');  // 204800
     *     echo Num::bytes('5MiB');  // 5242880
     *     echo Num::bytes('1000');  // 1000
     *     echo Num::bytes('2.5GB'); // 2684354560
     *
     * @param string $size File size in SB format
     * @return float
     * @throws InvalidArgumentException
     */
    public static function bytes(string $size): float
    {
        // Prepare the size
        $size = trim($size);

        // Construct an OR list of byte units for the regex
        $accepted = implode('|', array_keys(self::$byte_units));

        // Construct the regex pattern for verifying the size format
        $pattern = '/^([0-9]+(?:\.[0-9]+)?)(' . $accepted . ')?$/Di';

        // Verify the size format and store the matching parts
        if (!preg_match($pattern, $size, $matches)) {
            throw new InvalidArgumentException('The byte unit size, ":size", is improperly formatted.', [':size' => $size]);
        }

        // Find the float value of the size
        $size = (float) $matches[1];

        // Find the actual unit, assume B if no unit specified
        $unit = $matches[2] ?? 'B';

        // Convert the size into bytes
        $bytes = $size * (2 ** self::$byte_units[$unit]);

        return $bytes;
    }
}
