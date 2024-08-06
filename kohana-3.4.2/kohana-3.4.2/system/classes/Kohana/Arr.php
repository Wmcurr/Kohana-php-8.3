<?php
declare(strict_types=1);

/**
 * Array helper.
 *
 * Provides a collection of useful array functions.
 * @php 8.3
 * @package    Kohana
 * @category   Helpers
 */
namespace Kohana;

class Arr
{
    /**
     * @var string Default delimiter for path() method.
     */
    public static $delimiter = '.';

    /**
     * Determines if an array is associative.
     *
     *     // Returns true
     *     Arr::is_assoc(['username' => 'john.doe']);
     *
     *     // Returns false
     *     Arr::is_assoc(['foo', 'bar']);
     *
     * @param array $array The array to check.
     * @return bool True if the array is associative, false otherwise.
     */
    public static function is_assoc(array $array): bool
    {
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }

    /**
     * Checks if a value is an array or an object implementing Traversable.
     *
     *     // Returns true
     *     Arr::is_array([]);
     *     Arr::is_array(new ArrayObject);
     *
     *     // Returns false
     *     Arr::is_array(false);
     *     Arr::is_array('not an array!');
     *     Arr::is_array(Database::instance());
     *
     * @param mixed $value The value to check.
     * @return bool True if the value is an array or a Traversable object, false otherwise.
     */
    public static function is_array($value): bool
    {
        return is_array($value) || (is_object($value) && $value instanceof \Traversable);
    }

    /**
     * Retrieves a value from an array using a dot-separated path.
     *
     *     // Get the value of $array['foo']['bar']
     *     $value = Arr::path($array, 'foo.bar');
     *
     * Using a wildcard "*" will search intermediate arrays and return an array.
     *
     *     // Get the values of "color" in theme
     *     $colors = Arr::path($array, 'theme.*.color');
     *
     *     // Using an array of keys
     *     $colors = Arr::path($array, ['theme', '*', 'color']);
     *
     * @param array $array The array to search.
     * @param mixed $path Key path string (delimiter-separated) or array of keys.
     * @param mixed $default Default value if the path is not set.
     * @param string|null $delimiter Key path delimiter.
     * @return mixed The value found, or the default value.
     */
    public static function path($array, $path, $default = null, $delimiter = null)
    {
        if (!Arr::is_array($array)) {
            return $default;
        }

        if (is_array($path)) {
            $keys = $path;
        } else {
            if (array_key_exists($path, $array)) {
                return $array[$path];
            }

            $delimiter ??= Arr::$delimiter;
            $path = ltrim($path, "{$delimiter} ");
            $path = rtrim($path, "{$delimiter} *");
            $keys = explode($delimiter, $path);
        }

        do {
            $key = array_shift($keys);

            if (ctype_digit($key)) {
                $key = (int)$key;
            }

            if (isset($array[$key])) {
                if ($keys) {
                    if (Arr::is_array($array[$key])) {
                        $array = $array[$key];
                    } else {
                        break;
                    }
                } else {
                    return $array[$key];
                }
            } elseif ($key === '*') {
                $values = [];
                foreach ($array as $arr) {
                    if ($value = Arr::path($arr, implode('.', $keys))) {
                        $values[] = $value;
                    }
                }

                return $values ?: $default;
            } else {
                break;
            }
        } while ($keys);

        return $default;
    }

    /**
     * Sets a value in an array using a dot-separated path.
     *
     * @param array $array Array to update.
     * @param mixed $path Path as a string or array.
     * @param mixed $value Value to set.
     * @param string|null $delimiter Path delimiter.
     */
public static function set_path(array &$array, string|array $path, mixed $value, ?string $delimiter = null): void
{
    // Use the default delimiter if none is provided
    $delimiter ??= Arr::$delimiter;

    // Convert path to an array if it's a string
    $keys = is_array($path) ? $path : explode($delimiter, $path);

    // Reference to the current level in the array
    $current = &$array;

    // Traverse the array, creating nested arrays as needed
    while (count($keys) > 1) {
        $key = array_shift($keys);
        // Convert numeric string keys to integers
        $key = ctype_digit($key) ? (int) $key : $key;
        // Create a new nested array if the key doesn't exist
        $current[$key] ??= [];
        // Move the reference to the next level
        $current = &$current[$key];
    }

    // Set the value at the final key
    $current[array_shift($keys)] = $value;
}

