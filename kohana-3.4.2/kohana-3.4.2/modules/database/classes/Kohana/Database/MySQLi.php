<?php

declare(strict_types=1);

/**
 * MySQLi database connection.
 *
 * Provides MySQLi specific database functions for Kohana framework.
 * Updated to comply with modern PHP and MySQL standards.
 */
class Kohana_Database_MySQLi extends Database
{
    /**
     * @var array Database in use by each connection
     */
    protected static array $_current_databases = [];

    /**
     * @var bool|null Use SET NAMES to set the character set
     */
    protected static ?bool $_set_names = null;

    /**
     * @var string Identifier for this connection within the PHP driver
     */
    protected string $_connection_id;

    /**
     * @var string MySQL uses a backtick for identifiers
     */
    protected string $_identifier = '`';

    /**
     * @var array Cache for list_tables and list_columns
     */
    protected static array $_table_cache = [];
    protected static array $_column_cache = [];

    /**
     * Connects to the MySQLi database.
     *
     * @return void
     * @throws Database_Exception If connection fails
     */
    public function connect(): void
    {
        if ($this->_connection) {
            return;
        }

        if (Database_MySQLi::$_set_names === null) {
            // Determine if we can use mysqli_set_charset(), available in modern versions of PHP and MySQL.
            Database_MySQLi::$_set_names = !function_exists('mysqli_set_charset');
        }

        // Extract the connection parameters, adding required variables
        extract($this->_config['connection'] + [
            'database' => '',
            'hostname' => '',
            'username' => '',
            'password' => '',
            'socket' => '',
            'port' => 3306,
            'ssl' => null,
        ]);

        // Prevent this information from showing up in traces
        unset($this->_config['connection']['username'], $this->_config['connection']['password']);

        try {
            if (is_array($ssl)) {
                $this->_connection = mysqli_init();
                $this->_connection->ssl_set(
                    Arr::get($ssl, 'client_key_path'),
                    Arr::get($ssl, 'client_cert_path'),
                    Arr::get($ssl, 'ca_cert_path'),
                    Arr::get($ssl, 'ca_dir_path'),
                    Arr::get($ssl, 'cipher')
                );
                $this->_connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1); // New option
                $this->_connection->real_connect($hostname, $username, $password, $database, $port, $socket, MYSQLI_CLIENT_SSL);
            } else {
                $this->_connection = new mysqli($hostname, $username, $password, $database, $port, $socket);
                $this->_connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1); // New option
            }
        } catch (Exception $e) {
            // No connection exists
            $this->_connection = null;

            throw new Database_Exception(':error', [':error' => $e->getMessage()], (int)$e->getCode());
        }

        // Unique identifier for the connection
        $this->_connection_id = sha1($hostname . '_' . $username . '_' . $password);

        if (!empty($this->_config['charset'])) {
            // Set the character set
            $this->set_charset($this->_config['charset']);
        }

        if (!empty($this->_config['connection']['variables'])) {
            // Set session variables
            $variables = [];

            foreach ($this->_config['connection']['variables'] as $var => $val) {
                $variables[] = 'SESSION ' . $var . ' = ' . $this->quote($val);
            }

            $this->_connection->query('SET ' . implode(', ', $variables));
        }
    }

    /**
     * Disconnects from the MySQLi database.
     *
     * @return bool True on success, false on failure
     */
    public function disconnect(): bool
    {
        try {
            // Database is assumed disconnected
            $status = true;

            if ($this->_connection instanceof mysqli) {
                if ($status = $this->_connection->close()) {
                    // Clear the connection
                    $this->_connection = null;

                    // Clear the instance
                    parent::disconnect();
                }
            }
        } catch (Exception $e) {
            // Database is probably not disconnected
            $status = !($this->_connection instanceof mysqli);
        }

        return $status;
    }

    /**
     * Sets the character set for the MySQLi connection.
     *
     * @param string $charset Character set to use
     * @return void
     * @throws Database_Exception If setting the charset fails
     */
    public function set_charset(string $charset): void
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if (Database_MySQLi::$_set_names === true) {
            // If mysqli_set_charset() is not available
            $status = (bool)$this->_connection->query('SET NAMES ' . $this->quote($charset));
        } else {
            // Use mysqli_set_charset() if available
            $status = $this->_connection->set_charset($charset);
        }

        if ($status === false) {
            throw new Database_Exception(':error', [':error' => $this->_connection->error], $this->_connection->errno);
        }
    }

    /**
     * Executes a database query.
     *
     * @param int $type Type of query (Database::SELECT, Database::INSERT, etc.)
     * @param string $sql SQL query to execute
     * @param bool|string $as_object Return results as objects or arrays
     * @param array|null $params Parameters to bind to the query
     * @return mixed Query result based on the type of query
     * @throws Database_Exception If the query fails
     */
