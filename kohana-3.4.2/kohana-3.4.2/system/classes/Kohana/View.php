<?php

/**
 * Acts as an object wrapper for HTML pages with embedded PHP, called "views".
 * Variables can be assigned with the view object and referenced locally within
 * the view.
 *
 * @package    Kohana
 * @category   Base
 * @author     Kohana Team
 * @copyright  (c) 2024 Kohana Team
 * @license    https://kohana.top/license
 */

class Kohana_View
{
    // Array of global variables
    protected static array $_global_data = [];

    /**
     * Returns a new View object. If you do not define the "file" parameter,
     * you must call [View::set_filename].
     *
     * @param   string|null  $file   view filename
     * @param   array|null   $data   array of values
     * @return  View
     */
    public static function factory(?string $file = null, ?array $data = null): View
    {
        return new View($file, $data);
    }

    /**
     * Captures the output that is generated when a view is included.
     *
     * @param   string  $kohana_view_filename   filename
     * @param   array   $kohana_view_data       variables
     * @return  string
     * @throws  Exception
     */
    protected static function capture(string $kohana_view_filename, array $kohana_view_data): string
    {
        // Import the view variables to local namespace
        extract($kohana_view_data, EXTR_SKIP);

        if (View::$_global_data) {
            // Import the global view variables to local namespace
            extract(View::$_global_data, EXTR_SKIP | EXTR_REFS);
        }

        // Capture the view output
        ob_start();

        try {
            // Load the view within the current scope
            include $kohana_view_filename;
        } catch (Throwable $e) {
            // Delete the output buffer
            ob_end_clean();

            // Re-throw the exception
            throw $e;
        }

        // Get the captured output and close the buffer
        return ob_get_clean();
    }

    /**
     * Sets a global variable, similar to [View::set].
     *
     * @param   string|array|Traversable  $key    variable name or an array of variables
     * @param   mixed                     $value  value
     * @return  void
     */
    public static function set_global(string|array|Traversable $key, mixed $value = null): void
    {
        if (is_array($key) || $key instanceof Traversable) {
            foreach ($key as $name => $value) {
                View::$_global_data[$name] = $value;
            }
        } else {
            View::$_global_data[$key] = $value;
        }
    }

    /**
     * Assigns a global variable by reference, similar to [View::bind].
     *
     * @param   string  $key    variable name
     * @param   mixed   $value  referenced variable
     * @return  void
     */
    public static function bind_global(string $key, mixed &$value): void
    {
        View::$_global_data[$key] = &$value;
    }

    // View filename
    protected string $_file;
    // Array of local variables
    protected array $_data = [];

    /**
     * Sets the initial view filename and local data.
     *
     * @param   string|null  $file   view filename
     * @param   array|null   $data   array of values
     */
    public function __construct(?string $file = null, ?array $data = null)
    {
        if ($file !== null) {
            $this->set_filename($file);
        }

        if ($data !== null) {
            // Add the values to the current data
            $this->_data = $data + $this->_data;
        }
    }

/**
 * Magic method, searches for the given variable and returns its value.
 * Local variables will be returned before global variables.
 *
 * @param   string  $key    variable name
 * @return  mixed
 * @throws  Kohana_Exception
 */
public function &__get(string $key): mixed
{
    // Проверяем наличие ключа и возвращаем ссылку на локальную переменную
    if (isset($this->_data[$key])) {
        return $this->_data[$key];
    }
    
    // Проверяем наличие ключа и возвращаем ссылку на глобальную переменную
    if (isset(self::$_global_data[$key])) {
        return self::$_global_data[$key];
    }
    
    // Если ключ не найден, бросаем исключение
    throw new Kohana_Exception("View variable is not set: {$key}");
}

    /**
     * Magic method, calls [View::set] with the same parameters.
     *
     * @param   string  $key    variable name
     * @param   mixed   $value  value
     * @return  void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Magic method, determines if a variable is set.
     *
     * @param   string  $key    variable name
     * @return  boolean
     */
    public function __isset(string $key): bool
    {
        return isset($this->_data[$key]) || isset(View::$_global_data[$key]);
    }

    /**
     * Magic method, unsets a given variable.
     *
     * @param   string  $key    variable name
     * @return  void
     */
    public function __unset(string $key): void
    {
        unset($this->_data[$key], View::$_global_data[$key]);
    }

    /**
     * Magic method, returns the output of [View::render].
     *
     * @return  string
     * @uses    View::render
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (Throwable $e) {
            // Handle the exception and return the response body
            $error_response = Kohana_Exception::_handler($e);
            return $error_response->body();
        }
    }

    /**
     * Sets the view filename.
     *
     * @param   string  $file   view filename
     * @return  View
     * @throws  View_Exception
     */
    public function set_filename(string $file): self
    {
        if (($path = Kohana::find_file('views', $file)) === false) {
            throw new View_Exception('The requested view :file could not be found', [':file' => $file]);
        }

        // Store the file path locally
        $this->_file = $path;

        return $this;
    }

    /**
     * Assigns a variable by name.
     *
     * @param   string|array|Traversable  $key    variable name or an array of variables
     * @param   mixed                     $value  value
     * @return  $this
     */
    public function set(string|array|Traversable $key, mixed $value = null): self
    {
        if (is_array($key) || $key instanceof Traversable) {
            foreach ($key as $name => $value) {
                $this->_data[$name] = $value;
            }
        } else {
            $this->_data[$key] = $value;
        }

        return $this;
    }

    /**
     * Assigns a value by reference.
     *
     * @param   string  $key    variable name
     * @param   mixed   $value  referenced variable
     * @return  $this
     */
    public function bind(string $key, mixed &$value): self
    {
        $this->_data[$key] = &$value;

        return $this;
    }

    /**
     * Renders the view object to a string.
     *
     * @param   string|null  $file   view filename
     * @return  string
     * @throws  View_Exception
     * @uses    View::capture
     */
    public function render(?string $file = null): string
    {
        if ($file !== null) {
            $this->set_filename($file);
        }

        if (empty($this->_file)) {
            throw new View_Exception('You must set the file to use within your view before rendering');
        }

        // Combine local and global data and capture the output
        return View::capture($this->_file, $this->_data);
    }
}

