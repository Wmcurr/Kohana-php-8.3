<?php
declare(strict_types=1);

/**
 * Date helper.
 *
 * Provides various date-related utilities and calculations.
 * @php 8.3
 * @package    Kohana
 * @category   Helpers
 */
class Kohana_Date
{
    // Second amounts for various time increments
    public const YEAR = 31556926;
    public const MONTH = 2629744;
    public const WEEK = 604800;
    public const DAY = 86400;
    public const HOUR = 3600;
    public const MINUTE = 60;
    // Available formats for Date::months()
    public const MONTHS_LONG = '%B';
    public const MONTHS_SHORT = '%b';

    /**
     * Default timestamp format for formatted_time
     * @var string
     */
    public static string $timestamp_format = 'Y-m-d H:i:s';

    /**
     * Timezone for formatted_time
     * @var string|null
     */
    public static ?string $timezone = null;

    /**
     * Returns the offset (in seconds) between two time zones.
     *
     * @param   string $remote Timezone to find the offset of
     * @param   string|null $local Timezone used as the baseline
     * @param   mixed $now UNIX timestamp or date string
     * @return  int
     */
    public static function offset(string $remote, ?string $local = null, $now = null): int
    {
        $local = $local ?? date_default_timezone_get();
        $now = is_int($now) ? date(DATE_RFC2822, $now) : (string)$now;

        $zone_remote = new DateTimeZone($remote);
        $zone_local = new DateTimeZone($local);

        $time_remote = new DateTime($now, $zone_remote);
        $time_local = new DateTime($now, $zone_local);

        return $zone_remote->getOffset($time_remote) - $zone_local->getOffset($time_local);
    }

    /**
     * Number of seconds in a minute, incrementing by a step.
     *
     * @param   int $step Amount to increment each step by, 1 to 30
     * @param   int $start Start value
     * @param   int $end End value
     * @return  array<int, string> A mirrored (foo => foo) array from 1-60.
     */
    public static function seconds(int $step = 1, int $start = 0, int $end = 60): array
    {
        $seconds = [];
        for ($i = $start; $i < $end; $i += $step) {
            $seconds[$i] = sprintf('%02d', $i);
        }
        return $seconds;
    }

    /**
     * Number of minutes in an hour, incrementing by a step.
     *
     * @param   int $step Amount to increment each step by, 1 to 30
     * @return  array<int, string> A mirrored (foo => foo) array from 1-60.
     */
    public static function minutes(int $step = 5): array
    {
        return self::seconds($step);
    }

    /**
     * Number of hours in a day. Typically used as a shortcut for generating a list.
     *
     * @param   int $step Amount to increment each step by
     * @param   bool $long Use 24-hour time
     * @param   int|null $start The hour to start at
     * @return  array<int, string> A mirrored (foo => foo) array from start-12 or start-23.
     */
    public static function hours(int $step = 1, bool $long = false, ?int $start = null): array
    {
        $start = $start ?? ($long ? 0 : 1);
        $size = $long ? 23 : 12;

        $hours = [];
        for ($i = $start; $i <= $size; $i += $step) {
            $hours[$i] = (string)$i;
        }

        return $hours;
    }

    /**
     * Returns AM or PM, based on a given hour (in 24-hour format).
     *
     * @param   int $hour Number of the hour
     * @return  string
     */
    public static function ampm(int $hour): string
    {
        return ($hour > 11) ? 'PM' : 'AM';
    }

    /**
     * Adjusts a non-24-hour number into a 24-hour number.
     *
     * @param   int $hour Hour to adjust
     * @param   string $ampm AM or PM
     * @return  string
     */
    public static function adjust(int $hour, string $ampm): string
    {
        $ampm = strtolower($ampm);
        if ($ampm === 'am' && $hour == 12) {
            $hour = 0;
        } elseif ($ampm === 'pm' && $hour < 12) {
            $hour += 12;
        }
        return sprintf('%02d', $hour);
    }

    /**
     * Number of days in a given month and year.
     *
     * @param   int $month Number of month
     * @param   int|false $year Number of year to check month, defaults to the current year
     * @return  array<int, string> A mirrored (foo => foo) array of the days.
     */
    public static function days(int $month, $year = false): array
    {
        $year = $year === false ? (int)date('Y') : $year;
        $total = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

        $days = [];
        for ($i = 1; $i <= $total; $i++) {
            $days[$i] = (string)$i;
        }

        return $days;
    }

    /**
     * Number of months in a year.
     *
     * @param   string|null $format The format to use for months
     * @return  array<int, string> An array of months based on the specified format
     */
    public static function months(?string $format = null): array
    {
        $months = [];
        if ($format === self::MONTHS_LONG || $format === self::MONTHS_SHORT) {
            for ($i = 1; $i <= 12; $i++) {
                $months[$i] = strftime($format, mktime(0, 0, 0, $i, 1));
            }
        } else {
            $months = self::hours();
        }
        return $months;
    }

    /**
     * Returns an array of years between a starting and ending year.
     *
     * @param   int|false $start Starting year (default is current year - 5)
     * @param   int|false $end Ending year (default is current year + 5)
     * @return  array<int, string>
     */
    public static function years($start = false, $end = false): array
    {
        $start = $start === false ? (int)date('Y') - 5 : (int)$start;
        $end = $end === false ? (int)date('Y') + 5 : (int)$end;

        $years = [];
        for ($i = $start; $i <= $end; $i++) {
            $years[$i] = (string)$i;
        }

        return $years;
    }

