<?php

declare(strict_types=1);

/**
 * Database query wrapper. See [Parameterized Statements](database/query/parameterized) for usage and examples.
 *
 * @package    Kohana/Database
 * @category   Query
 */
class Kohana_Database_Query
{
    protected readonly QueryType $_type;
    protected readonly string $_sql;
    protected bool $_force_execute = false;
    protected ?int $_lifetime = null;
    protected array $_parameters = [];
    protected bool|string $_as_object = false;
    protected array $_object_params = [];

    /**
     * Creates a new SQL query of the specified type.
     *
     * @param QueryType|int $type Query type: QueryType::SELECT, QueryType::INSERT, etc., or integer equivalent.
     * @param string        $sql  Query string.
     */
    public function __construct(QueryType|int $type, string $sql)
    {
        $this->_type = is_int($type) ? QueryType::from($type) : $type;
        $this->_sql = $sql;
    }

    /**
     * Return the SQL query string.
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            return $this->compile(Database::instance());
        } catch (Exception $e) {
            return Kohana_Exception::text($e);
        }
    }

    /**
     * Get the type of the query.
     *
     * @return QueryType
     */
    public function type(): QueryType
    {
        return $this->_type;
    }

    /**
     * Enables the query to be cached for a specified amount of time.
     *
     * @param int|null $lifetime Number of seconds to cache, 0 deletes it from the cache.
     * @param bool     $force    Whether or not to execute the query during a cache hit.
     * @return $this
     */
    public function cached(?int $lifetime = null, bool $force = false): self
    {
        $this->_lifetime = $lifetime ?? Kohana::$cache_life;
        $this->_force_execute = $force;

        return $this;
    }

    /**
     * Returns results as associative arrays.
     *
     * @return $this
     */
    public function as_assoc(): self
    {
        $clone = clone $this;
        $clone->_as_object = false;
        $clone->_object_params = [];
        return $clone;
    }

    /**
     * Returns results as objects.
     *
     * @param string|bool $class  Classname or true for stdClass.
     * @param array|null  $params Parameters for object construction.
     * @return $this
     */
    public function as_object(string|bool $class = true, ?array $params = null): self
    {
        $clone = clone $this;
        $clone->_as_object = $class;
        $clone->_object_params = $params ?? [];
        return $clone;
    }

    /**
     * Set the value of a parameter in the query.
     *
     * @param string $param Parameter key to replace.
     * @param mixed  $value Value to use.
     * @return $this
     */
    public function param(string $param, mixed $value): self
    {
        $this->_parameters[$param] = $value;
        return $this;
    }

    /**
     * Bind a variable to a parameter in the query.
     *
     * @param string $param Parameter key to replace.
     * @param mixed  $var   Variable to use.
     * @return $this
     */
    public function bind(string $param, mixed &$var): self
    {
        $this->_parameters[$param] = &$var;
        return $this;
    }

    /**
     * Add multiple parameters to the query.
     *
     * @param array $params List of parameters.
     * @return $this
     */
    public function parameters(array $params): self
    {
        $this->_parameters = $params + $this->_parameters;
        return $this;
    }

    /**
     * Compile the SQL query and return it. Replaces any parameters with their
     * given values.
     *
     * @param mixed $db Database instance or name of instance.
     * @return string
     */
    public function compile(mixed $db = null): string
    {
        if (!is_object($db)) {
            $db = Database::instance($db);
        }

        $sql = $this->_sql;

        if (!empty($this->_parameters)) {
            $values = array_map([$db, 'quote'], $this->_parameters);
            $sql = strtr($sql, $values);
        }

        return $sql;
    }

    /**
     * Execute the current query on the given database.
     *
     * @param mixed       $db            Database instance or name of instance.
     * @param string|bool $as_object     Result object classname, true for stdClass or false for array.
     * @param array|null  $object_params Result object constructor arguments.
     * @return mixed      Database_Result for SELECT queries, the insert id for INSERT queries,
     *                    number of affected rows for all other queries.
     */
    public function execute(mixed $db = null, string|bool $as_object = null, ?array $object_params = null): mixed
    {
        try {
            if (!is_object($db)) {
                $db = Database::instance($db);
            }

            $as_object = $as_object ?? $this->_as_object;
            $object_params = $object_params ?? $this->_object_params;

            $sql = $this->compile($db);

            if ($this->_lifetime !== null && $this->_type === QueryType::SELECT) {
                $cache_key = 'Database::query("' . $db . '", "' . $sql . '")';

                if (($result = Kohana::cache($cache_key, null, $this->_lifetime)) !== null && !$this->_force_execute) {
                    return new Database_Result_Cached($result, $sql, $as_object, $object_params);
                }
            }

            // Execute the query
            $result = $db->query($this->_type->value, $sql, $as_object, $object_params);

            if (isset($cache_key) && $this->_lifetime > 0) {
                Kohana::cache($cache_key, $result->as_array(), $this->_lifetime);
            }

            return $result;

        } catch (QueryException $e) {
            // Specific handling for query-related exceptions
            Kohana::$log->add(Log::ERROR, 'QueryException: :message', [':message' => $e->getMessage()]);
            throw new Kohana_Exception('Ошибка выполнения запроса: ' . $e->getMessage(), null, $e);

        } catch (\Exception $e) {
            // Handling for all other exceptions
            Kohana::$log->add(Log::ERROR, 'Exception: :message', [':message' => $e->getMessage()]);
            throw new Kohana_Exception('Произошла ошибка: ' . $e->getMessage(), null, $e);
        }
    }

    /**
     * Create a copy of the current query object.
     *
     * @return static
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * Throws an exception indicating a non-implemented feature.
     *
     * @return never
     */
    protected function notImplemented(): never
    {
        throw new LogicException("This method is not implemented.");
    }
}

/**
 * Enum for query types.
 */
enum QueryType: int
{
    // Query types
    case SELECT = 1;
    case INSERT = 2;
    case UPDATE = 3;
    case DELETE = 4;

    // Join types
    case INNER = 5;
    case LEFT = 6;
    case RIGHT = 7;
    case FULL = 8; // If supported by the database
}
