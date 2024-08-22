<?php
declare(strict_types=1);

/**
 * STDOUT log writer. Writes out messages to STDOUT.
 *
 * @package    Kohana
 * @category   Logging
 */
class Kohana_Log_StdOut extends Kohana_Log_Writer
{
    /**
     * Writes each of the messages to STDOUT.
     *
     * @param array $messages
     * @return void
     */
    public function write(array $messages): void
    {
        foreach ($messages as $message) {
            // Writes out each message to STDOUT
            fwrite(STDOUT, $this->format_message($message) . PHP_EOL);
        }
    }
}
