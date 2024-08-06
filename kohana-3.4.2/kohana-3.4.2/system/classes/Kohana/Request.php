<?php
declare(strict_types=1); // Enabling strict typing mode
/**
 * Request. Uses the [Route] class to determine what
 * [Controller] to send the request to.
 *
 * @package    Kohana
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2024 Kohana Team
 * @license    https://kohana.top/license
 */
class Kohana_Request implements HTTP_Request
{
    /**
     * @var  string  client user agent
     */
    public static string $user_agent = '';

    /**
     * @var  string  client IP address
     */
    public static string $client_ip = '0.0.0.0';

    /**
     * @var  string  trusted proxy server IPs
     */
    public static array $trusted_proxies = ['127.0.0.1', 'localhost', 'localhost.localdomain'];

    /**
     * @var  Request|null  main request instance
     */
    public static ?Request $initial = null;

    /**
     * @var  Request|null  currently executing request instance
     */
    public static ?Request $current = null;

    /**
     * Creates a new request object for the given URI. New requests should be
     * created using the [Request::factory] method.
     *
     * This method handles the creation of the initial request as well as
     * subsequent requests. It collects various information from the server
     * environment, including protocol, method, security status, client IP,
     * and more.
     *
     * The method has been updated for PHP 8.3 compatibility, including:
     * - Use of typed parameters and return type
     * - Improved handling of HTTPS detection
     * - Use of null coalescing operator for safer array access
     * - Use of match expression for more concise conditional logic
     * - Simplified approach to reading request body
     *
     * @param   bool|string    $uri               URI of the request or true for automatic detection
     * @param   array          $client_params     An array of params to pass to the request client
     * @param   bool           $allow_external    Allow external requests? (deprecated in 3.3)
     * @param   array          $injected_routes   An array of routes to use, for testing
     * @return  Request
     * @throws  Request_Exception
     * @uses    Route::all
     * @uses    Route::matches
     */
    public static function factory(
        bool|string $uri = true,
        array $client_params = [],
        bool $allow_external = true,
        array $injected_routes = []
    ): Request {
        if (!Request::$initial) {
            $protocol = HTTP::$protocol;
            $method = $_SERVER['REQUEST_METHOD'] ?? HTTP_Request::GET;

            $secure = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                && in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies, true);

            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            Request::$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;

            Request::$client_ip = match (true) {
                isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
                in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies, true) =>
                    explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0],
                
