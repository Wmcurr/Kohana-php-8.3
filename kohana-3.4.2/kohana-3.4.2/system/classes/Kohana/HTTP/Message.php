<?php

declare(strict_types=1); // Enabling strict typing mode

/**
 * The HTTP Interaction interface providing the core HTTP methods that
 * should be implemented by any HTTP request or response class.
 *
 * @package    Kohana
 * @category   HTTP
 * @version    PHP 8.3
 * @since      3.5.0
 * @license    https://kohana.top/license
 */
interface Kohana_HTTP_Message
{
    /**
     * Gets or sets the HTTP protocol. The standard protocol to use
     * is `HTTP/1.1`.
     *
     * @param   string|null  $protocol  Protocol to set to the request/response, or null to get the current protocol
     * @return  mixed  Returns the current protocol if no argument is given, otherwise sets the protocol
     */
    public function protocol($protocol = null);

    /**
     * Gets or sets HTTP headers to the request or response. All headers
     * are included immediately after the HTTP protocol definition during
     * transmission. This method provides a simple array or key/value
     * interface to the headers.
     *
     * @param   mixed  $key    Key or array of key/value pairs to set, or null to get all headers
     * @param   string|null  $value  Value to set to the supplied key, ignored if key is an array or null
     * @return  mixed
     */
    public function headers($key = null, $value = null);

    /**
     * Gets or sets the HTTP body to the request or response. The body is
     * included after the header, separated by a single empty new line.
     *
     * @param   string|null  $content  Content to set to the object, or null to get the current body content
     * @return  mixed  Returns the current body content if no argument is given, otherwise sets the body content
     */
    public function body($content = null);

    /**
     * Renders the HTTP_Interaction to a string, producing
     *
     *  - Protocol
     *  - Headers
     *  - Body
     *
     * @return  string
     */
    public function render();
}
