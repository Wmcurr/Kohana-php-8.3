<?php

declare(strict_types=1);

/**
 * Database query builder for SELECT statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 */
class Kohana_Database_Query_Builder_Select extends Database_Query_Builder_Where
{
    // SELECT ...
    protected array $_select = [];
    // DISTINCT
    protected bool $_distinct = false;
    // FROM ...
    protected array $_from = [];
    // JOIN ...
    protected array $_join = [];
    // GROUP BY ...
    protected array $_group_by = [];
    // HAVING ...
    protected array $_having = [];
    // OFFSET ...
    protected ?int $_offset = null;
    // UNION ...
    protected array $_union = [];
    // The last JOIN statement created
    protected ?Database_Query_Builder_Join $_last_join = null;

    /**
     * Sets the initial columns to select from.
     *
     * @param array|null $columns Column list.
     */
    public function __construct(array $columns = null)
    {
        if (!empty($columns)) {
            // Set the initial columns
            $this->_select = $columns;
        }

        // Start the query with no actual SQL statement
        parent::__construct(QueryType::SELECT, '');
    }

    /**
     * Enables or disables selecting only unique columns using "SELECT DISTINCT"
     *
     * @param bool $value Enable or disable distinct columns.
     * @return $this
     */
    public function distinct(bool $value): self
    {
        $this->_distinct = $value;

        return $this;
    }

    /**
     * Choose the columns to select from.
     *
     * @param string|array|Database_Expression ...$columns Column names or objects.
     * @return $this
     */
    public function select(...$columns): self
    {
        $this->_select = array_merge($this->_select, $columns);

        return $this;
    }

    /**
     * Choose the columns to select from, using an array.
     *
     * @param array $columns List of column names or aliases.
     * @return $this
     */
    public function select_array(array $columns): self
    {
        $this->_select = array_merge($this->_select, $columns);

        return $this;
    }

    /**
     * Choose the tables to select "FROM ..."
     *
     * @param string|array|Database_Expression ...$tables Table names or objects.
     * @return $this
     */
    public function from(...$tables): self
    {
        $this->_from = array_merge($this->_from, $tables);

        return $this;
    }

    /**
     * Adds additional tables to "JOIN ...".
     *
     * @param string|array|Database_Expression $table Table name or object.
     * @param QueryType|null                   $type  Join type (INNER, LEFT, etc).
     * @return $this
     */
    public function join($table, ?QueryType $type = null): self
    {
        $this->_join[] = $this->_last_join = new Database_Query_Builder_Join($table, $type);

        return $this;
    }

    /**
     * Adds "ON ..." conditions for the last created JOIN statement.
     *
     * @param string|array|Database_Expression $c1 Column name or object.
     * @param string                           $op Logic operator.
     * @param string|array|Database_Expression $c2 Column name or object.
     * @return $this
     */
    public function on($c1, string $op, $c2): self
    {
        $this->_last_join->on($c1, $op, $c2);

        return $this;
    }

    /**
     * Adds "USING ..." conditions for the last created JOIN statement.
     *
     * @param string ...$columns Column names.
     * @return $this
     */
    public function using(string ...$columns): self
    {
        call_user_func_array([$this->_last_join, 'using'], $columns);

        return $this;
    }

    /**
     * Creates a "GROUP BY ..." filter.
     *
     * @param string|array|Database_Expression ...$columns Column names or objects.
     * @return $this
     */
    public function group_by(...$columns): self
    {
        $this->_group_by = array_merge($this->_group_by, $columns);

        return $this;
    }

    /**
     * Alias of and_having()
     *
     * @param string|array|Database_Expression $column Column name or object.
     * @param string                           $op     Logic operator.
     * @param mixed                            $value  Column value.
     * @return $this
     */
    public function having($column, string $op, mixed $value = null): self
    {
        return $this->and_having($column, $op, $value);
    }

