<?php

declare(strict_types=1);

/**
 * Database query builder for WHERE statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 */
abstract class Kohana_Database_Query_Builder_Where extends Database_Query_Builder
{
    // WHERE ...
    protected array $_where = [];
    // ORDER BY ...
    protected array $_order_by = [];
    // LIMIT ...
    protected ?int $_limit = null;

    /**
     * Alias of and_where()
     *
     * @param string|array|Database_Expression $column Column name or [$column, $alias] or object.
     * @param string                           $op     Logic operator.
     * @param mixed                            $value  Column value.
     * @return $this
     */
    public function where(string|array|Database_Expression $column, string $op, mixed $value): self
    {
        return $this->and_where($column, $op, $value);
    }

    /**
     * Creates a new "AND WHERE" condition for the query.
     *
     * @param string|array|Database_Expression $column Column name or [$column, $alias] or object.
     * @param string                           $op     Logic operator.
     * @param mixed                            $value  Column value.
     * @return $this
     */
    public function and_where(string|array|Database_Expression $column, string $op, mixed $value): self
    {
        $this->_where[] = ['AND' => [$column, $op, $value]];
        return $this;
    }

    /**
     * Creates a new "OR WHERE" condition for the query.
     *
     * @param string|array|Database_Expression $column Column name or [$column, $alias] or object.
     * @param string                           $op     Logic operator.
     * @param mixed                            $value  Column value.
     * @return $this
     */
    public function or_where(string|array|Database_Expression $column, string $op, mixed $value): self
    {
        $this->_where[] = ['OR' => [$column, $op, $value]];
        return $this;
    }

    /**
     * Alias of and_where_open()
     *
     * @return $this
     */
    public function where_open(): self
    {
        return $this->and_where_open();
    }

    /**
     * Opens a new "AND WHERE (...)" grouping.
     *
     * @return $this
     */
    public function and_where_open(): self
    {
        $this->_where[] = ['AND' => '('];
        return $this;
    }

    /**
     * Opens a new "OR WHERE (...)" grouping.
     *
     * @return $this
     */
    public function or_where_open(): self
    {
        $this->_where[] = ['OR' => '('];
        return $this;
    }

    /**
     * Closes an open "WHERE (...)" grouping.
     *
     * @return $this
     */
    public function where_close(): self
    {
        return $this->and_where_close();
    }

    /**
     * Closes an open "WHERE (...)" grouping or removes the grouping when it is empty.
     *
     * @return $this
     */
    public function where_close_empty(): self
    {
        $group = end($this->_where);

        if ($group && reset($group) === '(') {
            array_pop($this->_where);
            return $this;
        }

        return $this->where_close();
    }

    /**
     * Closes an open "AND WHERE (...)" grouping.
     *
     * @return $this
     */
    public function and_where_close(): self
    {
        $this->_where[] = ['AND' => ')'];
        return $this;
    }

    /**
     * Closes an open "OR WHERE (...)" grouping.
     *
     * @return $this
     */
    public function or_where_close(): self
    {
        $this->_where[] = ['OR' => ')'];
        return $this;
    }

    /**
     * Applies sorting with "ORDER BY ..."
     *
     * @param string|array|Database_Expression $column    Column name or [$column, $alias] or object.
     * @param string|null                      $direction Direction of sorting.
     * @return $this
     */
    public function order_by(string|array|Database_Expression $column, ?string $direction = null): self
    {
        $this->_order_by[] = [$column, $direction];
        return $this;
    }

    /**
     * Return up to "LIMIT ..." results
     *
     * @param int|null $number Maximum results to return or null to reset.
     * @return $this
     */
    public function limit(?int $number): self
    {
        $this->_limit = $number;
        return $this;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @param mixed $db Database instance or string.
     * @return string Compiled SQL query.
     */
    public function compile(mixed $db = null): string
    {
        if ($db === null) {
            $db = Database::instance();
        }

        $sql = '';

        if (!empty($this->_where)) {
            $conditions = [];
            foreach ($this->_where as $group) {
                foreach ($group as $logic => $condition) {
                    if (is_array($condition)) {
                        [$column, $op, $value] = $condition;

                        if ($value instanceof Database_Expression) {
                            $value = $value->compile($db);
                        } else {
                            $value = $db->quote($value);
                        }

                        if (is_array($column)) {
                            list($column, $alias) = $column;
                            $column = $db->quote_column($column) . ' AS ' . $db->quote_column($alias);
                        } else {
                            $column = $db->quote_column($column);
                        }

                        $conditions[] = $logic . ' ' . $column . ' ' . $op . ' ' . $value;
                    } else {
                        $conditions[] = $logic . ' ' . $condition;
                    }
                }
            }

            $sql .= ' WHERE ' . trim(implode(' ', $conditions));
        }

        if (!empty($this->_order_by)) {
            $order_by = [];
            foreach ($this->_order_by as [$column, $direction]) {
                $column = $db->quote_column($column);
                $direction = strtoupper($direction);

                $order_by[] = $column . ' ' . ($direction === 'ASC' || $direction === 'DESC' ? $direction : '');
            }

            $sql .= ' ORDER BY ' . implode(', ', $order_by);
        }

        if ($this->_limit !== null) {
            $sql .= ' LIMIT ' . $this->_limit;
        }

        return $sql;
    }
}
