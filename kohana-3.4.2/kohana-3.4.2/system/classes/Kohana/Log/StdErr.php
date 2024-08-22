<?php
declare(strict_types=1);

/**
 * STDERR log writer. Writes out messages to STDERR.
 *
 * @package    Kohana
 * @category   Logging
 */
class Kohana_Log_StdErr extends Kohana_Log_Writer
{
    /**
     * Writes each of the messages to STDERR.
     *
     * @param array $messages
     * @return void
     */
    public function write(array $messages): void
    {
        foreach ($messages as $message) {
            // Writes out each message to STDERR
            fwrite(STDERR, $this->format_message($message) . PHP_EOL);
        }
    }
}
