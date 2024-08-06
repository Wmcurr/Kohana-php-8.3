<?php
declare(strict_types=1);

/**
 * Security helper class.
 *
 * @package    Kohana
 * @category   Security
 * @version    Updated for PHP 8.3 compatibility
 */
class Kohana_Security
{
    /**
     * @var  string  key name used for token storage
     */
    public static $token_name = 'security_token';

    /**
     * Generate and store a unique token which can be used to help prevent
     * [CSRF](http://wikipedia.org/wiki/Cross_Site_Request_Forgery) attacks.
     *
     *     $token = Security::token();
     *
     * You can insert this token into your forms as a hidden field:
     *
     *     echo Form::hidden('csrf', Security::token());
     *
     * And then check it when using [Validation]:
     *
     *     $array->rules('csrf', [
     *         ['not_empty'],
     *         ['Security::check'],
     *     ]);
     *
     * This provides a basic, but effective, method of preventing CSRF attacks.
     *
     * @param   boolean $new    force a new token to be generated?
     * @return  string
     * @uses    Session::instance
     */
    public static function token(bool $new = false): string
    {
        $session = Session::instance();

        // Get the current token
        $token = $session->get(Security::$token_name);

        if ($new === true || !$token) {
            // Generate a new unique token
            $token = bin2hex(random_bytes(32));

            // Store the new token
            $session->set(Security::$token_name, $token);
        }

        return $token;
    }

    /**
     * Check that the given token matches the currently stored security token.
     *
     *     if (Security::check($token))
     *     {
     *         // Pass
     *     }
     *
     * @param   string  $token  token to check
     * @return  boolean
     * @uses    Security::token
     */
    public static function check(string $token): bool
    {
        return hash_equals(Security::token(), $token);
    }

    /**
     * Compare two hashes in a time-invariant manner.
     * Prevents cryptographic side-channel attacks (timing attacks, specifically)
     *
     * @param string $a cryptographic hash
     * @param string $b cryptographic hash
     * @return boolean
     */
    public static function slow_equals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /**
     * Encodes PHP tags in a string.
     *
     *     $str = Security::encode_php_tags($str);
     *
     * @param   string  $str    string to sanitize
     * @return  string
     */
    public static function encode_php_tags(string $str): string
    {
        return str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);
    }

    /**
     * Sanitize input by escaping special characters.
     *
     *     $str = Security::sanitize($str);
     *
     * @param   string  $str    string to sanitize
     * @return  string
     */
    public static function sanitize(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate if the token is valid for the current session.
     * Adds an extra layer of CSRF protection by validating the session.
     *
     * @param   string  $token  token to validate
     * @return  boolean
     */
    public static function validate_token(string $token): bool
    {
        $session = Session::instance();
        $stored_token = $session->get(Security::$token_name);

        if (!$stored_token) {
            return false;
        }

        return hash_equals($stored_token, $token);
    }

    /**
     * Check for potential XSS attacks by searching for script tags or event handlers.
     *
     * @param   string  $data   data to check
     * @return  boolean
     */
    public static function check_xss(string $data): bool
    {
        return preg_match('/<script\b[^>]*>.*?<\/script>|on\w+\s*=\s*["\'].*?["\']/', $data) === 1;
    }

    /**
     * Generate a more secure random string suitable for cryptographic use.
     *
     * @param   int     $length length of the random string
     * @return  string
     */
    public static function generate_secure_string(int $length = 64): string
    {
        return bin2hex(random_bytes($length));
    }
}
