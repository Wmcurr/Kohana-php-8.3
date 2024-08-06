<?php

namespace Kohana\Security;

use Kohana_Exception;

/**
 * Provides a common interface to a variety of cryptography engines.
 * Supports multiple instances of cryptography engines through a singleton pattern.
 *
 * @package    Kohana
 * @category   Security
 */
abstract class Kohana_Encrypt
{
    /**
     * @var string Default instance name.
     */
    public static string $default = 'default';

    /**
     * @var array<string, self> Encrypt class instances.
     */
    private static array $instances = [];

    /**
     * Creates a singleton instance of Encrypt. An encryption key must be
     * provided in your "encrypt" configuration file.
     *
     * @param string|null $name   Configuration group name.
     * @param array|null $config  Configuration parameters.
     * @return self
     * @throws Kohana_Exception
     */
    public static function instance(?string $name = null, ?array $config = null): self
    {
        $name ??= self::$default;

        if (!isset(self::$instances[$name])) {
            $config = $config ?? Kohana::$config->load('encrypt')->$name ?? null;

            if ($config === null || !isset($config['driver'])) {
                throw new Kohana_Exception('No encryption driver is defined in the encryption configuration group: :group', [':group' => $name]);
            }

            // Set the driver class name
            $driverClass = 'Encrypt_' . ucfirst($config['driver']);

            if (!class_exists($driverClass)) {
                throw new Kohana_Exception("Encryption driver class :class not found.", [':class' => $driverClass]);
            }

            // Create a new instance
            self::$instances[$name] = new $driverClass($config);
        }

        return self::$instances[$name];
    }

    /**
     * Encrypts a string and returns an encrypted string that can be decoded.
     *
     * @param string $data Data to be encrypted.
     * @return string
     */
    abstract public function encode(string $data): string;

    /**
     * Decrypts an encoded string back to its original value.
     *
     * @param string $data Encoded string to be decrypted.
     * @return string|false
     */
    abstract public function decode(string $data): string|false;
}