public function query(int $type, string $sql, $as_object = false, ?array $params = null)
{
    // Убедиться, что соединение установлено
    $this->_connection or $this->connect();

    if (Kohana::$profiling) {
        // Профилирование запроса
        $benchmark = Profiler::start("Database ({$this->_instance})", $sql);
    }

    // Инициализация переменных
    $result = null;
    $stmt = null;

    // Если есть параметры, использовать подготовленный запрос
    if (!empty($params)) {
        // Подготовка выражения
        $stmt = $this->_connection->prepare($sql);
        if ($stmt === false) {
            throw new Database_Exception(':error [ :query ]', [
                ':error' => $this->_connection->error,
                ':query' => $sql,
            ], $this->_connection->errno);
        }

        // Динамическая привязка параметров
        $types = $this->_get_param_types($params);
        if (!$stmt->bind_param($types, ...$params)) {
            throw new Database_Exception(':error [ :query ]', [
                ':error' => $stmt->error,
                ':query' => $sql,
            ], $stmt->errno);
        }

        // Выполнение запроса
        if (!$stmt->execute()) {
            throw new Database_Exception(':error [ :query ]', [
                ':error' => $stmt->error,
                ':query' => $sql,
            ], $stmt->errno);
        }

        // Обработка результата в зависимости от типа запроса
        if ($type === Database::SELECT) {
            $mysqli_result = $stmt->get_result();
            if ($mysqli_result === false) {
                throw new Database_Exception(':error [ :query ]', [
                    ':error' => $stmt->error,
                    ':query' => $sql,
                ], $stmt->errno);
            }

            // Создаём объект Database_MySQLi_Result
            $result = new Database_MySQLi_Result($mysqli_result, $sql, $as_object, $params);
        } elseif ($type === Database::INSERT) {
            $result = [$stmt->insert_id, $stmt->affected_rows];
        } else {
            $result = $stmt->affected_rows;
        }

        // Закрытие выражения
        $stmt->close();
    } else {
        // Выполняем обычный запрос, если нет параметров
        $mysqli_result = $this->_connection->query($sql);

        if ($mysqli_result === false) {
            if (isset($benchmark)) {
                Profiler::delete($benchmark);
            }

            throw new Database_Exception(':error [ :query ]', [
                ':error' => $this->_connection->error,
                ':query' => $sql,
            ], $this->_connection->errno);
        }

        // Обработка результата в зависимости от типа запроса
        if ($type === Database::SELECT) {
            $result = new Database_MySQLi_Result($mysqli_result, $sql, $as_object, $params);
        } elseif ($type === Database::INSERT) {
            $result = [$this->_connection->insert_id, $this->_connection->affected_rows];
        } else {
            $result = $this->_connection->affected_rows;
        }
    }

    if (isset($benchmark)) {
        Profiler::stop($benchmark);
    }

    // Установка последнего запроса
    $this->last_query = $sql;

    return $result;
}


/**
 * Определяет типы данных для bind_param.
 *
 * @param array $params Параметры запроса
 * @return string Типы данных для параметров
 */
