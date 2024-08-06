<?php

declare(strict_types=1);

/**
 * The Kohana_HTTP_Header class provides an Object-Orientated interface
 * to HTTP headers. This can parse header arrays returned from the
 * PHP functions `apache_request_headers()` or the `http_parse_headers()`
 * function available within the PECL HTTP library.
 *
 * @package    Kohana
 * @category   HTTP
 * @updated    PHP 8.3
 * @license    https://kohana.top/license
 */
class Kohana_HTTP_Header extends ArrayObject
{
    // Default Accept-* quality value if none supplied
    const DEFAULT_QUALITY = 1;

    /**
     * @var string The default protocol to use for sending headers
     */
    protected string $protocol = 'HTTP/2';

    /**
     * Sets the protocol for sending headers.
     *
     * @param string $protocol
     * @return self
     */
    public function setProtocol(string $protocol): self
    {
        $this->protocol = $protocol;
        return $this;
    }

    /**
     * Gets the protocol used for sending headers.
     *
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * Parses an Accept(-*) header and detects the quality.
     *
     * @param array $parts Accept header parts
     * @return array
     * @since 3.5.0
     */
    public static function accept_quality(array $parts): array
    {
        $parsed = [];

        // Resource light iteration
        $parts_keys = array_keys($parts);
        foreach ($parts_keys as $key) {
            $value = trim(str_replace(["\r", "\n"], '', $parts[$key]));

            $pattern = '~\b(\;\s*+)?q\s*+=\s*+([.0-9]+)~';

            // If there is no quality directive, return default
            if (!preg_match($pattern, $value, $quality)) {
                $parsed[$value] = (float) self::DEFAULT_QUALITY;
            } else {
                $quality = $quality[2];

                if ($quality[0] === '.') {
                    $quality = '0' . $quality;
                }

                // Remove the quality value from the string and apply quality
                $parsed[trim(preg_replace($pattern, '', $value, 1), '; ')] = (float) $quality;
            }
        }

        return $parsed;
    }

    /**
     * Parses the accept header to provide the correct quality values
     * for each supplied accept type.
     *
     * @link https://datatracker.ietf.org/doc/html/rfc7231#section-5.3.2
     * @param string|null $accepts Accept content header string to parse
     * @return array
     * @since 3.2.0
     */
    public static function parse_accept_header(?string $accepts = null): array
    {
        $accepts = explode(',', (string) $accepts);

        // If there is no accept, lets accept everything
        if (empty($accepts)) {
            return ['*' => ['*' => (float) self::DEFAULT_QUALITY]];
        }

        // Parse the accept header qualities
        $accepts = self::accept_quality($accepts);

        $parsed_accept = [];

        // This method of iteration uses less resource
        $keys = array_keys($accepts);
        foreach ($keys as $key) {
            // Extract the parts
            $parts = explode('/', $key, 2);

            // Invalid content type - bail
            if (!isset($parts[1])) {
                continue;
            }

            // Set the parsed output
            $parsed_accept[$parts[0]][$parts[1]] = $accepts[$key];
        }

        return $parsed_accept;
    }

    /**
     * Parses the `Accept-Charset:` HTTP header and returns an array containing
     * the charset and associated quality.
     *
     * @link https://datatracker.ietf.org/doc/html/rfc7231#section-5.3.3
     * @param string|null $charset Charset string to parse
     * @return array
     * @since 3.5.0
     */
    public static function parse_charset_header(?string $charset = null): array
    {
        if ($charset === null) {
            return ['*' => (float) self::DEFAULT_QUALITY];
        }

        return self::accept_quality(explode(',', (string) $charset));
    }

    /**
     * Parses the `Accept-Encoding:` HTTP header and returns an array containing
     * the encodings and associated quality.
     *
     * @link https://datatracker.ietf.org/doc/html/rfc7231#section-5.3.4
     * @param string|null $encoding Encoding string to parse
     * @return array
     * @since 3.5.0
     */
    public static function parse_encoding_header(?string $encoding = null): array
    {
        // Accept everything
        if ($encoding === null) {
            return ['*' => (float) self::DEFAULT_QUALITY];
        } elseif ($encoding === '') {
            return ['identity' => (float) self::DEFAULT_QUALITY];
        } else {
            return self::accept_quality(explode(',', (string) $encoding));
        }
    }