    /**
     * Creates a new "AND HAVING" condition for the query.
     *
     * @param string|array|Database_Expression $column Column name or object.
     * @param string                           $op     Logic operator.
     * @param mixed                            $value  Column value.
     * @return $this
     */
    public function and_having($column, string $op, mixed $value = null): self
    {
        $this->_having[] = ['AND' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Creates a new "OR HAVING" condition for the query.
     *
     * @param string|array|Database_Expression $column Column name or object.
     * @param string                           $op     Logic operator.
     * @param mixed                            $value  Column value.
     * @return $this
     */
    public function or_having($column, string $op, mixed $value = null): self
    {
        $this->_having[] = ['OR' => [$column, $op, $value]];

        return $this;
    }

    /**
     * Alias of and_having_open()
     *
     * @return $this
     */
    public function having_open(): self
    {
        return $this->and_having_open();
    }

    /**
     * Opens a new "AND HAVING (...)" grouping.
     *
     * @return $this
     */
    public function and_having_open(): self
    {
        $this->_having[] = ['AND' => '('];

        return $this;
    }

    /**
     * Opens a new "OR HAVING (...)" grouping.
     *
     * @return $this
     */
    public function or_having_open(): self
    {
        $this->_having[] = ['OR' => '('];

        return $this;
    }

    /**
     * Closes an open "AND HAVING (...)" grouping.
     *
     * @return $this
     */
    public function having_close(): self
    {
        return $this->and_having_close();
    }

    /**
     * Closes an open "AND HAVING (...)" grouping.
     *
     * @return $this
     */
    public function and_having_close(): self
    {
        $this->_having[] = ['AND' => ')'];

        return $this;
    }

    /**
     * Closes an open "OR HAVING (...)" grouping.
     *
     * @return $this
     */
    public function or_having_close(): self
    {
        $this->_having[] = ['OR' => ')'];

        return $this;
    }

    /**
     * Adds another UNION clause.
     *
     * @param string|Database_Query_Builder_Select $select Table name or SELECT query.
     * @param bool                                 $all    Whether it's a UNION or UNION ALL.
     * @return $this
     * @throws Kohana_Exception
     */
    public function union($select, bool $all = true): self
    {
        if (is_string($select)) {
            $select = DB::select()->from($select);
        }

        if (!$select instanceof Database_Query_Builder_Select) {
            throw new Kohana_Exception('First parameter must be a string or an instance of Database_Query_Builder_Select');
        }

        $this->_union[] = ['select' => $select, 'all' => $all];

        return $this;
    }

    /**
     * Start returning results after "OFFSET ..."
     *
     * @param int|null $number Starting result number or null to reset.
     * @return $this
     */
    public function offset(?int $number): self
    {
        $this->_offset = $number;

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

        // Callback to quote columns
        $quote_column = [$db, 'quote_column'];

        // Callback to quote tables
        $quote_table = [$db, 'quote_table'];

        // Start a selection query
        $query = 'SELECT ';

        if ($this->_distinct) {
            // Select only unique results
            $query .= 'DISTINCT ';
        }

        if (empty($this->_select)) {
            // Select all columns
            $query .= '*';
        } else {
            // Select all columns
            $query .= implode(', ', array_unique(array_map($quote_column, $this->_select)));
        }

        if (!empty($this->_from)) {
            // Set tables to select from
            $query .= ' FROM ' . implode(', ', array_unique(array_map($quote_table, $this->_from)));
        }

        if (!empty($this->_join)) {
            // Add tables to join
            $query .= ' ' . $this->_compile_join($db, $this->_join);
        }

        if (!empty($this->_where)) {
            // Add selection conditions
            $query .= ' WHERE ' . $this->_compile_conditions($db, $this->_where);
        }

        if (!empty($this->_group_by)) {
            // Add grouping
            $query .= ' ' . $this->_compile_group_by($db, $this->_group_by);
        }

        if (!empty($this->_having)) {
            // Add filtering conditions
            $query .= ' HAVING ' . $this->_compile_conditions($db, $this->_having);
        }

        if (!empty($this->_order_by)) {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by($db, $this->_order_by);
        }

        if ($this->_limit !== null) {
            // Add limiting
            $query .= ' LIMIT ' . $this->_limit;
        }

        if ($this->_offset !== null) {
            // Add offsets
            $query .= ' OFFSET ' . $this->_offset;
        }

        if (!empty($this->_union)) {
            $query = '(' . $query . ')';
            foreach ($this->_union as $u) {
                $query .= ' UNION ';
                if ($u['all']) {
                    $query .= 'ALL ';
                }
                $query .= '(' . $u['select']->compile($db) . ')';
            }
        }

        // Instead of assigning to _sql, just return the query
        return $query;
    }

    /**
     * Reset the builder state.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->_select = $this->_from = $this->_join = $this->_where = $this->_group_by = $this->_having = $this->_order_by = $this->_union = [];

        $this->_distinct = false;

        $this->_limit = $this->_offset = $this->_last_join = null;

        $this->_parameters = [];

        return $this;
    }
}
