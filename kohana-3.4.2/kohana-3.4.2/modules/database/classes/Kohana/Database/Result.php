<?php

declare(strict_types=1);

/**
 * Wrapper for database result. See [Results](/database/results) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query/Result
 * @autor      Kohana Team
 * @license    https://kohana.top/license
 */
abstract class Kohana_Database_Result implements Countable, Iterator, SeekableIterator, ArrayAccess
{
    // Executed SQL for this result
    protected string $_query;
    // Raw result resource
    protected mixed $_result;
    // Total number of rows and current row
    protected int $_total_rows = 0;
    protected int $_current_row = 0;
    // Return rows as object or associative array
    protected string|bool $_as_object;
    // Parameters for __construct when using object results
    protected ?array $_object_params = null;

    /**
     * Sets the total number of rows and stores the result locally.
     *
     * @param   mixed   $result     query result
     * @param   string  $sql        SQL query
     * @param   string|bool $as_object return as object or array
     * @param   array|null $params   constructor parameters for objects
     * @return  void
     */
    public function __construct(mixed $result, string $sql, string|bool $as_object = false, ?array $params = null)
    {
        // Store result locally
        $this->_result = $result;

        // Store SQL locally
        $this->_query = $sql;

        if (is_object($as_object)) {
            // Get the class name of the object
            $as_object = get_class($as_object);
        }

        // Results as objects or associative arrays
        $this->_as_object = $as_object;

        if ($params) {
            // Object constructor parameters
            $this->_object_params = $params;
        }
    }

    /**
     * Destructs the result and clears all open result sets.
     *
     * @return  void
     */
    abstract public function __destruct();

    /**
     * Get a cached database result from the current result iterator.
     *
     *     $cachable = serialize($result->cached());
     *
     * @return  Database_Result_Cached
     * @since   3.0.5
     */
    public function cached(): Database_Result_Cached
    {
        return new Database_Result_Cached($this->as_array(), $this->_query, $this->_as_object);
    }

    /**
     * Returns all the rows as an array.
     *
     *     // Indexed array of all rows
     *     $rows = $result->as_array();
     *
     *     // Associative array of rows by "id"
     *     $rows = $result->as_array('id');
     *
     *     // Associative array of rows, "id" => "name"
     *     $rows = $result->as_array('id', 'name');
     *
     * @param   string|null  $key    column for associative keys
     * @param   string|null  $value  column for values
     * @return  array
     */
    public function as_array(?string $key = null, ?string $value = null): array
    {
        $results = [];

        if ($key === null && $value === null) {
            // Indexed rows
            foreach ($this as $row) {
                $results[] = $row;
            }
        } elseif ($key === null) {
            // Indexed columns
            foreach ($this as $row) {
                $results[] = $this->_as_object ? $row->$value : $row[$value];
            }
        } elseif ($value === null) {
            // Associative rows
            foreach ($this as $row) {
                $results[$this->_as_object ? $row->$key : $row[$key]] = $row;
            }
        } else {
            // Associative columns
            foreach ($this as $row) {
                $results[$this->_as_object ? $row->$key : $row[$key]] = $this->_as_object ? $row->$value : $row[$value];
            }
        }

        $this->rewind();

        return $results;
    }

    /**
     * Returns the named column from the current row.
     *
     *     // Get the "id" value
     *     $id = $result->get('id');
     *
     * @param   string  $name     column name
     * @param   mixed   $default  default value if column does not exist
     * @return  mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $row = $this->current();

        if ($this->_as_object) {
            return $row->$name ?? $default;
        }

        return $row[$name] ?? $default;
    }

    /**
     * Implements [Countable::count], returns the total number of rows.
     *
     *     echo count($result);
     *
     * @return  int
     */
    public function count(): int
    {
        return $this->_total_rows;
    }

    /**
     * Implements [ArrayAccess::offsetExists], determines if a row exists.
     *
     *     if (isset($result[10]))
     *     {
     *         // Row 10 exists
     *     }
     *
     * @param   mixed $offset
     * @return  bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_int($offset) && $offset >= 0 && $offset < $this->_total_rows;
    }

    /**
     * Implements [ArrayAccess::offsetGet], gets the specified row.
     *
     *     $row = $result[10];
     *
     * @param   mixed $offset
     * @return  mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->seek($offset)) {
            return null;
        }

        return $this->current();
    }

    /**
     * Implements [ArrayAccess::offsetSet], throws an error.
     *
     * [!!] You cannot modify a database result.
     *
     * @param   mixed $offset
     * @param   mixed $value
     * @return  void
     * @throws  Kohana_Exception
     */
    final public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new Kohana_Exception('Database results are read-only');
    }

    /**
     * Implements [ArrayAccess::offsetUnset], throws an error.
     *
     * [!!] You cannot modify a database result.
     *
     * @param   mixed $offset
     * @return  void
     * @throws  Kohana_Exception
     */
    final public function offsetUnset(mixed $offset): void
    {
        throw new Kohana_Exception('Database results are read-only');
    }

    /**
     * Implements [Iterator::key], returns the current row number.
     *
     *     echo key($result);
     *
     * @return  int
     */
    public function key(): int
    {
        return $this->_current_row;
    }

    /**
     * Implements [Iterator::next], moves to the next row.
     *
     *     next($result);
     *
     * @return  void
     */
    public function next(): void
    {
        ++$this->_current_row;
    }

    /**
     * Implements [Iterator::prev], moves to the previous row.
     *
     *     prev($result);
     *
     * @return  $this
     */
    public function prev(): self
    {
        --$this->_current_row;
        return $this;
    }

    /**
     * Implements [Iterator::rewind], sets the current row to zero.
     *
     *     rewind($result);
     *
     * @return  void
     */
    public function rewind(): void
    {
        $this->_current_row = 0;
    }

    /**
     * Implements [Iterator::valid], checks if the current row exists.
     *
     * [!!] This method is only used internally.
     *
     * @return  bool
     */
    public function valid(): bool
    {
        return $this->offsetExists($this->_current_row);
    }

/**
 * Abstract method to seek to a specific row.
 *
 * @param   int $position
 * @return  void
 */
abstract public function seek(int $position): void;

    /**
     * Abstract method to get the current row.
     *
     * @return  mixed
     */
    abstract public function current(): mixed;
}
