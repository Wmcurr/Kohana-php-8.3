<?php

declare(strict_types=1);

/**
 * Database query builder for DELETE statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 */
class Kohana_Database_Query_Builder_Delete extends Database_Query_Builder_Where
{
    // DELETE FROM ...
    protected string|array|Database_Expression|null $_table = null;

    /**
     * Set the table for a delete.
     *
     * @param string|array|Database_Expression|null $table Table name or [$table, $alias] or object.
     */
    public function __construct(string|array|Database_Expression|null $table = null)
    {
        if ($table !== null) {
            // Set the initial table name
            $this->_table = $table;
        }

        // Start the query with no SQL
        parent::__construct(QueryType::DELETE, '');
    }

    /**
     * Sets the table to delete from.
     *
     * @param string|array|Database_Expression $table Table name or [$table, $alias] or object.
     * @return $this
     */
    public function table(string|array|Database_Expression $table): self
    {
        $this->_table = $table;

        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param mixed $db Database instance or name of instance.
     * @return string
     */
    public function compile(mixed $db = null): string
    {
        if (!is_object($db)) {
            // Get the database instance
            $db = Database::instance($db);
        }

        // Start a deletion query
        $query = 'DELETE FROM ' . $db->quote_table($this->_table);

        if (!empty($this->_where)) {
            // Add deletion conditions
            $query .= ' WHERE ' . $this->_compile_conditions($db, $this->_where);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by($db, $this->_order_by);
        }

        if ($this->_limit !== null) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        return $query;
    }

    /**
     * Reset the builder state.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->_table = null;
        $this->_where = [];
        $this->_order_by = [];
        $this->_limit = null;
        $this->_parameters = [];

        return $this;
    }
}