                isset($_SERVER['HTTP_CLIENT_IP']) &&
                in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies, true) =>
                    explode(',', $_SERVER['HTTP_CLIENT_IP'])[0],
                
                isset($_SERVER['REMOTE_ADDR']) => $_SERVER['REMOTE_ADDR'],
                
                default => '0.0.0.0'
            };

            $body = $method !== HTTP_Request::GET ? file_get_contents('php://input') ?: null : null;

            if ($uri === true) {
                $uri = Request::detect_uri();
            }

            $cookies = array_map(fn($key) => Cookie::get($key), array_keys($_COOKIE));

            Request::$initial = $request = new Request($uri, $client_params, $allow_external, $injected_routes);

            $request->protocol($protocol)
                ->query($_GET)
                ->post($_POST);

            if (isset($secure)) {
                $request->secure($secure);
            }

            if (isset($method)) {
                $request->method($method);
            }

            if (isset($referrer)) {
                $request->referrer($referrer);
            }

            if (isset($requested_with)) {
                $request->requested_with($requested_with);
            }

            if (isset($body)) {
                $request->body($body);
            }

            if (!empty($cookies)) {
                $request->cookie($cookies);
            }
        } else {
            $request = new Request($uri, $client_params, $allow_external, $injected_routes);
        }

        return $request;
    }

    /**
     * Automatically detects the URI of the main request using PATH_INFO,
     * REQUEST_URI, PHP_SELF or REDIRECT_URL.
     *
     * @return  string  URI of the main request
     * @throws  Kohana_Exception
     * @since   3.0.8
     */
    public static function detect_uri(): string
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            $uri = $_SERVER['PATH_INFO'];
        } else {
            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = $_SERVER['REQUEST_URI'];

                if ($request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
                    $uri = $request_uri;
                }

                $uri = rawurldecode($uri);
            } elseif (isset($_SERVER['PHP_SELF'])) {
                $uri = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['REDIRECT_URL'])) {
                $uri = $_SERVER['REDIRECT_URL'];
            } else {
                throw new Kohana_Exception('Unable to detect the URI using PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
            }

            $base_url = parse_url(Kohana::$base_url, PHP_URL_PATH);

            if (strpos($uri, $base_url) === 0) {
                $uri = (string) substr($uri, strlen($base_url));
            }

            if (Kohana::$index_file AND strpos($uri, Kohana::$index_file) === 0) {
                $uri = (string) substr($uri, strlen(Kohana::$index_file));
            }
        }

        return $uri;
    }

    /**
     * Return the currently executing request. This is changed to the current
     * request when [Request::execute] is called and restored when the request
     * is completed.
     *
     * @return  Request|null
     * @since   3.0.5
     */
    public static function current(): ?Request
    {
        return Request::$current;
    }

    /**
     * Returns the first request encountered by this framework. This will should
     * only be set once during the first [Request::factory] invocation.
     *
     * @return  Request|null
     * @since   3.1.0
     */
    public static function initial(): ?Request
    {
        return Request::$initial;
    }

    /**
     * Returns information about the initial user agent.
     *
     * @param   string  $value  Information to return: browser, version, robot, mobile, platform
     * @return  string|bool     Requested information, false if nothing is found
     */
    public static function user_agent(string $value): string|bool
    {
        return Text::user_agent(Request::$user_agent, $value);
    }

    /**
     * Determines if a file larger than the post_max_size has been uploaded.
     * PHP does not handle this situation gracefully on its own, so this method
     * helps to solve that problem.
     *
     * @return  bool
     */
    public static function post_max_size_exceeded(): bool
    {
        if (Request::$initial?->method() !== HTTP_Request::POST) {
            return false;
        }

        $max_bytes = Num::bytes(ini_get('post_max_size'));

        return (Arr::get($_SERVER, 'CONTENT_LENGTH') > $max_bytes);
    }

    /**
     * Process a request to find a matching route
     *
     * @param   Request  $request  Request
     * @param   array|null  $routes  Routes
     * @return  array|null
     */
    public static function process(Request $request, ?array $routes = null): ?array
    {
        $routes = $routes ?: Route::all();

        foreach ($routes as $name => $route) {
            if ($route->is_external()) {
                continue;
            }

            if ($params = $route->matches($request)) {
                return [
                    'params' => $params,
                    'route' => $route,
                ];
            }
        }

        return null;
    }

    /**
     * Parses an accept header and returns an array (type => quality) of the
     * accepted types, ordered by quality.
     *
     * @param   string  $header   Header to parse
     * @param   array   $accepts  Default values
     * @return  array
     */
    protected static function _parse_accept(string $header, array $accepts = []): array
    {
        if (!empty($header)) {
            $types = explode(',', $header);

            foreach ($types as $type) {
                $parts = explode(';', $type);
                $type = trim(array_shift($parts));

                $quality = 1.0;

                foreach ($parts as $part) {
                    if (strpos($part, '=') === false) {
                        continue;
                    }

                    [$key, $value] = explode('=', trim($part));

                    if ($key === 'q') {
                        $quality = (float) trim($value);
                    }
                }

                $accepts[$type] = $quality;
            }
        }

        arsort($accepts);

        return $accepts;
    }

    /**
     * @var  string|null  the x-requested-with header which most likely
     *                    will be xmlhttprequest
     */
    protected ?string $_requested_with = null;

    /**
     * @var  string  method: GET, POST, PUT, DELETE, HEAD, etc
     */
    protected string $_method = 'GET';

    /**
     * @var  string|null  protocol: HTTP/1.1, FTP, CLI, etc
     */
    protected ?string $_protocol = null;

    /**
     * @var  bool  secure connection
     */
    protected bool $_secure = false;

    /**
     * @var  string|null  referring URL
     */
    protected ?string $_referrer = null;

    /**
     * @var  Route|null  route matched for this request
     */
    protected ?Route $_route = null;

    /**
     * @var  array  routes to manually look at instead of the global namespace
     */
    protected array $_routes;

    /**
     * @var  HTTP_Header  headers to send as part of the request
     */
    protected HTTP_Header $_header;

    /**
     * @var  string|null  request body
     */
    protected ?string $_body = null;

    /**
     * @var  string  controller directory
     */
    protected string $_directory = '';

    /**
     * @var  string|null  controller to be executed
     */
    protected ?string $_controller = null;

    /**
     * @var  string|null  action to be executed in the controller
     */
    protected ?string $_action = null;

    /**
     * @var  string  the URI of the request
     */
    protected string $_uri;

    /**
     * @var  bool  external request
     */
    protected bool $_external = false;

    /**
     * @var  array  parameters from the route
     */
    protected array $_params = [];

    /**
     * @var  array  query parameters
     */
    protected array $_get = [];

    /**
     * @var  array  post parameters
     */
    protected array $_post = [];

    /**
     * @var  array  cookies to send with the request
     */
    protected array $_cookies = [];

    /**
     * @var  Request_Client  client to handle the request
     */
    protected Request_Client $_client;

    /**
     * Creates a new request object for the given URI. New requests should be
     * created using the [Request::factory] method.
     *
     * If $cache parameter is set, the response for the request will attempt to
     * be retrieved from the cache.
     *
     * @param   string  $uri              URI of the request
     * @param   array   $client_params    Array of params to pass to the request client
     * @param   bool    $allow_external   Allow external requests? (deprecated in 3.3)
     * @param   array   $injected_routes  An array of routes to use, for testing
     * @return  void
     * @throws  Request_Exception
     * @uses    Route::all
     * @uses    Route::matches
     */
    public function __construct(string $uri, array $client_params = [], bool $allow_external = true, array $injected_routes = [])
    {
        $client_params = is_array($client_params) ? $client_params : [];

        $this->_header = new HTTP_Header([]);

        $this->_routes = $injected_routes;

        $split_uri = explode('?', $uri);
        $uri = array_shift($split_uri);

        if ($split_uri) {
            parse_str($split_uri[0], $this->_get);
        }

        if (!$allow_external || strpos($uri, '://') === false) {
            $this->_uri = trim($uri, '/');
            $this->_client = new Request_Client_Internal($client_params);
        } else {
            $this->_route = new Route($uri);
            $this->_uri = $uri;

            if (strpos($uri, 'https://') === 0) {
                $this->secure(true);
            }

            $this->_external = true;
            $this->_client = Request_Client_External::factory($client_params);
        }
    }

    /**
     * Returns the response as the string representation of a request.
     *
     * @return  string
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Sets and gets the URI from the request.
     *
     * @param   string|null  $uri  URI to set
     * @return  string|self
     */
    public function uri(?string $uri = null): string|self
    {
        if ($uri === null) {
            return ($this->_uri === '') ? '/' : $this->_uri;
        }

        $this->_uri = $uri;

        return $this;
    }

    /**
     * Create a URL string from the current request.
     *
     * @param   mixed  $protocol  Protocol string or Request object
     * @return  string
     * @uses    URL::site
     */
    public function url(mixed $protocol = null): string
    {
        if ($this->is_external()) {
            return $this->uri();
        }

        return URL::site($this->uri(), $protocol);
    }

    /**
     * Retrieves a value from the route parameters.
     *
     * @param   string|null  $key      Key of the value
     * @param   mixed        $default  Default value if the key is not set
     * @return  mixed
     */
    public function param(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->_params;
        }

        return $this->_params[$key] ?? $default;
    }

    /**
     * Sets and gets the referrer from the request.
     *
     * @param   string|null  $referrer  Referrer to set
     * @return  string|self
     */
    public function referrer(?string $referrer = null): string|self
    {
        if ($referrer === null) {
            return $this->_referrer;
        }

        $this->_referrer = $referrer;

        return $this;
    }

    /**
     * Sets and gets the route from the request.
     *
     * @param   Route|null  $route  Route to set
     * @return  Route|self
     */
    public function route(?Route $route = null): Route|self
    {
        if ($route === null) {
            return $this->_route;
        }

        $this->_route = $route;

        return $this;
    }

    /**
     * Sets and gets the directory for the controller.
     *
     * @param   string|null  $directory  Directory to execute the controller from
     * @return  string|self
     */
    public function directory(?string $directory = null): string|self
    {
        if ($directory === null) {
            return $this->_directory;
        }

        $this->_directory = $directory;

        return $this;
    }

    /**
     * Sets and gets the controller for the matched route.
     *
     * @param   string|null  $controller  Controller to execute the action
     * @return  string|self
     */
    public function controller(?string $controller = null): string|self
    {
        if ($controller === null) {
            return $this->_controller;
        }

        $this->_controller = $controller;

        return $this;
    }

    /**
     * Sets and gets the action for the controller.
     *
     * @param   string|null  $action  Action to execute the controller from
     * @return  string|self
     */
    public function action(?string $action = null): string|self
    {
        if ($action === null) {
            return $this->_action;
        }

        $this->_action = $action;

        return $this;
    }

    /**
     * Provides access to the [Request_Client].
     *
     * @param   Request_Client|null  $client  Client to set
     * @return  Request_Client|self
     */
    public function client(?Request_Client $client = null): Request_Client|self
    {
        if ($client === null) {
            return $this->_client;
        }

        $this->_client = $client;

        return $this;
    }

    /**
     * Gets and sets the requested with property, which should
     * be relative to the x-requested-with pseudo header.
     *
     * @param   string|null  $requested_with  Requested with value
     * @return  string|self
     */
    public function requested_with(?string $requested_with = null): string|self
    {
        if ($requested_with === null) {
            return $this->_requested_with;
        }

        $this->_requested_with = strtolower($requested_with);

        return $this;
    }

    /**
     * Processes the request, executing the controller action that handles this
     * request, determined by the [Route].
     *
     * @return  Response
     * @throws  Request_Exception
     * @throws  HTTP_Exception_404
     */
    public function execute(): Response
    {
        if (!$this->_external) {
            $processed = Request::process($this, $this->_routes);

            if ($processed) {
                $this->_route = $processed['route'];
                $params = $processed['params'];

                $this->_external = $this->_route->is_external();

                if (isset($params['directory'])) {
                    $this->_directory = $params['directory'];
                }

                $this->_controller = $params['controller'];
                $this->_action = $params['action'] ?? Route::$default_action;

                unset($params['controller'], $params['action'], $params['directory']);

                $this->_params = $params;
            }
        }

        if (!$this->_route instanceof Route) {
            return HTTP_Exception::factory(404, 'Unable to find a route to match the URI: :uri', [':uri' => $this->_uri])
                ->request($this)
                ->get_response();
        }

        if (!$this->_client instanceof Request_Client) {
            throw new Request_Exception('Unable to execute :uri without a Kohana_Request_Client', [':uri' => $this->_uri]);
        }

        return $this->_client->execute($this);
    }

    /**
     * Returns whether this request is the initial request Kohana received.
     * Can be used to test for sub requests.
     *
     * @return  bool
     */
    public function is_initial(): bool
    {
        return ($this === Request::$initial);
    }

    /**
     * Readonly access to the [Request::$_external] property.
     *
     * @return  bool
     */
    public function is_external(): bool
    {
        return $this->_external;
    }

    /**
     * Returns whether this is an ajax request (as used by JS frameworks).
     *
     * @return  bool
     */
    public function is_ajax(): bool
    {
        return ($this->requested_with() === 'xmlhttprequest');
    }

    /**
     * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
     * traditional CRUD applications.
     *
     * @param   string|null  $method  Method to use for this request
     * @return  string|self
     */
    public function method($method = null)
    {
        if ($method === null) {
            // Act as a getter
            return $this->_method;
        }

        // Act as a setter
        $this->_method = strtoupper($method);

        return $this;
    }

