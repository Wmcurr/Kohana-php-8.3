<?php
declare(strict_types=1);

/**
 * URL helper class for managing and generating URLs in the Kohana framework.
 * This class provides utilities such as creating base URLs, generating
 * signed URLs, canonicalizing URLs, and managing trusted hosts.
 *
 * @package    Kohana
 * @category   Helpers
 */
class Kohana_URL
{
    /** @var string|null Default protocol to be used in URLs */
    public static ?string $default_protocol = 'https://';

    /** @var array List of trusted localhost entries */
    public static array $localhosts = [false, '', 'local', 'localhost'];

    /** @var array Cache for storing generated URLs */
    protected static array $cache = [];

    /**
     * Returns the base URL of the application, optionally with a specified protocol
     * and index file if required.
     *
     * @param string|Request|null $protocol Protocol string or Request instance (e.g., "https")
     * @param bool                $index    Whether to include the index file in the URL
     * @return string                       The generated base URL
     * @throws Kohana_Exception             If an untrusted host is detected
     */
    public static function base(string|Request|null $protocol = null, bool $index = false): string {
        $cache_key = md5(serialize([$protocol, $index]));

        // Return cached URL if it exists
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $base_url = Kohana::$base_url;

        if ($protocol === true) {
            $protocol = Request::$initial;
        }

        if ($protocol instanceof Request) {
            $protocol = $protocol->secure() ? 'https' : strtolower(explode('/', $protocol->protocol())[0]);
        }

        // If no specific protocol is set, default to the one in the base URL
        if ($protocol === null) {
            $protocol = parse_url($base_url, PHP_URL_SCHEME);
        }

        if ($index && !empty(Kohana::$index_file)) {
            $base_url .= Kohana::$index_file . '/';
        }

        if (is_string($protocol)) {
            $port = parse_url($base_url, PHP_URL_PORT) ? ':' . parse_url($base_url, PHP_URL_PORT) : '';
            $host = parse_url($base_url, PHP_URL_HOST) ?: strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME']);

            if (!self::is_trusted_host($host)) {
                Kohana::$log->add(Log::WARNING, "Untrusted host access attempt: :host", [':host' => $host]);
                throw new Kohana_Exception('Untrusted host :host. Add it to trusted hosts in the `url` config file.', [':host' => $host]);
            }

            $base_url = $protocol . '://' . $host . $port . $base_url;
        }

        return self::$cache[$cache_key] = $base_url;
    }

    /**
     * Retrieves or generates an HMAC key for URL signing.
     * 
     * @return string HMAC key
     */
    protected static function getHmacKey(): string
    {
        $config = Kohana::$config->load('url');
        $hmacKey = $config->get('hmac_key');

        // Generate and save the HMAC key if it is not set
        if (!$hmacKey) {
            $hmacKey = bin2hex(random_bytes(32)); // Generate a 256-bit key
            
            // Get the path to the configuration file
            $config_file = SYSPATH . 'config/url.php';
            
            // Create the config directory if it does not exist
            if (!is_dir(dirname($config_file))) {
                mkdir(dirname($config_file), 0755, true);
            }

            // Load the existing config or create a new array
            $current_config = file_exists($config_file) ? include($config_file) : [];
            
            // Update the configuration array
            $current_config['hmac_key'] = $hmacKey;
            
            // Write the updated config
            $config_content = "<?php\nreturn " . var_export($current_config, true) . ";\n";
            if (file_put_contents($config_file, $config_content) === false) {
                throw new Kohana_Exception('Failed to save HMAC key to the configuration file');
            }

            // Update the config in memory
            $config->set('hmac_key', $hmacKey);
        }

        return $hmacKey;
    } 

    /**
     * Generates a signed URL with an HMAC signature for security.
     *
     * @param string $uri        The URI segment
     * @param array  $params     Query parameters
     * @param int    $expiration Expiration time in seconds
     * @return string            The signed URL
     */
    public static function signedUrl(string $uri, array $params = [], int $expiration = 0): string {
        $params['expires'] = $expiration > 0 ? time() + $expiration : 0;

        // Sort parameters by keys
        ksort($params);

        // Build the query string using PHP_QUERY_RFC3986 for proper encoding
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $hmacKey = self::getHmacKey();

        // Generate the signature
        $signature = hash_hmac('sha256', $uri . '?' . $queryString, $hmacKey);
        $params['signature'] = $signature;

        // Update the query string with the signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // Create the full URL
        $url = $uri . '?' . $queryString;

        return $url;
    }


