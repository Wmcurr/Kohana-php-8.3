<?php

declare(strict_types=1);

/**
 * Database query builder for UPDATE statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 */
class Kohana_Database_Query_Builder_Update extends Database_Query_Builder_Where
{
    // UPDATE ...
    protected string|array|Database_Expression $_table;
    // SET ...
    protected array $_set = [];

    /**
     * Set the table for an update.
     *
     * @param string|array|Database_Expression|null $table Table name or [$table, $alias] or object.
     */
    public function __construct(string|array|Database_Expression $table = null)
    {
        if ($table !== null) {
            // Set the initial table name
            $this->_table = $table;
        }

        // Start the query with the correct type and no SQL
        parent::__construct(QueryType::UPDATE, '');
    }

    /**
     * Sets the table to update.
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
     * Set the values to update with an associative array.
     *
     * @param array $pairs Associative (column => value) list.
     * @return $this
     */
    public function set(array $pairs): self
    {
        foreach ($pairs as $column => $value) {
            $this->_set[] = [$column, $value];
        }

        return $this;
    }

    /**
     * Set the value of a single column.
     *
     * @param string|array|Database_Expression $column Table name or [$table, $alias] or object.
     * @param mixed                            $value  Column value.
     * @return $this
     */
    public function value(string|array|Database_Expression $column, mixed $value): self
    {
        $this->_set[] = [$column, $value];

        return $this;
    }

    /**
     * Increment a column's value by a specified amount.
     *
     * @param string $column Column name.
     * @param int    $amount Amount to increment.
     * @return $this
     */
    public function increment(string $column, int $amount = 1): self
    {
        $this->_set[] = [$column, new Database_Expression("$column + $amount")];
        return $this;
    }

    /**
     * Decrement a column's value by a specified amount.
     *
     * @param string $column Column name.
     * @param int    $amount Amount to decrement.
     * @return $this
     */
    public function decrement(string $column, int $amount = 1): self
    {
        $this->_set[] = [$column, new Database_Expression("$column - $amount")];
        return $this;
    }

    /**
     * Update existing record or insert new if not exists.
     *
     * @param array $conditions Conditions to check for existing records.
     * @param array $values     Values to update or insert.
     * @param Database $db       Database instance.
     * @return void
     */
    public function updateOrInsert(array $conditions, array $values, Database $db): void
    {
        $exists = (new Database_Query_Builder_Select)
            ->from($this->_table)
            ->where($conditions)
            ->execute($db)
            ->count();

        if ($exists) {
            $this->set($values)
                ->where($conditions)
                ->execute($db);
        } else {
            (new Database_Query_Builder_Insert($this->_table))
                ->columns(array_keys($values))
                ->values(array_values($values))
                ->execute($db);
        }
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

        // Start an update query
        $query = 'UPDATE ' . $db->quote_table($this->_table);

        // Add the columns to update
        $query .= ' SET ' . $this->_compile_set($db, $this->_set);

        if (!empty($this->_where)) {
            // Add selection conditions
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
     * Reset the builder to its initial state.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->_table = '';
        $this->_set = [];
        $this->_where = [];
        $this->_order_by = [];
        $this->_limit = null;
        $this->_parameters = [];

        return $this;
    }
}