    /**
     * Parses the `Accept-Language:` HTTP header and returns an array containing
     * the languages and associated quality.
     *
     * @link https://datatracker.ietf.org/doc/html/rfc7231#section-5.3.5
     * @param string|null $language Language string to parse
     * @return array
     * @since 3.2.0
     */
    public static function parse_language_header(?string $language = null): array
    {
        if ($language === null) {
            return ['*' => ['*' => (float) self::DEFAULT_QUALITY]];
        }

        $language = self::accept_quality(explode(',', (string) $language));

        $parsed_language = [];

        $keys = array_keys($language);
        foreach ($keys as $key) {
            // Extract the parts
            $parts = explode('-', $key, 2);

            // Invalid content type - bail
            if (!isset($parts[1])) {
                $parsed_language[$parts[0]]['*'] = $language[$key];
            } else {
                // Set the parsed output
                $parsed_language[$parts[0]][$parts[1]] = $language[$key];
            }
        }

        return $parsed_language;
    }

    /**
     * Generates a Cache-Control HTTP header based on the supplied array.
     *
     * @link https://datatracker.ietf.org/doc/html/rfc7234#section-5.2
     * @param array $cache_control Cache-Control to render to string
     * @return string
     */
    public static function create_cache_control(array $cache_control): string
    {
        $parts = [];

        foreach ($cache_control as $key => $value) {
            $parts[] = is_int($key) ? $value : ($key . '=' . $value);
        }

        return implode(', ', $parts);
    }

    /**
     * Parses the Cache-Control header and returns an array representation of the Cache-Control
     * header.
     *
     * @param string $cache_control Cache-Control header
     * @return mixed
     */
    public static function parse_cache_control(string $cache_control)
    {
        $directives = explode(',', strtolower($cache_control));

        if ($directives === false) {
            return false;
        }

        $output = [];

        foreach ($directives as $directive) {
            if (strpos($directive, '=') !== false) {
                [$key, $value] = explode('=', trim($directive), 2);

                $output[$key] = ctype_digit($value) ? (int) $value : $value;
            } else {
                $output[] = trim($directive);
            }
        }

        return $output;
    }

    /**
     * @var array Accept: (content) types
     */
    protected array $_accept_content;

    /**
     * @var array Accept-Charset: parsed header
     */
    protected array $_accept_charset;

    /**
     * @var array Accept-Encoding: parsed header
     */
    protected array $_accept_encoding;

    /**
     * @var array Accept-Language: parsed header
     */
    protected array $_accept_language;

    /**
     * Constructor method for [Kohana_HTTP_Header]. Uses the standard constructor
     * of the parent `ArrayObject` class.
     *
     * @param array $input Input array
     * @param int $flags Flags
     * @param string $iterator_class The iterator class to use
     */
    public function __construct(array $input = [], int $flags = 0, string $iterator_class = 'ArrayIterator')
    {
        /**
         * @link https://datatracker.ietf.org/doc/html/rfc7230
         *
         * HTTP header declarations should be treated as case-insensitive
         */
        $input = array_change_key_case((array) $input, CASE_LOWER);

        parent::__construct($input, $flags, $iterator_class);
    }

    /**
     * Returns the header object as a string, including
     * the terminating new line.
     *
     * @return string
     */
    public function __toString(): string
    {
        $header = '';

        foreach ($this as $key => $value) {
            // Put the keys back to the Case-Convention expected
            $key = ucfirst($key);

            if (is_array($value)) {
                $header .= $key . ': ' . (implode(', ', $value)) . "\r\n";
            } else {
                $header .= $key . ': ' . $value . "\r\n";
            }
        }

        return $header . "\r\n";
    }

