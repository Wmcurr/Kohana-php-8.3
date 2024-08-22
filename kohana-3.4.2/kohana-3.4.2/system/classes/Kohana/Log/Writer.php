<?php
declare(strict_types=1);

/**
 * Log writer abstract class. All [Log] writers must extend this class.
 *
 * @package    Kohana
 * @category   Logging
 */
abstract class Kohana_Log_Writer
{
    /**
     * @var string Timestamp format for log entries.
     */
    public static string $timestamp;

    /**
     * @var string Timezone for log entries.
     */
    public static string $timezone;

    /**
     * Numeric log level to string lookup table.
     * @var array<int, string>
     */
    protected array $_log_levels = [
        LOG_EMERG   => 'EMERGENCY',
        LOG_ALERT   => 'ALERT',
        LOG_CRIT    => 'CRITICAL',
        LOG_ERR     => 'ERROR',
        LOG_WARNING => 'WARNING',
        LOG_NOTICE  => 'NOTICE',
        LOG_INFO    => 'INFO',
        LOG_DEBUG   => 'DEBUG',
    ];

    /**
     * @var int Level to use for stack traces.
     */
    public static int $strace_level = LOG_DEBUG;

    /**
     * Write an array of messages.
     *
     * @param array $messages
     * @return void
     */
    abstract public function write(array $messages);


    /**
     * Allows the writer to have a unique key when stored.
     *
     * @return string
     */
    final public function __toString(): string
    {
        return spl_object_hash($this);
    }

    /**
     * Formats a log entry.
     *
     * @param array $message
     * @param string $format
     * @return string
     */
    public function format_message(array $message, string $format = "time --- level: body in file:line"): string
    {
        $datetime = new DateTime('@' . $message['time']);
        $datetime->setTimezone(new DateTimeZone(static::$timezone ?? date_default_timezone_get()));
        $message['time'] = $datetime->format(static::$timestamp ?? Date::$timestamp_format);
        $message['level'] = $this->_log_levels[$message['level']];

        $string = strtr($format, array_filter($message, 'is_scalar'));

        if (isset($message['additional']['exception'])) {
            // Re-use as much as possible, just resetting the body to the trace
            $message['body'] = $message['additional']['exception']->getTraceAsString();
            $message['level'] = $this->_log_levels[static::$strace_level];

            $string .= PHP_EOL . strtr($format, array_filter($message, 'is_scalar'));
        }

        return $string;
    }

    /**
     * Logs a message with a specific level.
     *
     * @param int $level The log level (e.g., LOG_ERR, LOG_WARNING, etc.)
     * @param string $message The log message
     * @param array $context Additional context for the message
     * @return void
     */
	 
    public function log($level, string $message, array $context = []): void
    {
        $this->write([[
            'time' => time(),
            'level' => $level,
            'body' => $this->interpolate($message, $context),
            'additional' => ['context' => $context],
        ]]);
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_array($val)) {
                $val = json_encode($val);
            } elseif (!is_scalar($val)) {
                $val = '[Object]';
            }
            $replace['{' . $key . '}'] = (string) $val;
        }

        return strtr($message, $replace);
    }

    /**
     * Logs a message at the emergency level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LOG_EMERG, $message, $context);
    }

    /**
     * Logs a message at the alert level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LOG_ALERT, $message, $context);
    }

    /**
     * Logs a message at the critical level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LOG_CRIT, $message, $context);
    }

    /**
     * Logs a message at the error level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LOG_ERR, $message, $context);
    }

    /**
     * Logs a message at the warning level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LOG_WARNING, $message, $context);
    }

    /**
     * Logs a message at the notice level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LOG_NOTICE, $message, $context);
    }

    /**
     * Logs a message at the info level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LOG_INFO, $message, $context);
    }

    /**
     * Logs a message at the debug level.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LOG_DEBUG, $message, $context);
    }
}
