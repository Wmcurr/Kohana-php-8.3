<?php

declare(strict_types=1);

/**
 * Contains the most low-level helper methods in Kohana:
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
        if ($response->headers('cache-control')) {
            $cacheControl = $response->headers('cache-control');
            if (stripos($cacheControl, 'must-revalidate') === false) {
                $response->headers('cache-control', $cacheControl . ', must-revalidate');
            }
        } else {
            $response->headers('cache-control', 'must-revalidate');
        }

        // Check if we have a matching etag
        $ifNoneMatch = $request->headers('if-none-match');
        if ($ifNoneMatch && str_contains((string)$ifNoneMatch, $etag)) {
            // No need to send data again
            throw HTTP_Exception::factory(304)->headers('etag', $etag);
        }

        return $response;
    }

    /**
     * Parses a HTTP header string into an associative array.
     *
     * @param string $header_string Header string to parse
     * @return HTTP_Header
     */
    public static function parse_header_string(string $header_string): HTTP_Header
    {
        // Use PECL HTTP extension if available for better performance
        if (extension_loaded('http')) {
            $headers = version_compare(phpversion('http'), '2.0.0', '>=') ?
                \http\Header::parse($header_string) :
                http_parse_headers($header_string);
            return new HTTP_Header($headers);
        }

        // Otherwise, use regular expressions for manual parsing
        $headers = [];
        if (preg_match_all('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_string, $matches)) {
            foreach ($matches[1] as $key => $header) {
                $headerName = strtolower($header);
                $headerValue = trim($matches[2][$key]);

                // If header exists, convert to an array of values
                if (!isset($headers[$headerName])) {
                    $headers[$headerName] = $headerValue;
                } else {
                    if (is_array($headers[$headerName])) {
                        $headers[$headerName][] = $headerValue;
                    } else {
                        $headers[$headerName] = [$headers[$headerName], $headerValue];
                    }
                }
            }
        }

        return new HTTP_Header($headers);
    }

    /**
     * Parses the HTTP request headers and returns an array containing
     * key-value pairs.
     *
     * @return HTTP_Header
     */
    public static function request_headers(): HTTP_Header
    {
        // Use PECL HTTP or Apache request headers if available
        if (function_exists('getallheaders')) {
            return new HTTP_Header(getallheaders());
        } elseif (extension_loaded('http')) {
            $headers = version_compare(phpversion('http'), '2.0.0', '>=') ?
                \http\Env::getRequestHeader() :
                http_get_request_headers();
            return new HTTP_Header($headers);
        }

        // Otherwise, manually parse headers from $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (stripos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $header = str_replace('_', '-', $key);
                $headers[$header] = $value;
            }
        }

        return new HTTP_Header($headers);
    }

    /**
     * Processes an array of key value pairs and encodes
     * the values to meet RFC 3986.
     *
     * @param array $params Params
     * @return string
     */
    public static function www_form_urlencode(array $params = []): string
    {
        // Use built-in function for consistency with RFC 3986
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Determine the HTTP protocol being used.
     *
     * @return string
     */
    public static function get_protocol(): string
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? self::$protocol;
        $protocol = strtoupper($protocol);

        // Sanitize protocol to strip minor versions
        $protocolVersion = preg_replace('/(\.\d+)?$/', '', substr($protocol, 5));
        $protocol = 'HTTP/' . $protocolVersion;

        if (in_array($protocol, self::$supportedProtocols, true)) {
            return $protocol;
        }

        return self::$protocol;
    }
}