    /**
     * Overloads `ArrayObject::offsetSet()` to enable handling of header
     * with multiple instances of the same directive. If the `$replace` flag
     * is `false`, the header will be appended rather than replacing the
     * original setting.
     *
     * @param mixed $index Index to set `$newval` to
     * @param mixed $newval New value to set
     * @param bool $replace Replace existing value
     * @return void
     */
    public function offsetSet($index, $newval, bool $replace = true): void
    {
        // Ensure the index is lowercase
        $index = strtolower($index);

        if ($replace || !$this->offsetExists($index)) {
            parent::offsetSet($index, $newval);
            return;
        }

        $current_value = $this->offsetGet($index);

        if (is_array($current_value)) {
            $current_value[] = $newval;
        } else {
            $current_value = [$current_value, $newval];
        }

        parent::offsetSet($index, $current_value);
    }

    /**
     * Overloads the `ArrayObject::offsetExists()` method to ensure keys
     * are lowercase.
     *
     * @param string $index
     * @return bool
     */
    public function offsetExists($index): bool
    {
        return parent::offsetExists(strtolower($index));
    }

    /**
     * Overloads the `ArrayObject::offsetUnset()` method to ensure keys
     * are lowercase.
     *
     * @param string $index
     * @return void
     */
    public function offsetUnset($index): void
    {
        parent::offsetUnset(strtolower($index));
    }

    /**
     * Overloads the `ArrayObject::offsetGet()` method to ensure that all
     * keys passed to it are formatted correctly for this object.
     *
     * @param string $index Index to retrieve
     * @return mixed
     */
    public function offsetGet($index): mixed
    {
        return parent::offsetGet(strtolower($index));
    }

    /**
     * Overloads the `ArrayObject::exchangeArray()` method to ensure that
     * all keys are changed to lowercase.
     *
     * @param mixed $input
     * @return array
     */
    public function exchangeArray($input): array
    {
        /**
         * @link https://datatracker.ietf.org/doc/html/rfc7230
         *
         * HTTP header declarations should be treated as case-insensitive
         */
        $input = array_change_key_case((array) $input, CASE_LOWER);

        return parent::exchangeArray($input);
    }

