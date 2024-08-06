<?php
declare(strict_types=1);

abstract class Kohana_Model
{
    /**
	 * Model base class. All models should extend this class.
     * Array for storing cached instances of models.
     * @php 8.3
     * @var array
     */
    protected static array $instances = [];

    /**
     * Creates or retrieves a model instance from the cache.
     *
     *     $model = Model::factory('User', $param1, $param2);
     *
     * @param   string  $name   Name of the model
     * @param   mixed   ...$params Constructor parameters
     * @return  self Model instance
     * @throws  Exception If the model class does not exist
     */
    public static function factory(string $name, ...$params): self
    {
        // Add prefix to the model name
        $class = 'Model_' . $name;

        // Check if the model class exists
        if (!class_exists($class)) {
            throw new Exception("Model class {$class} does not exist.");
        }

        // Check if the model instance is in the cache
        if (!isset(self::$instances[$class])) {
            // Create a new instance and store it in the cache
            self::$instances[$class] = new $class(...$params);

            // Trigger event after model creation
            self::onModelCreated(self::$instances[$class]);
        }

        // Return the model instance from the cache
        return self::$instances[$class];
    }

    /**
     * Event triggered after a model instance is created.
     *
     * @param self $instance Model instance
     */
    protected static function onModelCreated(self $instance): void
    {
        // Example: Log model creation
        // Logger::info('Model created: ' . get_class($instance));
    }
}