    /**
     * Returns time difference between two timestamps, in human readable format.
     *
     * @param   int $remote Timestamp to find the span of
     * @param   int|null $local Timestamp to use as the baseline
     * @param   string $output Formatting string
     * @return  array|string   Associative list of all outputs requested
     */
    public static function span(int $remote, ?int $local = null, string $output = 'years,months,weeks,days,hours,minutes,seconds')
    {
        $output = array_flip(preg_split('/[^a-z]+/', $output) ?: []);

        $local = $local ?? time();
        $timespan = abs($remote - $local);

        if (isset($output['years'])) {
            $output['years'] = (int)floor($timespan / self::YEAR);
            $timespan %= self::YEAR;
        }

        if (isset($output['months'])) {
            $output['months'] = (int)floor($timespan / self::MONTH);
            $timespan %= self::MONTH;
        }

        if (isset($output['weeks'])) {
            $output['weeks'] = (int)floor($timespan / self::WEEK);
            $timespan %= self::WEEK;
        }

        if (isset($output['days'])) {
            $output['days'] = (int)floor($timespan / self::DAY);
            $timespan %= self::DAY;
        }

        if (isset($output['hours'])) {
            $output['hours'] = (int)floor($timespan / self::HOUR);
            $timespan %= self::HOUR;
        }

        if (isset($output['minutes'])) {
            $output['minutes'] = (int)floor($timespan / self::MINUTE);
            $timespan %= self::MINUTE;
        }

        if (isset($output['seconds'])) {
            $output['seconds'] = $timespan;
        }

        return count($output) === 1 ? array_pop($output) : $output;
    }

    /**
     * Returns the difference between a time and now in a "fuzzy" way.
     *
     * @param   int $timestamp "remote" timestamp
     * @param   int|null $local_timestamp "local" timestamp, defaults to time()
     * @return  string
     */
    public static function fuzzy_span(int $timestamp, ?int $local_timestamp = null): string
    {
        $local_timestamp = $local_timestamp ?? time();
        $offset = abs($local_timestamp - $timestamp);

        $span = match (true) {
            $offset <= self::MINUTE => 'moments',
            $offset < self::MINUTE * 20 => 'a few minutes',
            $offset < self::HOUR => 'less than an hour',
            $offset < self::HOUR * 4 => 'a couple of hours',
            $offset < self::DAY => 'less than a day',
            $offset < self::DAY * 2 => 'about a day',
            $offset < self::DAY * 4 => 'a couple of days',
            $offset < self::WEEK => 'less than a week',
            $offset < self::WEEK * 2 => 'about a week',
            $offset < self::MONTH => 'less than a month',
            $offset < self::MONTH * 2 => 'about a month',
            $offset < self::MONTH * 4 => 'a couple of months',
            $offset < self::YEAR => 'less than a year',
            $offset < self::YEAR * 2 => 'about a year',
            $offset < self::YEAR * 4 => 'a couple of years',
            $offset < self::YEAR * 8 => 'a few years',
            $offset < self::YEAR * 12 => 'about a decade',
            $offset < self::YEAR * 24 => 'a couple of decades',
            $offset < self::YEAR * 64 => 'several decades',
            default => 'a long time',
        };

        return $timestamp <= $local_timestamp ? "$span ago" : "in $span";
    }

    /**
     * Converts a UNIX timestamp to DOS format.
     *
     * @param   int|false $timestamp UNIX timestamp
     * @return  int
     */
    public static function unix2dos($timestamp = false): int
    {
        $timestamp = $timestamp === false ? getdate() : getdate($timestamp);

        if ($timestamp['year'] < 1980) {
            return (1 << 21 | 1 << 16);
        }

        $timestamp['year'] -= 1980;

        return ($timestamp['year'] << 25 | $timestamp['mon'] << 21 |
            $timestamp['mday'] << 16 | $timestamp['hours'] << 11 |
            $timestamp['minutes'] << 5 | $timestamp['seconds'] >> 1);
    }

    /**
     * Converts a DOS timestamp to UNIX format.
     *
     * @param   int|false $timestamp DOS timestamp
     * @return  int
     */
    public static function dos2unix($timestamp = false): int
    {
        $sec = 2 * ($timestamp & 0x1f);
        $min = ($timestamp >> 5) & 0x3f;
        $hrs = ($timestamp >> 11) & 0x1f;
        $day = ($timestamp >> 16) & 0x1f;
        $mon = ($timestamp >> 21) & 0x0f;
        $year = ($timestamp >> 25) & 0x7f;

        return mktime($hrs, $min, $sec, $mon, $day, $year + 1980);
    }

    /**
     * Returns a date/time string with the specified timestamp format.
     *
     * @param   string $datetime_str Datetime string
     * @param   string|null $timestamp_format Timestamp format
     * @param   string|null $timezone Timezone identifier
     * @return  string
     */
    public static function formatted_time(string $datetime_str = 'now', ?string $timestamp_format = null, ?string $timezone = null): string
    {
        $timestamp_format = $timestamp_format ?? self::$timestamp_format;
        $timezone = $timezone ?? self::$timezone;

        $tz = new DateTimeZone($timezone ?: date_default_timezone_get());
        $time = new DateTime($datetime_str, $tz);
        $time->setTimeZone($tz);

        return $time->format($timestamp_format);
    }
}
