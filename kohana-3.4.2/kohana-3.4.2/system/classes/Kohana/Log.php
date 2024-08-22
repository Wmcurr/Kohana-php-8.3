<?php

/**
 * Message logging with observer-based log writing.
 *
 * [!!] This class does not support extensions, only additional writers.
 *
 * @package    Kohana
 * @category   Logging
 * @author     Kohana Team
 * @copyright  (c) 2024 Kohana Team
 * @license    https://kohana.top/license
 */
declare(strict_types=1);

class Kohana_Log
{
    // Log message levels - Windows users see PHP Bug #18090
    public const EMERGENCY = LOG_EMERG;    // 0
    public const ALERT = LOG_ALERT;        // 1
    public const CRITICAL = LOG_CRIT;      // 2
    public const ERROR = LOG_ERR;          // 3
    public const WARNING = LOG_WARNING;    // 4
    public const NOTICE = LOG_NOTICE;      // 5
    public const INFO = LOG_INFO;          // 6
    public const DEBUG = LOG_DEBUG;        // 7

    /**
     * @var bool immediately write when logs are added
     */
    public static bool $write_on_add = false;

    /**
     * @var self|null Singleton instance container
     */
    protected static ?self $_instance = null;

    /**
     * Get the singleton instance of this class and enable writing at shutdown.
     *
     * @return  self
     */
    public static function instance()
    {
        if (Log::$_instance === null) {
            // Create a new instance
            Log::$_instance = new Log;

            // Write the logs at shutdown
            register_shutdown_function([Log::$_instance, 'write']);
        }

        return Log::$_instance;
    }

    /**
     * @var array list of added messages
     */
    protected array $_messages = [];

    /**
     * @var array list of log writers
     */
    protected array $_writers = [];

    /**
     * Attaches a log writer, and optionally limits the levels of messages that
     * will be written by the writer.
     *
     * @param   Log_Writer  $writer     instance
     * @param   array|int   $levels     array of messages levels to write OR max level to write
     * @param   int         $min_level  min level to write IF $levels is not an array
     * @return  self
     */
    public function attach(Log_Writer $writer, array|int $levels = [], int $min_level = 0): self
    {
        if (!is_array($levels)) {
            $levels = range($min_level, $levels);
        }

        $this->_writers[spl_object_hash($writer)] = [
            'object' => $writer,
            'levels' => $levels
        ];

        return $this;
    }

    /**
     * Detaches a log writer. The same writer object must be used.
     *
     * @param   Log_Writer  $writer instance
     * @return  self
     */
    public function detach(Log_Writer $writer): self
    {
        // Remove the writer
        unset($this->_writers[spl_object_hash($writer)]);

        return $this;
    }

    /**
     * Adds a message to the log. Replacement values must be passed in to be
     * replaced using [strtr](http://php.net/strtr).
     *
     * @param   string  $level       level of message
     * @param   string  $message     message body
     * @param   array|null   $values      values to replace in the message
     * @param   array|null   $additional  additional custom parameters to supply to the log writer
     * @return  self
     */
	 public function add(int|string $level, string $message, ?array $values = null, ?array $additional = null): self
    {
        if ($values) {
            // Insert the values into the message
            $message = strtr($message, $values);
        }

        // Grab a copy of the trace
        if (isset($additional['exception'])) {
            $trace = $additional['exception']->getTrace();
        } else {
            // Older php version don't have 'DEBUG_BACKTRACE_IGNORE_ARGS', so manually remove the args from the backtrace
            if (!defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
                $trace = array_map(function ($item) {
                    unset($item['args']);
                    return $item;
                }, array_slice(debug_backtrace(false), 1));
            } else {
                $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1);
            }
        }

        $this->_messages[] = [
            'time' => (new DateTime())->getTimestamp(),
            'level' => $level,
            'body' => $message,
            'trace' => $trace,
            'file' => $trace[0]['file'] ?? null,
            'line' => $trace[0]['line'] ?? null,
            'class' => $trace[0]['class'] ?? null,
            'function' => $trace[0]['function'] ?? null,
            'additional' => $additional ?? [],
        ];

        if (self::$write_on_add) {
            // Write logs as they are added
            $this->write();
        }

        return $this;
    }

    /**
     * Write and clear all of the messages.
     *
     * @return  void
     */
    public function write(): void
    {
        if (empty($this->_messages)) {
            // There is nothing to write, move along
            return;
        }

        // Import all messages locally
        $messages = $this->_messages;

        // Reset the messages array
        $this->_messages = [];

        foreach ($this->_writers as $writer) {
            if (empty($writer['levels'])) {
                // Write all of the messages
                $writer['object']->write($messages);
            } else {
                // Filtered messages
                $filtered = array_filter($messages, fn($message) => in_array($message['level'], $writer['levels']));

                // Write the filtered messages
                $writer['object']->write($filtered);
            }
        }
    }
}
