<?php

declare(strict_types=1);

/**
 * Provides a shortcut to get Database related objects for [making queries](../database/query).
 *
 * Shortcut     | Returned Object
 * -------------|---------------
 * [`DB::query()`](#query)   | [Database_Query]
 * [`DB::insert()`](#insert) | [Database_Query_Builder_Insert]
 * [`DB::select()`](#select),<br />[`DB::select_array()`](#select_array) | [Database_Query_Builder_Select]
 * [`DB::update()`](#update) | [Database_Query_Builder_Update]
 * [`DB::delete()`](#delete) | [Database_Query_Builder_Delete]
 * [`DB::expr()`](#expr)     | [Database_Expression]
 *
 * You pass the same parameters to these functions as you pass to the objects they return.
 *
 * @package    Kohana/Database
 * @category   Base
 */
class Kohana_DB
{
    /**
     * Create a new [Database_Query] of the given type.
     *
     *     // Create a new SELECT query
     *     $query = DB::query(QueryType::SELECT, 'SELECT * FROM users');
     *
     *     // Create a new DELETE query
     *     $query = DB::query(QueryType::DELETE, 'DELETE FROM users WHERE id = 5');
     *
     * Specifying the type changes the returned result. When using
     * `QueryType::SELECT`, a [Database_Query_Result] will be returned.
     * `QueryType::INSERT` queries will return the insert id and number of rows.
     * For all other queries, the number of affected rows is returned.
     *
     * @param QueryType $type  type: QueryType::SELECT, QueryType::UPDATE, etc.
     * @param string    $sql   SQL statement
     * @return Database_Query
     */
    public static function query(QueryType $type, string $sql): Database_Query
    {
        return new Database_Query($type, $sql);
    }

    /**
     * Create a new [Database_Query_Builder_Select]. Each argument will be
     * treated as a column. To generate a `foo AS bar` alias, use an array.
     *
     *     // SELECT id, username
     *     $query = DB::select('id', 'username');
     *
     *     // SELECT id AS user_id
     *     $query = DB::select(['id', 'user_id']);
     *
     * @param mixed ...$columns Column name or [$column, $alias] or object.
     * @return Database_Query_Builder_Select
     */
    public static function select(...$columns): Database_Query_Builder_Select
    {
        return new Database_Query_Builder_Select($columns);
    }

    /**
     * Create a new [Database_Query_Builder_Select] from an array of columns.
     *
     *     // SELECT id, username
     *     $query = DB::select_array(['id', 'username']);
     *
     * @param array $columns Columns to select.
     * @return Database_Query_Builder_Select
     */
    public static function select_array(array $columns = []): Database_Query_Builder_Select
    {
        return new Database_Query_Builder_Select($columns);
    }

    /**
     * Create a new [Database_Query_Builder_Insert].
     *
     *     // INSERT INTO users (id, username)
     *     $query = DB::insert('users', ['id', 'username']);
     *
     * @param string|null $table   Table to insert into.
     * @param array|null  $columns List of column names or [$column, $alias] or object.
     * @return Database_Query_Builder_Insert
     */
    public static function insert(?string $table = null, ?array $columns = null): Database_Query_Builder_Insert
    {
        return new Database_Query_Builder_Insert($table, $columns);
    }

    /**
     * Create a new [Database_Query_Builder_Update].
     *
     *     // UPDATE users
     *     $query = DB::update('users');
     *
     * @param string|null $table Table to update.
     * @return Database_Query_Builder_Update
     */
    public static function update(?string $table = null): Database_Query_Builder_Update
    {
        return new Database_Query_Builder_Update($table);
    }

    /**
     * Create a new [Database_Query_Builder_Delete].
     *
     *     // DELETE FROM users
     *     $query = DB::delete('users');
     *
     * @param string|null $table Table to delete from.
     * @return Database_Query_Builder_Delete
     */
    public static function delete(?string $table = null): Database_Query_Builder_Delete
    {
        return new Database_Query_Builder_Delete($table);
    }

    /**
     * Create a new [Database_Expression] which is not escaped. An expression
     * is the only way to use SQL functions within query builders.
     *
     *     $expression = DB::expr('COUNT(users.id)');
     *     $query = DB::update('users')->set(['login_count' => DB::expr('login_count + 1')])->where('id', '=', $id);
     *     $users = ORM::factory('user')->where(DB::expr("BINARY `hash`"), '=', $hash)->find();
     *
     * @param string $string Expression string.
     * @param array  $parameters Unquoted parameter values.
     * @return Database_Expression
     */
    public static function expr(string $string, array $parameters = []): Database_Expression
    {
        return new Database_Expression($string, $parameters);
    }
}
