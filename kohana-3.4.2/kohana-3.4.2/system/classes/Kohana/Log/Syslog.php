<?php
declare(strict_types=1);

/**
 * Syslog log writer.
 *
 * @package    Kohana
 * @category   Logging
 */
class Kohana_Log_Syslog extends Kohana_Log_Writer
{
    /**
     * @var string The syslog identifier
     */
    protected string $_ident;

    /**
     * Creates a new syslog logger.
     *
     * @link http://www.php.net/manual/function.openlog
     *
     * @param string $ident syslog identifier
     * @param int $facility facility to log to
     */
    public function __construct(string $ident = 'KohanaPHP', int $facility = LOG_USER)
    {
        $this->_ident = $ident;

        // Open the connection to syslog
        openlog($this->_ident, LOG_CONS, $facility);
    }

    /**
     * Writes each of the messages into the syslog.
     *
     * @param array $messages
     * @return void
     */
    public function write(array $messages): void
    {
        foreach ($messages as $message) {
            syslog($message['level'], $message['body']);

            if (isset($message['additional']['exception'])) {
                syslog(static::$strace_level, $message['additional']['exception']->getTraceAsString());
            }
        }
    }

    /**
     * Closes the syslog connection.
     *
     * @return void
     */
    public function __destruct()
    {
        // Close connection to syslog
        closelog();
    }
}
