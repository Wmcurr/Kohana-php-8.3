<?php

declare(strict_types=1);

/**
 * PDO database connection.
 *
 * @package    Kohana/Database
 * @category   Drivers
 * @version    PHP 8.3, MySQL 8
 */
class Kohana_Database_PDO extends Database
{
    // Константы для часто используемых значений
    private const DEFAULT_CHARSET = 'utf8';
    private const FETCH_MODE_ASSOC = PDO::FETCH_ASSOC;

    // Типизированные свойства
    protected string $_identifier = '';

    // Кэш для таблиц и колонок
    private static array $tables_cache = [];
    private static array $columns_cache = [];

    public function __construct(string $name, array $config)
    {
        parent::__construct($name, $config);

        if (isset($this->_config['identifier'])) {
            $this->_identifier = (string) $this->_config['identifier'];
        }
    }

    public function connect(): void
    {
        if ($this->_connection) {
            return;
        }

        extract($this->_config['connection'] + [
            'dsn' => '',
            'username' => null,
            'password' => null,
            'persistent' => false,
        ]);

        unset($this->_config['connection']);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (!empty($persistent)) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        try {
            $this->_connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new Database_Exception(':error', [':error' => $e->getMessage()], $e->getCode());
        }

        if (!empty($this->_config['charset'])) {
            $this->set_charset($this->_config['charset']);
        }
    }

    /**
     * @param string $name
     * @param callable $step
     * @param callable $final
     * @param int $arguments
     * @return bool
     */
    public function create_aggregate(string $name, callable $step, callable $final, int $arguments = -1): bool
    {
        $this->_connection or $this->connect();

        return $this->_connection->sqliteCreateAggregate($name, $step, $final, $arguments);
    }

    /**
     * @param string $name
     * @param callable $callback
     * @param int $arguments
     * @return bool
     */
    public function create_function(string $name, callable $callback, int $arguments = -1): bool
    {
        $this->_connection or $this->connect();

        return $this->_connection->sqliteCreateFunction($name, $callback, $arguments);
    }

    /**
     * Disconnect from the database
     *
     * @return bool
     */
    public function disconnect(): bool
    {
        $this->_connection = null;
        return parent::disconnect();
    }

    /**
     * Set the character set
     *
     * @param string $charset
     * @return void
     */
    public function set_charset(string $charset): void
    {
        $this->_connection or $this->connect();
        $this->_connection->exec('SET NAMES ' . $this->quote($charset));
    }

    /**
     * Execute a query and return the result
     *
     * @param int $type
     * @param string $sql
     * @param bool|string $as_object
     * @param array|null $params
     * @return mixed
     * @throws Database_Exception
     */
        public function query(int $type, string $sql, $as_object = false, ?array $params = null): mixed
    {
        $this->_connection or $this->connect();

        if (Kohana::$profiling) {
            $benchmark = Profiler::start("Database ({$this->_instance})", $sql);
        }

        try {
            $stmt = $params ? $this->_connection->prepare($sql) : $this->_connection->query($sql);
            $params ? $stmt->execute($params) : null;
        } catch (Exception $e) {
            if (isset($benchmark)) {
                Profiler::delete($benchmark);
            }
            throw new Database_Exception(':error [ :query ]', [
                ':error' => $e->getMessage(),
                ':query' => $sql
            ], $e->getCode());
        }

        if (isset($benchmark)) {
            Profiler::stop($benchmark);
        }

        $this->last_query = $sql;

        return match ($type) {
            Database::SELECT => $this->fetchResult($stmt, $as_object, $params, $sql),
            Database::INSERT => [
                $this->_connection->lastInsertId(),
                $stmt->rowCount(),
            ],
            default => $stmt->rowCount(),
        };
    }

    /**
     * Fetch the result based on type
     *
     * @param PDOStatement $stmt
     * @param bool|string $as_object
     * @param array|null $params
     * @param string $sql
     * @return Database_Result_Cached
     */
    private function fetchResult(PDOStatement $stmt, bool|string $as_object, ?array $params, string $sql): Database_Result_Cached
    {
        if ($as_object === false) {
            $stmt->setFetchMode(self::FETCH_MODE_ASSOC);
        } elseif (is_string($as_object)) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $as_object, $params);
        } else {
            $stmt->setFetchMode(PDO::FETCH_CLASS, 'stdClass');
        }

        $result = $stmt->fetchAll();

        return new Database_Result_Cached($result, $sql, $as_object, $params);
    }

    /**
     * Start a transaction
     *
     * @param string|null $mode
     * @return bool
     */
    public function begin(?string $mode = null): bool
    {
        $this->_connection or $this->connect();
        return $this->_connection->beginTransaction();
    }

    /**
     * Commit the transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        $this->_connection or $this->connect();
        return $this->_connection->commit();
    }

    /**
     * Rollback the transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        $this->_connection or $this->connect();
        return $this->_connection->rollBack();
    }

    /**
     * Savepoint in transaction
     *
     * @param string $name
     * @return bool
     */
    public function savepoint(string $name): bool
    {
        $this->_connection or $this->connect();
        return $this->_connection->exec("SAVEPOINT {$name}");
    }

    /**
     * List all tables
     *
     * @param string|null $like
     * @return array
     */
    public function list_tables(?string $like = null): array
    {
        if (isset(self::$tables_cache[$like])) {
            return self::$tables_cache[$like];
        }

        $this->_connection or $this->connect();

        $sql = 'SHOW TABLES';

        if ($like !== null) {
            $sql .= ' LIKE ' . $this->quote($like);
        }

        $result = $this->query(Database::SELECT, $sql, false);

        $tables = [];
        foreach ($result as $row) {
            $tables[] = reset($row);
        }

        self::$tables_cache[$like] = $tables;
        return $tables;
    }

    /**
     * List all columns in a table
     *
     * @param string $table
     * @param string|null $like
     * @param bool $add_prefix
     * @return array
     */
    public function list_columns(string $table, ?string $like = null, bool $add_prefix = true): array
    {
        $cache_key = $table . $like;
        if (isset(self::$columns_cache[$cache_key])) {
            return self::$columns_cache[$cache_key];
        }

        $this->_connection or $this->connect();

        if ($add_prefix) {
            $table = $this->table_prefix() . $table;
        }

        $sql = 'SHOW COLUMNS FROM ' . $this->quote_table($table);

        if ($like !== null) {
            $sql .= ' LIKE ' . $this->quote($like);
        }

        $result = $this->query(Database::SELECT, $sql, false);

        $columns = [];
        foreach ($result as $row) {
            $column = [
                'name' => $row['Field'],
                'type' => $this->mysql_datatype($row['Type']),
                'is_nullable' => ($row['Null'] === 'YES'),
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra'],
            ];
            $columns[$row['Field']] = $column;
        }

        self::$columns_cache[$cache_key] = $columns;
        return $columns;
    }

    /**
     * Parse MySQL data type
     *
     * @param string $type
     * @return array
     */
    public function mysql_datatype(string $type): array
    {
        $types = [];
        if (preg_match('/^([a-z]+)(?:\(([^)]+)\))?/', $type, $matches)) {
            $types['type'] = $matches[1];
            if (isset($matches[2])) {
                $types['length'] = $matches[2];
            }
        }

        return $types;
    }

    /**
     * Escape a value for use in a query
     *
     * @param string $value
     * @return string
     */
    public function escape(string $value): string
    {
        $this->_connection or $this->connect();
        return $this->_connection->quote($value);
    }
}