    /**
     * Parses a HTTP Message header line and applies it to this HTTP_Header.
     *
     * @param resource $resource The resource (required by Curl API)
     * @param string $header_line The line from the header to parse
     * @return int
     */
    public function parse_header_string($resource, string $header_line): int
    {
        if (preg_match_all('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_line, $matches)) {
            if (!empty($matches)) {
                foreach ($matches[0] as $key => $value) {
                    $this->offsetSet($matches[1][$key], $matches[2][$key], false);
                }
            }
        }

        return strlen($header_line);
    }

    /**
     * Returns the accept quality of a submitted mime type based on the
     * request `Accept:` header. If the `$explicit` argument is `true`,
     * only precise matches will be returned, excluding all wildcard (`*`)
     * directives.
     *
     * @param string $type
     * @param bool $explicit Explicit check, excludes `*`
     * @return float
     */
    public function accepts_at_quality(string $type, bool $explicit = false): float
    {
        // Parse Accept header if required
        if ($this->_accept_content === null) {
            $accept = $this->offsetExists('accept') ? $this->offsetGet('accept') : '*/*';
            $this->_accept_content = self::parse_accept_header($accept);
        }

        // If not a real mime, try and find it in config
        if (strpos($type, '/') === false) {
            $mime = Kohana::$config->load('mimes.' . $type);

            if ($mime === null) {
                return 0.0;
            }

            $quality = 0.0;

            foreach ($mime as $_type) {
                $quality_check = $this->accepts_at_quality($_type, $explicit);
                $quality = ($quality_check > $quality) ? $quality_check : $quality;
            }

            return $quality;
        }

        $parts = explode('/', $type, 2);

        if (isset($this->_accept_content[$parts[0]][$parts[1]])) {
            return $this->_accept_content[$parts[0]][$parts[1]];
        } elseif ($explicit === true) {
            return 0.0;
        } else {
            if (isset($this->_accept_content[$parts[0]]['*'])) {
                return $this->_accept_content[$parts[0]]['*'];
            } elseif (isset($this->_accept_content['*']['*'])) {
                return $this->_accept_content['*']['*'];
            } else {
                return 0.0;
            }
        }
    }

    /**
     * Returns the preferred response content type based on the accept header
     * quality settings. If items have the same quality value, the first item
     * found in the array supplied as `$types` will be returned.
     *
     * @param array $types The content types to examine
     * @param bool $explicit Only allow explicit references, no wildcards
     * @return string|null Name of the preferred content type
     */
    public function preferred_accept(array $types, bool $explicit = false): ?string
    {
        $preferred = null;
        $ceiling = 0.0;

        foreach ($types as $type) {
            $quality = $this->accepts_at_quality($type, $explicit);

            if ($quality > $ceiling) {
                $preferred = $type;
                $ceiling = $quality;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of the supplied `$charset` argument. This method
     * will automatically parse the `Accept-Charset` header if present and
     * return the associated resolved quality value.
     *
     * @param string $charset Charset to examine
     * @return float The quality of the charset
     */
    public function accepts_charset_at_quality(string $charset): float
    {
        if ($this->_accept_charset === null) {
            $charset_header = $this->offsetExists('accept-charset') ? strtolower($this->offsetGet('accept-charset')) : null;
            $this->_accept_charset = self::parse_charset_header($charset_header);
        }

        $charset = strtolower($charset);

        if (isset($this->_accept_charset[$charset])) {
            return $this->_accept_charset[$charset];
        } elseif (isset($this->_accept_charset['*'])) {
            return $this->_accept_charset['*'];
        } elseif ($charset === 'iso-8859-1') {
            return 1.0;
        }

        return 0.0;
    }

    /**
     * Returns the preferred charset from the supplied array `$charsets` based
     * on the `Accept-Charset` header directive.
     *
     * @param array $charsets Charsets to test
     * @return string|null Preferred charset or `false`
     */
    public function preferred_charset(array $charsets): ?string
    {
        $preferred = null;
        $ceiling = 0.0;

        foreach ($charsets as $charset) {
            $quality = $this->accepts_charset_at_quality($charset);

            if ($quality > $ceiling) {
                $preferred = $charset;
                $ceiling = $quality;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of the `$encoding` type passed to it. Encoding
     * is usually compression such as `gzip`, but could be some other
     * message encoding algorithm. This method allows explicit checks to be
     * done ignoring wildcards.
     *
     * @param string $encoding Encoding type to interrogate
     * @param bool $explicit Explicit check, ignoring wildcards and `identity`
     * @return float
     */
    public function accepts_encoding_at_quality(string $encoding, bool $explicit = false): float
    {
        if ($this->_accept_encoding === null) {
            $encoding_header = $this->offsetExists('accept-encoding') ? $this->offsetGet('accept-encoding') : null;
            $this->_accept_encoding = self::parse_encoding_header($encoding_header);
        }

        // Normalize the encoding
        $encoding = strtolower($encoding);

        if (isset($this->_accept_encoding[$encoding])) {
            return $this->_accept_encoding[$encoding];
        }

        if ($explicit === false) {
            if (isset($this->_accept_encoding['*'])) {
                return $this->_accept_encoding['*'];
            } elseif ($encoding === 'identity') {
                return (float) self::DEFAULT_QUALITY;
            }
        }

        return 0.0;
    }

    /**
     * Returns the preferred message encoding type based on quality, and can
     * optionally ignore wildcard references. If two or more encodings have the
     * same quality, the first listed in `$encodings` will be returned.
     *
     * @param array $encodings Encodings to test against
     * @param bool $explicit Explicit check, if `true` wildcards are excluded
     * @return string|null
     */
    public function preferred_encoding(array $encodings, bool $explicit = false): ?string
    {
        $ceiling = 0.0;
        $preferred = null;

        foreach ($encodings as $encoding) {
            $quality = $this->accepts_encoding_at_quality($encoding, $explicit);

            if ($quality > $ceiling) {
                $ceiling = $quality;
                $preferred = $encoding;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of `$language` supplied, optionally ignoring
     * wildcards if `$explicit` is set to a non-`false` value. If the quality
     * is not found, `0.0` is returned.
     *
     * @param string $language Language to interrogate
     * @param bool $explicit Explicit interrogation, `true` ignores wildcards
     * @return float
     */
    public function accepts_language_at_quality(string $language, bool $explicit = false): float
    {
        if ($this->_accept_language === null) {
            $language_header = $this->offsetExists('accept-language') ? strtolower($this->offsetGet('accept-language')) : null;
            $this->_accept_language = self::parse_language_header($language_header);
        }

        // Normalize the language
        $language_parts = explode('-', strtolower($language), 2);

        if (isset($this->_accept_language[$language_parts[0]])) {
            if (isset($language_parts[1])) {
                if (isset($this->_accept_language[$language_parts[0]][$language_parts[1]])) {
                    return $this->_accept_language[$language_parts[0]][$language_parts[1]];
                } elseif ($explicit === false && isset($this->_accept_language[$language_parts[0]]['*'])) {
                    return $this->_accept_language[$language_parts[0]]['*'];
                }
            } elseif (isset($this->_accept_language[$language_parts[0]]['*'])) {
                return $this->_accept_language[$language_parts[0]]['*'];
            }
        }

        if ($explicit === false && isset($this->_accept_language['*'])) {
            return $this->_accept_language['*'];
        }

        return 0.0;
    }

    /**
     * Returns the preferred language from the supplied array `$languages` based
     * on the `Accept-Language` header directive.
     *
     * @param array $languages
     * @param bool $explicit
     * @return string|null
     */
    public function preferred_language(array $languages, bool $explicit = false): ?string
    {
        $ceiling = 0.0;
        $preferred = null;

        foreach ($languages as $language) {
            $quality = $this->accepts_language_at_quality($language, $explicit);

            if ($quality > $ceiling) {
                $ceiling = $quality;
                $preferred = $language;
            }
        }

        return $preferred;
    }

    /**
     * Sends headers to the PHP processor, or supplied `$callback` argument.
     * This method formats the headers correctly for output, re-instating their
     * capitalization for transmission.
     *
     * @param HTTP_Response $response Header to send
     * @param bool $replace Replace existing value
     * @param callable|null $callback Optional callback to replace PHP header function
     * @return mixed
     */
    public function send_headers(HTTP_Response $response = null, bool $replace = false, ?callable $callback = null)
    {
        $protocol = $this->protocol;
        $status = $response->status();

        // Create the response header
        $processed_headers = [$protocol . ' ' . $status . ' ' . Response::$messages[$status]];

        // Get the headers array
        $headers = $response->headers()->getArrayCopy();

        foreach ($headers as $header => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $processed_headers[] = ucfirst($header) . ': ' . $value;
        }

        if (!isset($headers['content-type'])) {
            $processed_headers[] = 'Content-Type: ' . Kohana::$content_type . '; charset=' . Kohana::$charset;
        }

        if (Kohana::$expose && !isset($headers['x-powered-by'])) {
            $processed_headers[] = 'X-Powered-By: ' . Kohana::version();
        }

        // Get the cookies and apply
        if ($cookies = $response->cookie()) {
            $processed_headers['Set-Cookie'] = $cookies;
        }

        if (is_callable($callback)) {
            // Use the callback method to set header
            return call_user_func($callback, $response, $processed_headers, $replace);
        } else {
            $this->_send_headers_to_php($processed_headers, $replace);
            return $response;
        }
    }

    /**
     * Sends the supplied headers to the PHP output buffer. If cookies
     * are included in the message they will be handled appropriately.
     *
     * @param array $headers Headers to send to PHP
     * @param bool $replace Replace existing headers
     * @return self
     */
    protected function _send_headers_to_php(array $headers, bool $replace): self
    {
        // If the headers have been sent, get out
        if (headers_sent()) {
            return $this;
        }

        foreach ($headers as $key => $line) {
            if ($key === 'Set-Cookie' && is_array($line)) {
                // Send cookies
                foreach ($line as $name => $value) {
                    Cookie::set($name, $value['value'], $value['expiration']);
                }

                continue;
            }

            header($line, $replace);
        }

        return $this;
    }
}