private function _get_param_types(array $params): string
{
    return implode('', array_map(function ($param) {
        if ($param === null) {
            return 's'; // null как строка
        }
        if (is_int($param)) {
            return 'i';
        }
        if (is_float($param)) {
            return 'd';
        }
        if (is_string($param)) {
            return 's';
        }
        return 'b';
    }, $params));
}
    /**
     * Gets the datatype for a given SQL type.
     *
     * @param string $type SQL datatype
     * @return array Associative array of type attributes
     */
    public function datatype(string $type): array
    {
        static $types = [
            'blob' => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '65535'],
            'bool' => ['type' => 'bool'],
            'bigint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '18446744073709551615'],
            'datetime' => ['type' => 'string'],
            'decimal unsigned' => ['type' => 'float', 'exact' => true, 'min' => '0'],
            'double' => ['type' => 'float'],
            'double precision unsigned' => ['type' => 'float', 'min' => '0'],
            'double unsigned' => ['type' => 'float', 'min' => '0'],
            'enum' => ['type' => 'string'],
            'fixed' => ['type' => 'float', 'exact' => true],
            'fixed unsigned' => ['type' => 'float', 'exact' => true, 'min' => '0'],
            'float unsigned' => ['type' => 'float', 'min' => '0'],
            'geometry' => ['type' => 'string', 'binary' => true],
            'int unsigned' => ['type' => 'int', 'min' => '0', 'max' => '4294967295'],
            'integer unsigned' => ['type' => 'int', 'min' => '0', 'max' => '4294967295'],
            'longblob' => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '4294967295'],
            'longtext' => ['type' => 'string', 'character_maximum_length' => '4294967295'],
            'mediumblob' => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '16777215'],
            'mediumint' => ['type' => 'int', 'min' => '-8388608', 'max' => '8388607'],
            'mediumint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '16777215'],
            'mediumtext' => ['type' => 'string', 'character_maximum_length' => '16777215'],
            'national varchar' => ['type' => 'string'],
            'numeric unsigned' => ['type' => 'float', 'exact' => true, 'min' => '0'],
            'nvarchar' => ['type' => 'string'],
            'point' => ['type' => 'string', 'binary' => true],
            'real unsigned' => ['type' => 'float', 'min' => '0'],
            'set' => ['type' => 'string'],
            'smallint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '65535'],
            'text' => ['type' => 'string', 'character_maximum_length' => '65535'],
            'tinyblob' => ['type' => 'string', 'binary' => true, 'character_maximum_length' => '255'],
            'tinyint' => ['type' => 'int', 'min' => '-128', 'max' => '127'],
            'tinyint unsigned' => ['type' => 'int', 'min' => '0', 'max' => '255'],
            'tinytext' => ['type' => 'string', 'character_maximum_length' => '255'],
            'year' => ['type' => 'string'],
        ];

        $type = str_replace(' zerofill', '', $type);

        return $types[$type] ?? parent::datatype($type);
    }

    /**
     * Starts a SQL transaction.
     *
     * @param string|null $mode Isolation level
     * @return bool True on success, false on failure
     * @throws Database_Exception If starting the transaction fails
     */
    public function begin(?string $mode = null): bool
    {
        // Ensure the database is connected
        $this->_connection or $this->connect();

        if ($mode && !$this->_connection->query("SET TRANSACTION ISOLATION LEVEL $mode")) {
            throw new Database_Exception(':error', [':error' => $this->_connection->error], $this->_connection->errno);
        }

        return (bool)$this->_connection->query('START TRANSACTION');
    }

    /**
     * Commits a SQL transaction.
     *
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        // Ensure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query('COMMIT');
    }

    /**
     * Rolls back a SQL transaction.
     *
     * @return bool True on success, false on failure
     */
    public function rollback(): bool
    {
        // Ensure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query('ROLLBACK');
    }

    /**
     * Creates a savepoint in a transaction.
     *
     * @param string $name Savepoint name
     * @return bool True on success, false on failure
     */
    public function savepoint(string $name): bool
    {
        // Ensure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query("SAVEPOINT `$name`");
    }

    /**
     * Rolls back to a savepoint in a transaction.
     *
     * @param string $name Savepoint name
     * @return bool True on success, false on failure
     */
    public function rollback_to_savepoint(string $name): bool
    {
        // Ensure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query("ROLLBACK TO SAVEPOINT `$name`");
    }

    /**
     * Releases a savepoint in a transaction.
     *
     * @param string $name Savepoint name
     * @return bool True on success, false on failure
     */
    public function release_savepoint(string $name): bool
    {
        // Ensure the database is connected
        $this->_connection or $this->connect();

        return (bool)$this->_connection->query("RELEASE SAVEPOINT `$name`");
    }

    /**
     * Lists all tables in the database.
     *
     * @param string|null $like Optional pattern to match table names
     * @return array List of table names
     */
    public function list_tables(?string $like = null): array
    {
        if (isset(self::$_table_cache[$like])) {
            return self::$_table_cache[$like];
        }

        if (is_string($like)) {
            // Search for table names
            $result = $this->query(Database::SELECT, 'SHOW TABLES LIKE ' . $this->quote($like), false);
        } else {
            // Find all table names
            $result = $this->query(Database::SELECT, 'SHOW TABLES', false);
        }

        $tables = [];
        foreach ($result as $row) {
            $tables[] = reset($row);
        }

        self::$_table_cache[$like] = $tables;

        return $tables;
    }

    /**
     * Lists all columns in a table.
     *
     * @param string $table Table name
     * @param string|null $like Optional pattern to match column names
     * @param bool $add_prefix Whether to add a table prefix
     * @return array List of column definitions
     */
    public function list_columns(string $table, ?string $like = null, bool $add_prefix = true): array
    {
        if ($add_prefix === true) {
            $table = $this->quote_table($table);
        }

        $cache_key = $table . ':' . $like;
        if (isset(self::$_column_cache[$cache_key])) {
            return self::$_column_cache[$cache_key];
        }

        if (is_string($like)) {
            // Search for column names
            $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM ' . $table . ' LIKE ' . $this->quote($like), false);
        } else {
            // Find all column names
            $result = $this->query(Database::SELECT, 'SHOW FULL COLUMNS FROM ' . $table, false);
        }

        $count = 0;
        $columns = [];
        foreach ($result as $row) {
            [$type, $length] = $this->_parse_type($row['Type']);

            $column = $this->datatype($type);

            $column['column_name'] = $row['Field'];
            $column['column_default'] = $row['Default'];
            $column['data_type'] = $type;
            $column['is_nullable'] = ($row['Null'] === 'YES');
            $column['ordinal_position'] = ++$count;

            switch ($column['type']) {
                case 'float':
                    if (isset($length)) {
                        [$column['numeric_precision'], $column['numeric_scale']] = explode(',', $length);
                    }
                    break;
                case 'int':
                    if (isset($length)) {
                        // MySQL attribute
                        $column['display'] = $length;
                    }
                    break;
                case 'string':
                    match ($column['data_type']) {
                        'binary', 'varbinary' => $column['character_maximum_length'] = $length,
                        'char', 'varchar' => $column['character_maximum_length'] = $length,
                        'text', 'tinytext', 'mediumtext', 'longtext' => $column['collation_name'] = $row['Collation'],
                        'enum', 'set' => [
                            $column['collation_name'] = $row['Collation'],
                            $column['options'] = explode('\',\'', substr($length, 1, -1))
                        ],
                        default => null,
                    };
                    break;
            }

            // MySQL attributes
            $column['comment'] = $row['Comment'];
            $column['extra'] = $row['Extra'];
            $column['key'] = $row['Key'];
            $column['privileges'] = $row['Privileges'];

            $columns[$row['Field']] = $column;
        }

        self::$_column_cache[$cache_key] = $columns;

        return $columns;
    }

    /**
     * Escapes a string value for safe SQL query usage.
     *
     * @param string $value String value to escape
     * @return string Escaped string
     * @throws Database_Exception If escaping the value fails
     */
    public function escape(string $value): string
    {
        // Ensure the database is connected
        $this->_connection or $this->connect();

        // Use prepared statements to escape values
        $stmt = $this->_connection->prepare('SELECT ?');
        if ($stmt === false) {
            throw new Database_Exception(':error', [':error' => $this->_connection->error], $this->_connection->errno);
        }
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $stmt->bind_result($escaped_value);
        $stmt->fetch();
        $stmt->close();

        return "'$escaped_value'";
    }
}
