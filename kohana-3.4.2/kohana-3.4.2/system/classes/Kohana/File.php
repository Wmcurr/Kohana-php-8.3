<?php

declare(strict_types=1);

/**
 * File helper class.
 *
 * @package    Kohana
 * @category   Helpers
 * @author     Kohana Team
 * @php 8.3
 */
class Kohana_File
{
    /**
     * Attempt to get the mime type from a file.
     *
     * @param   string  $filename   File name or path
     * @return  string|null Mime type on success, null on failure
     */
    public static function mime(string $filename): ?string
    {
        // Get the complete path to the file
        $filename = realpath($filename);
        if ($filename === false) {
            return null; // File does not exist
        }

        // Get the extension from the filename
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (preg_match('/^(?:jpe?g|png|[gt]if|bmp|swf)$/', $extension)) {
            // Use getimagesize() to find the mime type on images
            $file = getimagesize($filename);
            if (isset($file['mime'])) {
                return $file['mime'];
            }
        }

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($filename);
            if ($mime !== false) {
                return $mime;
            }
        }

        // Fallback to mime_content_type if available
        if (function_exists('mime_content_type')) {
            return mime_content_type($filename);
        }

        // Fallback to extension-based mime type
        return self::mime_by_ext($extension);
    }

    /**
     * Return the mime type of an extension.
     *
     * @param   string  $extension  File extension (php, pdf, txt, etc.)
     * @return  string|null Mime type on success, null on failure
     */
    public static function mime_by_ext(string $extension): ?string
    {
        // Load all of the mime types
        $mimes = Kohana::$config->load('mimes');
        return $mimes[$extension][0] ?? null;
    }

    /**
     * Lookup MIME types for a file extension.
     *
     * @param string $extension Extension to lookup
     * @return array Array of MIME types associated with the specified extension
     */
    public static function mimes_by_ext(string $extension): array
    {
        // Load all of the mime types
        $mimes = Kohana::$config->load('mimes');
        return $mimes[$extension] ?? [];
    }

    /**
     * Lookup file extensions by MIME type.
     *
     * @param   string  $type File MIME type
     * @return  array   File extensions matching MIME type
     */
    public static function exts_by_mime(string $type): array
    {
        static $types = [];

        // Fill the static array
        if (empty($types)) {
            foreach (Kohana::$config->load('mimes') as $ext => $mimes) {
                foreach ($mimes as $mime) {
                    if ($mime == 'application/octet-stream') {
                        // octet-stream is a generic binary
                        continue;
                    }

                    if (!isset($types[$mime])) {
                        $types[$mime] = [(string) $ext];
                    } elseif (!in_array($ext, $types[$mime])) {
                        $types[$mime][] = (string) $ext;
                    }
                }
            }
        }

        return $types[$type] ?? [];
    }

    /**
     * Lookup a single file extension by MIME type.
     *
     * @param   string  $type  MIME type to lookup
     * @return  string|null    First file extension matching or null
     */
    public static function ext_by_mime(string $type): ?string
    {
        $exts = self::exts_by_mime($type);
        return $exts[0] ?? null;
    }

    /**
     * Split a file into pieces matching a specific size.
     *
     * @param   string  $filename   File to be split
     * @param   int     $piece_size Size, in MB, for each piece to be
     * @return  int     The number of pieces that were created
     * @throws  Exception If file cannot be opened
     */
    public static function split(string $filename, int $piece_size = 10): int
    {
        // Open the input file
        $file = fopen($filename, 'rb');
        if ($file === false) {
            throw new Exception("Unable to open file: $filename");
        }

        // Change the piece size to bytes
        $piece_size = floor($piece_size * 1024 * 1024);

        // Write files in 8k blocks
        $block_size = 1024 * 8;

        // Total number of pieces
        $pieces = 0;

        while (!feof($file)) {
            // Create another piece
            $pieces += 1;

            // Create a new file piece
            $piece_filename = $filename . '.' . str_pad((string)$pieces, 3, '0', STR_PAD_LEFT);
            $piece = fopen($piece_filename, 'wb+');
            if ($piece === false) {
                fclose($file);
                throw new Exception("Unable to open file for writing: $piece_filename");
            }

            // Number of bytes read
            $read = 0;

            do {
                // Transfer the data in blocks
                fwrite($piece, fread($file, $block_size));

                // Another block has been read
                $read += $block_size;
            } while ($read < $piece_size);

            // Close the piece
            fclose($piece);
        }

        // Close the file
        fclose($file);

        return $pieces;
    }

    /**
     * Join a split file into a whole file.
     *
     * @param   string  $filename   Split filename, without .000 extension
     * @return  int     The number of pieces that were joined
     * @throws  Exception If file cannot be opened
     */
    public static function join(string $filename): int
    {
        // Open the file
        $file = fopen($filename, 'wb+');
        if ($file === false) {
            throw new Exception("Unable to open file: $filename");
        }

        // Read files in 8k blocks
        $block_size = 1024 * 8;

        // Total number of pieces
        $pieces = 0;

        while (is_file($piece_filename = $filename . '.' . str_pad((string)($pieces + 1), 3, '0', STR_PAD_LEFT))) {
            // Read another piece
            $pieces += 1;

            // Open the piece for reading
            $piece = fopen($piece_filename, 'rb');
            if ($piece === false) {
                fclose($file);
                throw new Exception("Unable to open piece: $piece_filename");
            }

            while (!feof($piece)) {
                // Transfer the data in blocks
                fwrite($file, fread($piece, $block_size));
            }

            // Close the piece
            fclose($piece);
        }

        // Close the main file
        fclose($file);

        return $pieces;
    }
}
