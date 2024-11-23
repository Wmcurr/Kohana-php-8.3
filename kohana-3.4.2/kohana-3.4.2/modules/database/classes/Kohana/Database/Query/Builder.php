<?php

declare(strict_types=1);

/**
 * Database query builder. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 */
abstract class Kohana_Database_Query_Builder extends Database_Query
{
    /**
     * Compiles an array of JOIN statements into an SQL partial.
     *
     * @param Database $db    Database instance.
     * @param array    $joins Join statements.
     * @return string
     */
    protected function _compile_join(Database $db, array $joins): string
    {
        $statements = [];

        foreach ($joins as $join) {
            // Compile each of the join statements
            $statements[] = $join->compile($db);
        }

        return implode(' ', $statements);
    }

    /**
     * Compiles an array of conditions into an SQL partial. Used for WHERE and HAVING.
     *
     * @param Database $db         Database instance.
     * @param array    $conditions Condition statements.
     * @return string
     */
    protected function _compile_conditions(Database $db, array $conditions): string
    {
        $last_condition = null;

        $sql = '';
        foreach ($conditions as $group) {
            foreach ($group as $logic => $condition) {
                if ($condition === '(') {
                    if (!empty($sql) && $last_condition !== '(') {
                        // Include logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    $sql .= ')';
                } else {
                    if (!empty($sql) && $last_condition !== '(') {
                        // Add the logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    list($column, $op, $value) = $condition;

                    if ($value === null) {
                        if ($op === '=') {
                            $op = 'IS';
                        } elseif ($op === '!=' || $op === '<>') {
                            $op = 'IS NOT';
                        }
                    }

                    $op = strtoupper($op);

                    if ($op === 'BETWEEN' && is_array($value)) {
                        list($min, $max) = $value;

                        $min = $min instanceof Database_Expression ? $min->compile($db) : $db->quote($min);
                        $max = $max instanceof Database_Expression ? $max->compile($db) : $db->quote($max);

                        $value = $min . ' AND ' . $max;
                    } else {
                        $value = $value instanceof Database_Expression ? $value->compile($db) : $db->quote($value);
                    }

                    $column = $column instanceof Database_Expression ? $column->compile($db) : $db->quote_column($column);

                    $sql .= trim($column . ' ' . $op . ' ' . $value);
                }

                $last_condition = $condition;
            }
        }

        return $sql;
    }

    /**
     * Compiles an array of set values into an SQL partial. Used for UPDATE.
     *
     * @param Database $db     Database instance.
     * @param array    $values Updated values.
     * @return string
     */
    protected function _compile_set(Database $db, array $values): string
    {
        $set = [];
        foreach ($values as $group) {
            list($column, $value) = $group;

            $column = $db->quote_column($column);
            $value = $value instanceof Database_Expression ? $value->compile($db) : $db->quote($value);

            $set[] = $column . ' = ' . $value;
        }

        return implode(', ', $set);
    }

    /**
     * Compiles an array of GROUP BY columns into an SQL partial.
     *
     * @param Database $db      Database instance.
     * @param array    $columns Columns.
     * @return string
     */
    protected function _compile_group_by(Database $db, array $columns): string
    {
        $group = [];

        foreach ($columns as $column) {
            $group[] = $column instanceof Database_Expression ? $column->compile($db) : $db->quote_column($column);
        }

        return 'GROUP BY ' . implode(', ', $group);
    }

    /**
     * Compiles an array of ORDER BY statements into an SQL partial.
     *
     * @param Database $db      Database instance.
     * @param array    $columns Sorting columns.
     * @return string
     */
    protected function _compile_order_by(Database $db, array $columns): string
    {
        $sort = [];
        foreach ($columns as $group) {
            list($column, $direction) = $group;

            $column = $column instanceof Database_Expression ? $column->compile($db) : $db->quote_column($column);

            if ($direction) {
                $direction = strtoupper($direction);

                if (!in_array($direction, ['ASC', 'DESC'])) {
                    throw new Database_Exception('Order direction must be "ASC" or "DESC".');
                }

                $direction = ' ' . $direction;
            }

            $sort[] = $column . $direction;
        }

        return 'ORDER BY ' . implode(', ', $sort);
    }

    /**
     * Reset the current builder status.
     *
     * @return $this
     */
    abstract public function reset(): self;
}
