<?php

declare(strict_types=1);

/**
 * Database Model base class.
 *
 * @package    Kohana/Database
 * @category   Models
 * @author     Kohana 2024
 * @php 8.3
 */
abstract class Kohana_Model_Database extends Model
{
    /**
     * Database instance
     */
    protected Database $_db;

    /**
     * Create a new model instance. A [Database] instance or configuration
     * group name can be passed to the model. If no database is defined, the
     * "default" database group will be used.
     *
     *     $model = Model::factory($name);
     *
     * @param   string   $name  model name
     * @param   Database|string|null $db    Database instance object or string
     * @return  static
     */
    public static function factory(string $name, Database|string|null $db = null): self
    {
        // Add the model prefix
        $class = 'Model_' . $name;

        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Model class $class does not exist.");
        }

        return new $class($db);
    }

    /**
     * Loads the database.
     *
     *     $model = new Foo_Model($db);
     *
     * @param   Database|string|null $db  Database instance object or string
     */
    public function __construct(Database|string|null $db = null)
    {
        $this->_db = $db instanceof Database
            ? $db
            : Database::instance($db ?? Database::$default);
    }
}