    /**
     * Fills an array with a range of numbers.
     *
     *     // Fill an array with values 5, 10, 15, 20
     *     $values = Arr::range(5, 20);
     *
     * @param int $step The step between values.
     * @param int $max The maximum value.
     * @return array Array of numbers.
     */
    public static function range(int $step = 10, int $max = 100): array
    {
        if ($step < 1) return [];

        $array = [];
        for ($i = $step; $i <= $max; $i += $step) {
            $array[$i] = $i;
        }

        return $array;
    }

    /**
     * Retrieves a value from an array using a specific key. 
     * Returns a default value if the key does not exist.
     *
     *     // Get the value "username" from $_POST, if it exists
     *     $username = Arr::get($_POST, 'username');
     *
     * @param array|ArrayObject $array The array or ArrayObject to extract from.
     * @param string $key The key to retrieve.
     * @param mixed $default Default value if the key does not exist.
     * @return mixed The value of the key or the default value.
     */
    public static function get($array, string $key, $default = null)
    {
        if ($array instanceof \ArrayObject) {
            return $array->offsetExists($key) ? $array->offsetGet($key) : $default;
        }

        return $array[$key] ?? $default;
    }

    /**
     * Extracts multiple paths from an array, providing a default value if the path is not found.
     *
     *     // Get the values "username", "password" from $_POST
     *     $auth = Arr::extract($_POST, ['username', 'password']);
     *
     * @param array $array The array to extract paths from.
     * @param array $paths List of paths to extract.
     * @param mixed $default Default value if a path is not found.
     * @return array Extracted values.
     */
    public static function extract(array $array, array $paths, $default = null): array
    {
        $found = [];
        foreach ($paths as $path) {
            Arr::set_path($found, $path, Arr::path($array, $path, $default));
        }

        return $found;
    }

    /**
     * Retrieves multiple single-key values from a list of arrays.
     *
     *     // Get all of the "id" values from a result
     *     $ids = Arr::pluck($result, 'id');
     *
     * [!!] A list of arrays is an array that contains arrays.
     *
     * @param array $array List of arrays to check.
     * @param string $key The key to pluck.
     * @return array An array of values.
     */
    public static function pluck(array $array, string $key): array
    {
        $values = [];

        foreach ($array as $row) {
            if (isset($row[$key])) {
                $values[] = $row[$key];
            }
        }

        return $values;
    }

    /**
     * Adds a value to the beginning of an associative array.
     *
     *     // Add an empty value to the start of a select list
     *     Arr::unshift($array, 'none', 'Select a value');
     *
     * @param array $array Array to modify.
     * @param string $key Array key name.
     * @param mixed $val Array value.
     * @return array The modified array.
     */
    public static function unshift(array &$array, string $key, $val): array
    {
        $array = array_reverse($array, true);
        $array[$key] = $val;
        return array_reverse($array, true);
    }

