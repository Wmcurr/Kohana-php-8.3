<?php

declare(strict_types=1);

/**
 * Database-based session class.
 *
 * Sample schema:
 *
 *     CREATE TABLE `sessions` (
 *         `session_id` VARCHAR(255) NOT NULL,
 *         `last_active` BIGINT UNSIGNED NOT NULL, -- Хранение времени с миллисекундами
 *         `contents` TEXT NOT NULL,
 *         PRIMARY KEY (`session_id`),
 *         INDEX (`last_active`)
 *     ) ENGINE = InnoDB;
 *
 * @package    Kohana/Database
 * @category   Session
 */
class Kohana_Session_Database extends Session
{
    // Database instance
    protected Database $_db;
    // Database table name
    protected string $_table = 'sessions';
    // Database column names
    protected array $_columns = [
        'session_id' => 'session_id',
        'last_active' => 'last_active',
        'contents' => 'contents'
    ];
    // Garbage collection requests
    protected int $_gc = 500;
    // The current session id
    protected ?string $_session_id = null;
    // The old session id
    protected ?string $_update_id = null;

    /**
     * Constructs the database session class.
     *
     * @param array $config Configuration settings for the session.
     * @param string|null $id Session ID.
     * @param string $group Database configuration group.
     * @param string $table Table name to store sessions.
     * @param int $gc Number of requests before garbage collection is invoked.
     * @param array $columns Custom column names in the session table.
     */
    public function __construct(
        array $config = [],
        ?string $id = null,
        string $group = Database::$default,
        string $table = 'sessions',
        int $gc = 500,
        array $columns = [
            'session_id' => 'session_id',
            'last_active' => 'last_active',
            'contents' => 'contents'
        ]
    ) {
        // Использование именованных аргументов для улучшенной читаемости
        $this->_db = Database::instance($group);
        $this->_table = $table;
        $this->_gc = $gc;
        $this->_columns = $columns;

        parent::__construct($config, $id);

        if (random_int(0, $this->_gc) === $this->_gc) {
            // Run garbage collection on average every $_gc requests
            $this->_gc();
        }
    }

    /**
     * Returns the current session ID.
     *
     * @return string|null The current session ID or null if not set.
     */
    public function id(): ?string
    {
        return $this->_session_id;
    }

    /**
     * Reads the session data from the database.
     *
     * @param string|null $id The session ID.
     * @return string|null The session data or null if not found.
     */
    protected function _read(?string $id = null): ?string
    {
        // Использование null-safe оператора
        $id ??= Cookie::get($this->_name)?->getValue();

        if ($id !== null) {
            $result = DB::select([$this->_columns['contents'], 'contents'])
                ->from($this->_table)
                ->where($this->_columns['session_id'], '=', ':id')
                ->limit(1)
                ->param(':id', $id)
                ->execute($this->_db);

            if ($result->count() > 0) {
                $this->_session_id = $this->_update_id = $id;
                return (string)$result->get('contents');
            }
        }

        $this->_regenerate();
        return null;
    }

    /**
     * Regenerates the session ID to a new unique value.
     *
     * @return string The new session ID.
     */
    protected function _regenerate(): string
    {
        $query = DB::select($this->_columns['session_id'])
            ->from($this->_table)
            ->where($this->_columns['session_id'], '=', ':id')
            ->limit(1)
            ->bind(':id', $id);

        do {
            $id = str_replace('.', '-', uniqid('', true));
            $result = $query->execute($this->_db);
        } while ($result->count() > 0);

        return $this->_session_id = $id;
    }

    /**
     * Writes the session data to the database.
     *
     * @return bool True if the session was successfully written, false otherwise.
     */
    protected function _write(): bool
    {
        $query = match(true) {
            $this->_update_id === null => DB::insert($this->_table, array_values($this->_columns))
                                            ->values([
                                                ':new_id', 
                                                ':active', 
                                                ':contents'
                                            ]),
            $this->_update_id !== $this->_session_id => DB::update($this->_table)
                                            ->set([$this->_columns['last_active'] => ':active'])
                                            ->set([$this->_columns['contents'] => ':contents'])
                                            ->set([$this->_columns['session_id'] => ':new_id'])
                                            ->where($this->_columns['session_id'], '=', ':old_id'),
            default => DB::update($this->_table)
                                            ->set([$this->_columns['last_active'] => ':active'])
                                            ->set([$this->_columns['contents'] => ':contents'])
                                            ->where($this->_columns['session_id'], '=', ':old_id')
        };

        $query->parameters([
            ':new_id' => $this->_session_id,
            ':old_id' => $this->_update_id,
            ':active' => round(microtime(true) * 1000), // Хранение времени в миллисекундах
            ':contents' => $this->__toString()
        ])->execute($this->_db);

        $this->_update_id = $this->_session_id;
        Cookie::set($this->_name, $this->_session_id, $this->_lifetime);

        return true;
    }

    /**
     * Regenerates the session ID, restarting the session.
     *
     * @return bool True if the session was successfully restarted.
     */
    protected function _restart(): bool
    {
        $this->_regenerate();
        return true;
    }

    /**
     * Destroys the current session, deleting it from the database.
     *
     * @return bool True if the session was successfully destroyed, false otherwise.
     */
    protected function _destroy(): bool
    {
        if ($this->_update_id === null) {
            return true;
        }

        try {
            DB::delete($this->_table)
                ->where($this->_columns['session_id'], '=', ':id')
                ->param(':id', $this->_update_id)
                ->execute($this->_db);

            $this->_update_id = null;
            Cookie::delete($this->_name);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Performs garbage collection, removing expired sessions from the database.
     *
     * @return void
     */
    protected function _gc(): void
    {
        $expires = $this->_lifetime ?? Date::MONTH;

        DB::delete($this->_table)
            ->where($this->_columns['last_active'], '<', ':time')
            ->param(':time', round(microtime(true) * 1000) - $expires * 1000)
            ->execute($this->_db);
    }
}
