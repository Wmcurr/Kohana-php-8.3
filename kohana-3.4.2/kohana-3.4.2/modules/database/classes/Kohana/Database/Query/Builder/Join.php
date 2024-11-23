<?php

declare(strict_types=1);

/**
 * Database query builder for JOIN statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 */
class Kohana_Database_Query_Builder_Join extends Kohana_Database_Query
{
    // JOIN ...
    protected string|array $_table;
    // ON ...
    protected array $_on = [];
    // USING ...
    protected array $_using = [];

    /**
     * Creates a new JOIN statement for a table. Optionally, the type of JOIN
     * can be specified as the second parameter.
     *
     * @param string|array $table Column name or [$column, $alias] or object.
     * @param QueryType|null $type Type of JOIN: INNER, RIGHT, LEFT, etc.
     */
    public function __construct(string|array $table, ?QueryType $type = null)
    {
        // Call parent constructor with the given type or default to INNER JOIN
        parent::__construct($type ?? QueryType::INNER, '');

        // Set the table to JOIN on
        $this->_table = $table;
    }

    /**
     * Adds a new condition for joining.
     *
     * @param string|array|Database_Expression $c1  Column name or [$column, $alias] or object.
     * @param string                           $op  Logic operator.
     * @param string|array|Database_Expression $c2  Column name or [$column, $alias] or object.
     * @return $this
     * @throws Kohana_Exception
     */
    public function on(string|array|Database_Expression $c1, string $op, string|array|Database_Expression $c2): self
    {
        if (!empty($this->_using)) {
            throw new Kohana_Exception('JOIN ... ON ... cannot be combined with JOIN ... USING ...');
        }

        $this->_on[] = [$c1, $op, $c2];

        return $this;
    }

    /**
     * Adds a new condition for joining using columns.
     *
     * @param string ...$columns Column names.
     * @return $this
     * @throws Kohana_Exception
     */
    public function using(string ...$columns): self
    {
        if (!empty($this->_on)) {
            throw new Kohana_Exception('JOIN ... ON ... cannot be combined with JOIN ... USING ...');
        }

        $this->_using = array_merge($this->_using, $columns);

        return $this;
    }

    /**
     * Compile the SQL partial for a JOIN statement and return it.
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

        // Ensure that $_type is correctly initialized and accessible
        $sql = strtoupper($this->_type->name) . ' JOIN';

        // Quote the table name that is being joined
        $sql .= ' ' . $db->quote_table($this->_table);

        if (!empty($this->_using)) {
            // Quote and concatenate the columns
            $sql .= ' USING (' . implode(', ', array_map([$db, 'quote_column'], $this->_using)) . ')';
        } else {
            $conditions = [];
            foreach ($this->_on as [$c1, $op, $c2]) {
                // Make the operator uppercase and spaced
                $op = $op ? ' ' . strtoupper($op) : '';

                // Quote each of the columns used for the condition
                $conditions[] = $db->quote_column($c1) . $op . ' ' . $db->quote_column($c2);
            }

            // Concatenate the conditions "... AND ..."
            $sql .= ' ON (' . implode(' AND ', $conditions) . ')';
        }

        return $sql;
    }

    /**
     * Reset the join builder to its initial state.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->_table = [];
        $this->_on = [];
        $this->_using = [];

        return $this;
    }
}