/**
     * Gets or sets the HTTP protocol. If there is no current protocol set,
     * it will use the default set in HTTP::$protocol
     *
     * @param   string   $protocol  Protocol to set to the request
     * @return  mixed
     */
    public function protocol($protocol = null)
    {
        if ($protocol === null) {
            if ($this->_protocol)
                return $this->_protocol;
            else
                return $this->_protocol = HTTP::$protocol;
        }

        // Act as a setter
        $this->_protocol = strtoupper($protocol);
        return $this;
    }

    /**
     * Getter/Setter to the security settings for this request. This
     * method should be treated as immutable.
     *
     * @param   bool|null  $secure  Is this request secure?
     * @return  bool|self
     */
    public function secure(?bool $secure = null): bool|self
    {
        if ($secure === null) {
            return $this->_secure;
        }

        $this->_secure = (bool) $secure;
        return $this;
    }

    /**
     * Gets or sets HTTP headers oo the request. All headers
     * are included immediately after the HTTP protocol definition during
     * transmission. This method provides a simple array or key/value
     * interface to the headers.
     *
     * @param   mixed   $key   Key or array of key/value pairs to set
     * @param   string  $value Value to set to the supplied key
     * @return  mixed
     */
    public function headers($key = null, $value = null)
    {
        if ($key instanceof HTTP_Header) {
            // Act a setter, replace all headers
            $this->_header = $key;

            return $this;
        }

        if (is_array($key)) {
            // Act as a setter, replace all headers
            $this->_header->exchangeArray($key);

            return $this;
        }

        if ($this->_header->count() === 0 AND $this->is_initial()) {
            // Lazy load the request headers
            $this->_header = HTTP::request_headers();
        }

        if ($key === null) {
            // Act as a getter, return all headers
            return $this->_header;
        } elseif ($value === null) {
            // Act as a getter, single header
            return ($this->_header->offsetExists($key)) ? $this->_header->offsetGet($key) : null;
        }

        // Act as a setter for a single header
        $this->_header[$key] = $value;

        return $this;
    }

    /**
     * Sets and gets cookies values for this request.
     *
     * @param   string|array|null  $key    Cookie name, or array of cookie values
     * @param   string|null        $value  Value to set to cookie
     * @return  string|array|self|null
     */
    public function cookie(string|array|null $key = null, ?string $value = null): string|array|self|null
    {
        if (is_array($key)) {
            $this->_cookies = $key;
            return $this;
        } elseif ($key === null) {
            return $this->_cookies;
        } elseif ($value === null) {
            return $this->_cookies[$key] ?? null;
        }

        $this->_cookies[$key] = (string) $value;

        return $this;
    }

    /**
     * Gets or sets the HTTP body of the request. The body is
     * included after the header, separated by a single empty new line.
     *
     * @param   string  $content Content to set to the object
     * @return  mixed
     */
    public function body($content = null)
    {
        if ($content === null) {
            // Act as a getter
            return $this->_body;
        }

        // Act as a setter
        $this->_body = $content;

        return $this;
    }

    /**
     * Returns the length of the body for use with content header.
     *
     * @return  int
     */
    public function content_length(): int
    {
        return strlen($this->body());
    }

    /**
     * Renders the HTTP_Interaction to a string.
     *
     * @return  string
     */
    public function render(): string
    {
        if (!$post = $this->post()) {
            $body = $this->body();
        } else {
            $body = http_build_query($post, '', '&');
            $this->body($body)
                ->headers('content-type', 'application/x-www-form-urlencoded; charset=' . Kohana::$charset);
        }

        $this->headers('content-length', (string) $this->content_length());

        if (Kohana::$expose) {
            $this->headers('user-agent', Kohana::version());
        }

        if ($this->_cookies) {
            $cookie_string = [];

            foreach ($this->_cookies as $key => $value) {
                $cookie_string[] = $key . '=' . $value;
            }

            $this->_header['cookie'] = implode('; ', $cookie_string);
        }

        $output = $this->method() . ' ' . $this->uri() . ' ' . $this->protocol() . "\r\n";
        $output .= (string) $this->_header;
        $output .= $body;

        return $output;
    }

   /**
     * Gets or sets HTTP query string.
     *
     * @param   mixed   $key    Key or key value pairs to set
     * @param   string  $value  Value to set to a key
     * @return  mixed
     * @uses    Arr::path
     */
    public function query($key = null, $value = null)
    {
        if (is_array($key)) {
            // Act as a setter, replace all query strings
            $this->_get = $key;

            return $this;
        }

        if ($key === null) {
            // Act as a getter, all query strings
            return $this->_get;
        } elseif ($value === null) {
            // Act as a getter, single query string
            return Arr::path($this->_get, $key);
        }

        // Act as a setter, single query string
        $this->_get[$key] = $value;

        return $this;
    }

    /**
     * Gets or sets HTTP POST parameters to the request.
     *
     * @param   mixed  $key    Key or key value pairs to set
     * @param   string $value  Value to set to a key
     * @return  mixed
     * @uses    Arr::path
     */
    public function post($key = null, $value = null)
    {
        if (is_array($key)) {
            // Act as a setter, replace all fields
            $this->_post = $key;

            return $this;
        }

        if ($key === null) {
            // Act as a getter, all fields
            return $this->_post;
        } elseif ($value === null) {
            // Act as a getter, single field
            return Arr::path($this->_post, $key);
        }

        // Act as a setter, single field
        $this->_post[$key] = $value;

        return $this;
    }
}
