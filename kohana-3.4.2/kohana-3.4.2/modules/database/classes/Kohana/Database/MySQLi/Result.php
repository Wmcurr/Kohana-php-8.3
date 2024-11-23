<?php

/**
 * MySQLi Database Result.
 *
 * This class represents the result set obtained from a MySQLi query.
 * It provides methods to iterate over the result set and fetch data.
 *
 * @package    Kohana/Database
 * @category   Query/Result
 */
class Kohana_Database_MySQLi_Result extends Database_Result
{
    /**
     * Internal row counter to keep track of the current row.
     *
     * @var int
     */
    protected $_internal_row = 0;

    /**
     * Constructor to initialize the result object.
     *
     * @param mysqli_result $result    The result resource from the query.
     * @param string        $sql       The SQL query that was executed.
     * @param bool|string   $as_object Whether to return results as objects.
     * @param array|null    $params    Parameters for object construction.
     */
    public function __construct($result, $sql, $as_object = false, array $params = null)
    {
        // Call the parent constructor
        parent::__construct($result, $sql, $as_object, $params);

        // Get the total number of rows in the result set
        $this->_total_rows = $result->num_rows;
    }

    /**
     * Destructor to free up the result memory.
     */
    public function __destruct()
    {
        // Free the result memory if the result is valid
        if ($this->_result instanceof mysqli_result) {
            $this->_result->free();
        }
    }

    /**
     * Seeks to a specified position in the result set.
     *
     * This method moves the internal pointer to the specified row.
     * If the position is invalid, it throws an exception.
     *
     * @param int $position The row number to seek to.
     *
     * @return void
     *
     * @throws Database_Exception If seeking to the position fails.
     */
    public function seek(int $position): void
    {
        // Check if the result set is valid and not empty
        if ($this->_total_rows === 0 || !$this->_result) {
            throw new Database_Exception("Result set is empty or invalid, unable to seek to position {$position}");
        }

        // Attempt to seek to the desired position
        if (!$this->offsetExists($position) || !$this->_result->data_seek($position)) {
            throw new Database_Exception("Unable to seek to position {$position}");
        }

        // Update the internal row counter
        $this->_current_row = $this->_internal_row = $position;
    }

    /**
     * Returns the current row in the result set.
     *
     * Depending on the $as_object parameter, it returns the row as an array or object.
     *
     * @return mixed The current row as an array or object.
     */
    public function current(): mixed
    {
        // Check if the result set is empty
        if ($this->_total_rows === 0) {
            return null;
        }

        // Ensure the internal row pointer is at the correct position
        if ($this->_current_row !== $this->_internal_row && !$this->seek($this->_current_row)) {
            return null;
        }

        // Increment the internal row counter
        $this->_internal_row++;

        // Return the row based on the specified format
        if ($this->_as_object === true) {
            // Return the row as a stdClass object
            return $this->_result->fetch_object();
        } elseif (is_string($this->_as_object)) {
            // Return the row as an object of a specified class
            return $this->_result->fetch_object($this->_as_object, (array)$this->_object_params);
        } else {
            // Return the row as an associative array
            return $this->_result->fetch_assoc();
        }
    }

    /**
     * Retrieves a value from the first row of the result set.
     *
     * This method is useful for fetching single values from queries expected to return only one row.
     *
     * @param string $name    The column name to retrieve.
     * @param mixed  $default The default value if the column is not found.
     *
     * @return mixed The value of the column or the default value.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        // Ensure the result set is not empty
        if ($this->_total_rows === 0) {
            return $default;
        }

        // Move to the first row
        $this->seek(0);
        $row = $this->current();

        // Return the value based on the row format
        if (is_array($row) && array_key_exists($name, $row)) {
            return $row[$name];
        } elseif (is_object($row) && property_exists($row, $name)) {
            return $row->$name;
        } else {
            return $default;
        }
    }
}