    /**
     * Validates a signed URL by verifying the HMAC signature and expiration time.
     *
     * @param string $url The signed URL
     * @return bool       True if the URL is valid
     */
    public static function validateSignedUrl(string $url): bool {
        $parsed_url = parse_url($url);

        // Check if the query component exists
        if (!isset($parsed_url['query'])) {
            return false;
        }

        parse_str($parsed_url['query'], $query_params);

        $signature = $query_params['signature'] ?? null;
        if ($signature === null) {
            return false;
        }

        // Remove the signature from parameters for verification
        unset($query_params['signature']);

        // Sort parameters by keys
        ksort($query_params);

        // Build the query string using PHP_QUERY_RFC3986 for proper encoding
        $queryString = http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);

        $hmacKey = self::getHmacKey();

        // Generate the expected signature
        $expected_signature = hash_hmac('sha256', $parsed_url['path'] . '?' . $queryString, $hmacKey);

        // Verify that the signatures match
        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }

        // Check the expiration time
        if ($query_params['expires'] != 0 && $query_params['expires'] < time()) {
            return false;
        }

        return true;
    }

    /**
     * Converts a URL to its canonical form.
     *
     * @param string $uri The URI to canonicalize
     * @return string     The canonicalized URL
     */
    public static function canonicalize(string $uri): string {
        $canonical = strtolower(trim($uri, '/'));
        $canonical = preg_replace('#/+#', '/', $canonical);
        return self::base(true) . '/' . $canonical;
    }

    /**
     * Generates an absolute site URL.
     *
     * @param string          $uri      The URI segment
     * @param string|Request|null $protocol The protocol or Request instance
     * @param bool            $index    Include the index file
     * @return string                   The absolute site URL
     */
    public static function site(string $uri = '', string|Request|null $protocol = null, bool $index = true): string {
        $path = preg_replace('~^[-a-z0-9+.]++://[^/]++/?~', '', trim($uri, '/'));

        if (!UTF8::is_ascii($path)) {
            $path = preg_replace_callback('~([^/]+)~', [self::class, '_rawurlencode_callback'], $path);
        }

        return URL::base($protocol, $index) . $path;
    }

    /**
     * Callback for encoding non-ASCII characters in URL paths.
     *
     * @param  array  $matches Matches from preg_replace_callback
     * @return string          Encoded string
     */
    protected static function _rawurlencode_callback(array $matches): string {
        return rawurlencode($matches[0]);
    }

    /**
     * Generates a query string by merging current GET parameters.
     *
     * @param array|null $params  Additional query parameters
     * @param bool       $use_get Whether to include current GET parameters
     * @return string             The generated query string
     */
    public static function query(array $params = null, bool $use_get = true): string {
        $params = $use_get ? array_merge($_GET, $params ?? []) : $params ?? [];
        return empty($params) ? '' : '?' . http_build_query($params, '', '&');
    }

    /**
     * Converts a phrase to a URL-safe title.
     *
     * @param string $title      Phrase to convert
     * @param string $separator  Word separator (e.g., "-")
     * @param bool   $ascii_only Whether to transliterate to ASCII
     * @return string            The URL-safe title
     */
    public static function title(string $title, string $separator = '-', bool $ascii_only = false): string {
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
     * Verifies if a given host is part of the trusted hosts.
     *
     * @param string $host          Host to verify
     * @param array|null $trusted_hosts Optional array of trusted hosts
     * @return bool                 True if host is trusted, otherwise false
     */
    public static function is_trusted_host(string $host, array $trusted_hosts = null): bool {
        $trusted_hosts = $trusted_hosts ?? (array) Kohana::$config->load('url')->get('trusted_hosts');

        foreach ($trusted_hosts as $trusted_host) {
            $trusted_host = str_replace('\*', '.*', preg_quote($trusted_host, '#'));
            if (preg_match('#^' . $trusted_host . '$#uD', $host)) {
                return true;
            }
        }

        return false;
    }
}
