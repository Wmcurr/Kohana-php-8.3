<?php

declare(strict_types=1);

/**
 * Contains the most low-level helpers methods in Kohana:
 *
 * - Environment initialization
 * - Locating files within the cascading filesystem
 * - Auto-loading and transparent extension of classes
 * - Variable and path debugging
 *
 * @package    Kohana
 * @category   HTTP
 * @updated    PHP 8.3
 * @license    https://kohana.top/license
 */
abstract class Kohana_HTTP
{
    /**
     * @var string The default protocol to use if it cannot be detected
     */
    public static string $protocol = 'HTTP/2';

    /**
     * List of supported protocols
     *
     * @var array
     */
    private static array $supportedProtocols = ['HTTP/1.0', 'HTTP/1.1', 'HTTP/2', 'HTTP/3'];

    /**
     * Issues a HTTP redirect.
     *
     * @param string $uri URI to redirect to
     * @param int $code HTTP Status code to use for the redirect
     * @throws HTTP_Exception
     */
    public static function redirect(string $uri = '', int $code = 302): void
    {
        $protocol = self::get_protocol();
        $locationHeader = $protocol === 'HTTP/2' || $protocol === 'HTTP/3' ? ':status' : 'Location';

        $e = HTTP_Exception::factory($code);

        if (!$e instanceof HTTP_Exception_Redirect) {
            throw new Kohana_Exception('Invalid redirect code \':code\'', [':code' => $code]);
        }

        throw $e->location($uri)->headers($locationHeader, $uri);
    }

    /**
     * Checks the browser cache to see if the response needs to be returned,
     * execution will halt and a 304 Not Modified will be sent if the
     * browser cache is up to date.
     *
     * @param Request $request Request
     * @param Response $response Response
     * @param string|null $etag Resource ETag
     * @throws HTTP_Exception_304
     * @return Response
     */
    public static function check_cache(Request $request, Response $response, ?string $etag = null): Response
    {
        // Generate an etag if necessary
        if ($etag === null) {
            $etag = $response->generate_etag();
        }

        // Set the ETag header
        $response->headers('etag', $etag);

        // Add the Cache-Control header if it is not already set
        // This allows etags to be used with max-age, etc
        if ($response->headers('cache-control')) {
            $response->headers('cache-control', $response->headers('cache-control') . ', must-revalidate');
        } else {
            $response->headers('cache-control', 'must-revalidate');
        }

        // Check if we have a matching etag
        if ($request->headers('if-none-match') && (string) $request->headers('if-none-match') === $etag) {
            // No need to send data again
            throw HTTP_Exception::factory(304)->headers('etag', $etag);
        }

        return $response;
    }

    /**
     * Parses a HTTP header string into an associative array
     *
     * @param string $header_string Header string to parse
     * @return HTTP_Header
     */
    public static function parse_header_string(string $header_string): HTTP_Header
    {
        // If the PECL HTTP extension is loaded
        if (extension_loaded('http')) {
            // Use the fast method to parse header string
            $headers = version_compare(phpversion('http'), '2.0.0', '>=') ?
                \http\Header::parse($header_string) :
                http_parse_headers($header_string);
            return new HTTP_Header($headers);
        }

        // Otherwise we use the slower PHP parsing
        $headers = [];

        // Match all HTTP headers
        if (preg_match_all('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_string, $matches)) {
            // Parse each matched header
            foreach ($matches[0] as $key => $value) {
                // If the header has not already been set
                if (!isset($headers[$matches[1][$key]])) {
                    // Apply the header directly
                    $headers[$matches[1][$key]] = $matches[2][$key];
                } else {
                    // Otherwise there is an existing entry
                    if (is_array($headers[$matches[1][$key]])) {
                        // Apply the new entry to the array
                        $headers[$matches[1][$key]][] = $matches[2][$key];
                    } else {
                        // Otherwise create a new array with the entries
                        $headers[$matches[1][$key]] = [
                            $headers[$matches[1][$key]],
                            $matches[2][$key],
                        ];
                    }
                }
            }
        }

        // Return the headers
        return new HTTP_Header($headers);
    }

    /**
     * Parses the HTTP request headers and returns an array containing
     * key-value pairs. This method is slow, but provides an accurate
     * representation of the HTTP request.
     *
     * @return HTTP_Header
     */
    public static function request_headers(): HTTP_Header
    {
        // If running on apache server
        if (function_exists('apache_request_headers')) {
            // Return the much faster method
            return new HTTP_Header(apache_request_headers());
        } elseif (extension_loaded('http')) {
            // Return the much faster method
            $headers = version_compare(phpversion('http'), '2.0.0', '>=') ?
                \http\Env::getRequestHeader() :
                http_get_request_headers();
            return new HTTP_Header($headers);
        }

        // Setup the output
        $headers = [];

        // Parse the content type
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        // Parse the content length
        if (!empty($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        foreach ($_SERVER as $key => $value) {
            // If there is no HTTP header here, skip
            if (strpos($key, 'HTTP_') !== 0) {
                continue;
            }

            // This is a dirty hack to ensure HTTP_X_FOO_BAR becomes X-FOO-BAR
            $headers[str_replace('_', '-', substr($key, 5))] = $value;
        }

        return new HTTP_Header($headers);
    }

    /**
     * Processes an array of key value pairs and encodes
     * the values to meet RFC 3986
     *
     * @param array $params Params
     * @return string
     */
    public static function www_form_urlencode(array $params = []): string
    {
        if (empty($params)) {
            return '';
        }

        $encoded = [];

        foreach ($params as $key => $value) {
            $encoded[] = $key . '=' . rawurlencode($value);
        }

        return implode('&', $encoded);
    }

    /**
     * Determine the HTTP protocol being used.
     *
     * @return string
     */
    public static function get_protocol(): string
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? self::$protocol;
        if (in_array($protocol, self::$supportedProtocols, true)) {
            return $protocol;
        }
        return self::$protocol;
    }
}
