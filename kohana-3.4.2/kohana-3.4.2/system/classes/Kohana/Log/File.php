<?php
declare(strict_types=1);

/**
 * File log writer. Writes out messages and stores them in a YYYY/MM directory.
 *
 * @package    Kohana
 * @category   Logging
 */
class Kohana_Log_File extends Log_Writer
{
    /**
     * @var string Directory to place log files in
     */
    protected string $_directory;

    /**
     * Creates a new file logger. Checks that the directory exists and
     * is writable.
     *
     *     $writer = new Kohana_Log_File($directory);
     *
     * @param string $directory log directory
     * @throws RuntimeException If the directory does not exist or is not writable
     */
    public function __construct(string $directory)
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException(sprintf('Directory %s must be writable', Debug::path($directory)));
        }

        // Determine the directory path
        $this->_directory = realpath($directory) . DIRECTORY_SEPARATOR;
    }

    /**
     * Writes each of the messages into the log file. The log file will be
     * appended to the `YYYY/MM/DD.log.php` file, where YYYY is the current
     * year, MM is the current month, and DD is the current day.
     *
     *     $writer->write($messages);
     *
     * @param array $messages
     * @return void
     */
    public function write(array $messages): void
    {
        // Set the yearly directory name
        $directory = $this->_directory . date('Y');

        if (!is_dir($directory)) {
            // Create the yearly directory
            mkdir($directory, 02775, true);

            // Set permissions (must be manually set to fix umask issues)
            chmod($directory, 02775);
        }

        // Add the month to the directory
        $directory .= DIRECTORY_SEPARATOR . date('m');

        if (!is_dir($directory)) {
            // Create the monthly directory
            mkdir($directory, 02775, true);

            // Set permissions (must be manually set to fix umask issues)
            chmod($directory, 02775);
        }

        // Set the name of the log file
        $filename = $directory . DIRECTORY_SEPARATOR . date('d') . '.log.php';

        if (!file_exists($filename)) {
            // Create the log file
            file_put_contents($filename, Kohana::FILE_SECURITY . ' ?>' . PHP_EOL);

            // Allow anyone to write to log files
            chmod($filename, 0664);
        }

        foreach ($messages as $message) {
            // Write each message into the log file
            file_put_contents($filename, PHP_EOL . $this->format_message($message), FILE_APPEND);
        }
    }
}
