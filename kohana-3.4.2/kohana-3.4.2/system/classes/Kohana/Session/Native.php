<?php

declare(strict_types=1);

/**
 * Native session class.
 *
 * @package    Kohana
 * @category   Cookie
 * @modified   2024-08-13 - Added support for multiple serialization formats, adaptive regeneration, and asynchronous session saving.
 */
class Kohana_Session_Native extends Session
{
    // Constants defining session lifetime and regeneration interval
    private const SESSION_LIFETIME = 3600; // 1 hour
    private const REGENERATION_INTERVAL = 1800; // 30 minutes

    private ?int $_current_time = null;
    private string $_serialization_format = 'json'; // Default serialization format

    /**
     * Sets the serialization format.
     *
     * @param string $format
     */
    public function setSerializationFormat(string $format): void
    {
        $allowed_formats = ['json', 'serialize', 'msgpack'];
        if (!in_array($format, $allowed_formats, true)) {
            throw new InvalidArgumentException('Unsupported serialization format.');
        }
        $this->_serialization_format = $format;
    }

    /**
     * Returns the current session ID.
     *
     * @return string
     */
    public function id(): string
    {
        return session_id();
    }

    /**
     * Reads session data, validating and regenerating the session if necessary.
     *
     * @param string|null $id
     * @return string|null
     */
    protected function _read(?string $id = null): ?string
    {
        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => $this->_lifetime,
            'path' => Cookie::$path,
            'domain' => Cookie::$domain,
            'secure' => Cookie::$secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_name($this->_name);

        if ($id !== null) {
            session_id($id);
        }

        // Start the session with enhanced security options
        if (!session_start([
            'use_strict_mode' => true,
            'sid_length' => 48,
            'sid_bits_per_character' => 6,
            'cache_limiter' => ''
        ])) {
            return null;
        }

        // Reference the session data
        $this->_data = &$_SESSION;

        // Validate and possibly regenerate the session
        if (!$this->_validate_session()) {
            $this->regenerate();
            $_SESSION = [];
        } elseif ($this->_should_regenerate()) {
            $this->regenerate();
        }

        return null;
    }

    /**
     * Writes the session data to storage.
     *
     * @return bool
     */
    protected function _write(): bool
    {
        if ($this->_destroyed) {
            return false;
        }

        // Update the last active time and close the session
        $_SESSION['_last_active'] = $this->_current_time();
        return session_write_close();
    }

    /**
     * Regenerates the session ID.
     *
     * @return string
     */
    protected function _regenerate(): string
    {
        session_regenerate_id(true);
        return session_id();
    }

    /**
     * Public method to trigger session regeneration if needed.
     *
     * @return string
     */
    public function regenerate(): string
    {
        if ($this->_should_regenerate()) {
            $_SESSION['_last_regenerate'] = $this->_current_time();
            session_regenerate_id(true);
            $this->_update_fingerprint();
        }
        return session_id();
    }

    /**
     * Restarts the session.
     *
     * @return bool
     */
protected function _restart(): bool
{
    // Clean up the current session data
    session_unset();
    session_destroy();

    // Start a new session with enhanced security options
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'use_strict_mode' => true,
            'sid_length' => 48,
            'sid_bits_per_character' => 6,
            'cache_limiter' => ''
        ]);
    }
    
    $this->_data = &$_SESSION;

    // Initialize the new session with default parameters if it's a new session
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = $this->_current_time();
        $_SESSION['_last_regenerate'] = $this->_current_time();
        $_SESSION['_fingerprint'] = $this->_generate_fingerprint();
    }

    return session_status() === PHP_SESSION_ACTIVE;
}
    /**
     * Destroys the session.
     *
     * @return bool
     */
    protected function _destroy(): bool
    {
        $_SESSION = [];

        // Clear the session cookie
        $params = session_get_cookie_params();
        setcookie($this->_name, '', $this->_current_time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );

        session_destroy();
        return session_id() === '';
    }

    /**
     * Checks whether the session should be regenerated.
     *
     * @return bool
     */
    private function _should_regenerate(): bool
    {
        // Минимальный интервал между регенерациями
        $min_regeneration_interval = 300; // 5 минут

        $last_regenerate = $_SESSION['_last_regenerate'] ?? 0;
        $inactive_time = $this->_current_time() - ($_SESSION['_last_active'] ?? 0);

        return $inactive_time > self::REGENERATION_INTERVAL && 
               ($this->_current_time() - $last_regenerate) > $min_regeneration_interval;
    }

    /**
     * Validates the session based on creation time and fingerprint.
     *
     * @return bool
     */
