<?php

declare(strict_types=1);

/**
 * URL helper class.
 *
 * [!!] You need to setup the list of trusted hosts in the `url.php` config file, before starting using this helper class.
 *
 * @package    Kohana
 * @category   Helpers
 */
class Kohana_URL
{
    /**
     * Gets the base URL to the application.
     * To specify a protocol, provide the protocol as a string or request object.
     * If a protocol is used, a complete URL will be generated using the
     * `$_SERVER['HTTP_HOST']` variable, which will be validated against RFC 952
     * and RFC 2181, as well as against the list of trusted hosts you have set
     * in the `url.php` config file.
     *
     * @param   string|Request|null $protocol Protocol string, Request object, or null
     * @param   bool                $index    Add index file to URL?
     * @return  string
     * @throws  Kohana_Exception
     */
    public static function base(string|Request|null $protocol = null, bool $index = false): string
    {
        // Start with the configured base URL
        $base_url = Kohana::$base_url;

        if ($protocol === true) {
            // Use the initial request to get the protocol
            $protocol = Request::$initial;
        }

        if ($protocol instanceof Request) {
            $protocol = $protocol->secure() ? 'https' : strtolower(explode('/', $protocol->protocol())[0]);
        }

        if ($protocol === null) {
            // Use the configured default protocol
            $protocol = parse_url($base_url, PHP_URL_SCHEME);
        }

        if ($index && !empty(Kohana::$index_file)) {
            // Add the index file to the URL
            $base_url .= Kohana::$index_file . '/';
        }

        if (is_string($protocol)) {
            $port = parse_url($base_url, PHP_URL_PORT) ? ':' . parse_url($base_url, PHP_URL_PORT) : '';

            if ($host = parse_url($base_url, PHP_URL_HOST)) {
                $base_url = parse_url($base_url, PHP_URL_PATH) ?: '';
            } else {
                $host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME']);

                if ($host && '' !== preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $host)) {
                    throw new Kohana_Exception('Invalid host :host', [':host' => $host]);
                }

                if (!static::is_trusted_host($host)) {
                    throw new Kohana_Exception('Untrusted host :host. If you trust :host, add it to the trusted hosts in the `url` config file.', [':host' => $host]);
                }
            }

            $base_url = $protocol . '://' . $host . $port . $base_url;
        }

        return $base_url;
    }

    /**
     * Fetches an absolute site URL based on a URI segment.
     *
     * @param   string          $uri        Site URI to convert
     * @param   string|Request|null $protocol   Protocol string or Request class to use protocol from
     * @param   bool            $index      Include the index_page in the URL
     * @return  string
     */
    public static function site(string $uri = '', string|Request|null $protocol = null, bool $index = true): string
    {
        $path = preg_replace('~^[-a-z0-9+.]++://[^/]++/?~', '', trim($uri, '/'));

        if (!UTF8::is_ascii($path)) {
            $path = preg_replace_callback('~([^/]+)~', [self::class, '_rawurlencode_callback'], $path);
        }

        return URL::base($protocol, $index) . $path;
    }

    /**
     * Callback used for encoding all non-ASCII characters, as per RFC 1738
     * Used by URL::site()
     *
     * @param  array $matches  Array of matches from preg_replace_callback()
     * @return string          Encoded string
     */
    protected static function _rawurlencode_callback(array $matches): string
    {
        return rawurlencode($matches[0]);
    }

    /**
     * Merges the current GET parameters with an array of new or overloaded
     * parameters and returns the resulting query string.
     *
     * @param   array|null $params   Array of GET parameters
     * @param   bool       $use_get  Include current request GET parameters
     * @return  string
     */
    public static function query(array $params = null, bool $use_get = true): string
    {
        if ($use_get) {
            $params = $params === null ? $_GET : array_merge($_GET, $params);
        }

        return empty($params) ? '' : '?' . http_build_query($params, '', '&');
    }

    /**
     * Convert a phrase to a URL-safe title.
     *
     * @param   string   $title       Phrase to convert
     * @param   string   $separator   Word separator (any single character)
     * @param   bool     $ascii_only  Transliterate to ASCII?
     * @return  string
     */
    public static function title(string $title, string $separator = '-', bool $ascii_only = false): string
    {
        if ($ascii_only) {
            $title = UTF8::transliterate_to_ascii($title);
            $title = preg_replace('![^' . preg_quote($separator) . 'a-z0-9\s]+!', '', strtolower($title));
        } else {
            $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', UTF8::strtolower($title));
        }

        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Test if given $host should be trusted.
     *
     * Tests against given $trusted_hosts
     * or looks for key `trusted_hosts` in `url` config
     *
     * @param string $host
     * @param array|null $trusted_hosts
     * @return bool true if $host is trustworthy.
     */
    public static function is_trusted_host(string $host, array $trusted_hosts = null): bool
    {
        if (empty($trusted_hosts)) {
            $trusted_hosts = (array) Kohana::$config->load('url')->get('trusted_hosts');
        }

        foreach ($trusted_hosts as $trusted_host) {
            if (preg_match('#^' . $trusted_host . '$#uD', $host)) {
                return true;
            }
        }

        return false;
    }
}