    /**
     * Recursively applies one or more callbacks to all elements in an array, including sub-arrays.
     *
     *     // Apply "strip_tags" to every element in the array
     *     $array = Arr::map('strip_tags', $array);
     *
     *     // Apply $this->filter to every element in the array
     *     $array = Arr::map([[$this, 'filter']], $array);
     *
     *     // Apply strip_tags and $this->filter to every element
     *     $array = Arr::map(['strip_tags', [$this,'filter']], $array);
     *
     * @param callable|array $callbacks Callbacks to apply to every element.
     * @param array $array Array to map.
     * @param array|null $keys Array of keys to apply to.
     * @return array The mapped array.
     */
    public static function map($callbacks, array $array, ?array $keys = null): array
    {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $array[$key] = Arr::map($callbacks, $val, $keys);
            } elseif ($keys === null || in_array($key, $keys)) {
                foreach ((array) $callbacks as $callback) {
                    $array[$key] = call_user_func($callback, $val);
                }
            }
        }

        return $array;
    }

    /**
     * Recursively merges two or more arrays. Values in associative arrays
     * overwrite previous values with the same key. Values in indexed arrays
     * are appended if they do not already exist.
     *
     *     $john = ['name' => 'john', 'children' => ['fred', 'paul', 'sally', 'jane']];
     *     $mary = ['name' => 'mary', 'children' => ['jane']];
     *
     *     // John and Mary are married, merge them together
     *     $john = Arr::merge($john, $mary);
     *
     *     // The output of $john will now be:
     *     ['name' => 'mary', 'children' => ['fred', 'paul', 'sally', 'jane']]
     *
     * @param array $array1 Initial array.
     * @param array ...$arrays Arrays to merge.
     * @return array Merged array.
     */
    public static function merge(array $array1, array ...$arrays): array
    {
        foreach ($arrays as $array2) {
            foreach ($array2 as $key => $value) {
                if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                    $array1[$key] = Arr::merge($array1[$key], $value);
                } elseif (!is_numeric($key) || !in_array($value, $array1, true)) {
                    $array1[$key] = $value;
                }
            }
        }

        return $array1;
    }

    /**
     * Overwrites an array with values from input arrays. 
     * Keys that do not exist in the first array will not be added!
     *
     *     $a1 = ['name' => 'john', 'mood' => 'happy', 'food' => 'bacon'];
     *     $a2 = ['name' => 'jack', 'food' => 'tacos', 'drink' => 'beer'];
     *
     *     // Overwrite the values of $a1 with $a2
     *     $array = Arr::overwrite($a1, $a2);
     *
     *     // The output of $array will now be:
     *     ['name' => 'jack', 'mood' => 'happy', 'food' => 'tacos']
     *
     * @param array $array1 Master array.
     * @param array ...$arrays Input arrays that will overwrite existing values.
     * @return array Overwritten array.
     */
    public static function overwrite(array $array1, array ...$arrays): array
    {
        foreach ($arrays as $array2) {
            foreach (array_intersect_key($array2, $array1) as $key => $value) {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    /**
     * Creates a callable function and parameter list from a string representation.
     * Note that this function does not validate the callback string.
     *
     *     // Get the callback function and parameters
     *     list($func, $params) = Arr::callback('Foo::bar(apple,orange)');
     *
     *     // Get the result of the callback
     *     $result = call_user_func_array($func, $params);
     *
     * @param string $str Callback string.
     * @return array [callable, array] The callable and its parameters.
     */
    public static function callback(string $str): array
    {
        $command = $params = null;

        if (preg_match('/^([^\(]*+)\((.*)\)$/', $str, $match)) {
            $command = $match[1];
            $params = $match[2] !== '' ? preg_split('/(?<!\\\\),/', $match[2]) : [];
            $params = str_replace('\,', ',', $params);
        } else {
            $command = $str;
        }

        if (strpos($command, '::') !== false) {
            $command = explode('::', $command, 2);
        }

        return [$command, $params];
    }

    /**
     * Converts a multi-dimensional array into a single-dimensional array.
     *
     *     $array = ['set' => ['one' => 'something'], 'two' => 'other'];
     *
     *     // Flatten the array
     *     $array = Arr::flatten($array);
     *
     *     // The array will now be:
     *     ['one' => 'something', 'two' => 'other'];
     *
     * @param array $array Array to flatten.
     * @return array Flattened array.
     */
    public static function flatten(array $array): array
    {
        $flat = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $flat = array_merge($flat, Arr::flatten($value));
            } else {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }
}