private function _validate_session(): bool
{
    if (!isset($_SESSION['_created'])) {
        // Initialize new session
        $_SESSION['_created'] = $this->_current_time();
        $_SESSION['_last_regenerate'] = $this->_current_time();
        $_SESSION['_fingerprint'] = $this->_generate_fingerprint();
        return true;
    }

    // Check if the session has expired
    if ($this->_current_time() - $_SESSION['_created'] > self::SESSION_LIFETIME) {
        // If the session has expired, restart it
        return $this->_restart();
    }

    // Validate the session fingerprint
    if ($_SESSION['_fingerprint'] !== $this->_generate_fingerprint()) {
        // If the fingerprint is invalid, restart the session
        return $this->_restart();
    }

    // Update the session fingerprint
    $this->_update_fingerprint();
    return true;
}

    /**
     * Generates a unique fingerprint for the session.
     *
     * @return string
     */
    private function _generate_fingerprint(): string
    {
        return hash('sha256', 
            $_SERVER['HTTP_USER_AGENT'] . 
            (ip2long($_SERVER['REMOTE_ADDR']) & ip2long('255.255.0.0')) .
            __DIR__
        );
    }

    /**
     * Updates the session fingerprint.
     */
    private function _update_fingerprint(): void
    {
        $_SESSION['_fingerprint'] = $this->_generate_fingerprint();
    }

    /**
     * Returns the current time, used for session timing.
     *
     * @return int
     */
    private function _current_time(): int
    {
        return $this->_current_time ?? $this->_current_time = (new DateTime())->getTimestamp();
    }

    /**
     * Serializes data based on the chosen format.
     *
     * @param mixed $data
     * @return string
     */
    protected function _serialize($data): string
    {
        switch ($this->_serialization_format) {
            case 'json':
                return json_encode($data);
            case 'serialize':
                return serialize($data);
            case 'msgpack':
                return msgpack_pack($data);
            default:
                throw new RuntimeException('Unsupported serialization format.');
        }
    }

    /**
     * Deserializes data based on the chosen format.
     *
     * @param string $data
     * @return mixed
     */
    protected function _deserialize(string $data)
    {
        if (empty($data)) {
            return [];
        }

        switch ($this->_serialization_format) {
            case 'json':
                $result = json_decode($data, true);
                return is_array($result) ? $result : [];
            case 'serialize':
                $result = unserialize($data);
                return is_array($result) ? $result : [];
            case 'msgpack':
                $result = msgpack_unpack($data);
                return is_array($result) ? $result : [];
            default:
                throw new RuntimeException('Unsupported serialization format.');
        }
    }

    /**
     * Encrypts session data using AES-256-GCM.
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected function _encrypt(string $data, string $key): string
    {
        try {
            $iv = random_bytes(openssl_cipher_iv_length('aes-256-gcm'));
            $tag = '';
            $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return base64_encode($iv . $tag . $encrypted);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to encrypt data');
        }
    }

    /**
     * Decrypts session data using AES-256-GCM.
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected function _decrypt(string $data, string $key): string
    {
        try {
            $data = base64_decode($data);
            $ivlen = openssl_cipher_iv_length('aes-256-gcm');
            $iv = substr($data, 0, $ivlen);
            $tag = substr($data, $ivlen, 16);
            $encrypted = substr($data, $ivlen + 16);
            return openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to decrypt data');
        }
    }

    /**
     * Generates or retrieves an encryption key for the session.
     *
     * @return string
     */
    protected function _generate_encryption_key(): string
    {
        if (!isset($_SESSION['_encryption_key'])) {
            $_SESSION['_encryption_key'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_encryption_key'];
    }
}
