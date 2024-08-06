<?php

declare(strict_types=1);

/**
 * Validation rules.
 *
 * @package    Kohana
 * @category   Security
 * @php 8.3
 */
 namespace Kohana\Validation;
 
class Valid
{
    /**
     * Checks if a field is not empty.
     *
     * @param mixed $value The value to check.
     * @return bool True if the value is not empty, otherwise false.
     */
    public static function not_empty(mixed $value): bool
    {
        if ($value instanceof ArrayObject) {
            // Get the array from the ArrayObject
            $value = $value->getArrayCopy();
        }

        // Value cannot be null, false, '', or an empty array
        return !in_array($value, [null, false, '', []], true);
    }

    /**
     * Checks a field against a regular expression.
     *
     * @param string $value The value to check.
     * @param string $expression The regular expression to match.
     * @return bool True if the value matches the regular expression, otherwise false.
     */
    public static function regex(string $value, string $expression): bool
    {
        return (bool) preg_match($expression, $value);
    }

    /**
     * Checks that a field is long enough.
     *
     * @param string $value The value to check.
     * @param int $length The minimum length required.
     * @return bool True if the value is long enough, otherwise false.
     */
    public static function min_length(string $value, int $length): bool
    {
        return mb_strlen($value) >= $length;
    }

    /**
     * Checks that a field is short enough.
     *
     * @param string $value The value to check.
     * @param int $length The maximum length allowed.
     * @return bool True if the value is short enough, otherwise false.
     */
    public static function max_length(string $value, int $length): bool
    {
        return mb_strlen($value) <= $length;
    }

    /**
     * Checks that a field is exactly the right length.
     *
     * @param string $value The value to check.
     * @param int|array $length The exact length required, or array of valid lengths.
     * @return bool True if the value is the right length, otherwise false.
     */
    public static function exact_length(string $value, int|array $length): bool
    {
        if (is_array($length)) {
            foreach ($length as $strlen) {
                if (mb_strlen($value) === $strlen) {
                    return true;
                }
            }
            return false;
        }

        return mb_strlen($value) === $length;
    }

    /**
     * Checks that a field is exactly the value required.
     *
     * @param string $value The value to check.
     * @param string $required The required value.
     * @return bool True if the value is exactly the required value, otherwise false.
     */
    public static function equals(string $value, string $required): bool
    {
        return ($value === $required);
    }

    /**
     * Check an email address for correct format.
     *
     * @link http://www.iamcal.com/publish/articles/php/parsing_email/
     * @link http://www.w3.org/Protocols/rfc822/
     *
     * @param string $email The email address to check.
     * @param bool $strict Whether to enforce strict RFC compatibility.
     * @return bool True if the email address is valid, otherwise false.
     */
    public static function email(string $email, bool $strict = false): bool
    {
        if (mb_strlen($email) > 254) {
            return false;
        }

        if ($strict) {
            $qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
            $dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
            $atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
            $pair = '\\x5c[\\x00-\\x7f]';

            $domain_literal = "\\x5b($dtext|$pair)*\\x5d";
            $quoted_string = "\\x22($qtext|$pair)*\\x22";
            $sub_domain = "($atom|$domain_literal)";
            $word = "($atom|$quoted_string)";
            $domain = "$sub_domain(\\x2e$sub_domain)*";
            $local_part = "$word(\\x2e$word)*";

            $expression = "/^$local_part\\x40$domain$/D";
        } else {
            $expression = '/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})$/iD';
        }

        return (bool) preg_match($expression, $email);
    }

    /**
     * Validate the domain of an email address by checking if the domain has a valid MX record.
     *
     * @param string $email The email address to check.
     * @return bool True if the domain has a valid MX record, otherwise false.
     */
    public static function email_domain(string $email): bool
    {
        if (!self::not_empty($email)) {
            return false; // Empty fields cause issues with checkdnsrr()
        }

        // Check if the email domain has a valid MX record
        return (bool) checkdnsrr(preg_replace('/^[^@]++@/', '', $email), 'MX');
    }

    /**
     * Validate a URL.
     *
     * @param string $url The URL to check.
     * @return bool True if the URL is valid, otherwise false.
     */
    public static function url(string $url): bool
    {
        // Based on http://www.apps.ietf.org/rfc/rfc1738.html#sec-5
        if (!preg_match(
                '~^

                # scheme
                [-a-z0-9+.]++://

                # username:password (optional)
                (?:
                    [-a-z0-9$_.+!*\'(),;?&=%]++   # username
                    (?::[-a-z0-9$_.+!*\'(),;?&=%]++)? # password (optional)
                    @
                )?

                (?:
                    # ip address
                    \d{1,3}+(?:\.\d{1,3}+){3}+

                    | # or

                    # hostname (captured)
                    (
                        (?!-)[-a-z0-9]{1,63}+(?<!-)
                        (?:\.(?!-)[-a-z0-9]{1,63}+(?<!-)){0,126}+
                    )
                )

                # port (optional)
                (?::\d{1,5}+)?

                # path (optional)
                (?:/.*)?

                $~iDx', $url, $matches)) {
            return false;
        }

        // We matched an IP address
        if (!isset($matches[1])) {
            return true;
        }

        // Check maximum length of the whole hostname
        // http://en.wikipedia.org/wiki/Domain_name#cite_note-0
        if (strlen($matches[1]) > 253) {
            return false;
        }

        // An extra check for the top level domain
        // It must start with a letter
        $tld = ltrim(substr($matches[1], (int) strrpos($matches[1], '.')), '.');
        return ctype_alpha($tld[0]);
    }

    /**
     * Validate an IP.
     *
     * @param string $ip The IP address to check.
     * @param bool $allow_private Whether to allow private IP networks.
     * @return bool True if the IP address is valid, otherwise false.
     */
    public static function ip(string $ip, bool $allow_private = true): bool
    {
        // Do not allow reserved addresses
        $flags = FILTER_FLAG_NO_RES_RANGE;

        if (!$allow_private) {
            // Do not allow private or reserved addresses
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
    }

    /**
     * Validates a credit card number, with a Luhn check if possible.
     *
     * @param string $number The credit card number to check.
     * @param string|array|null $type The card type, or an array of card types.
     * @return bool True if the credit card number is valid, otherwise false.
     */
    public static function credit_card(string $number, string|array $type = null): bool
    {
        // Remove all non-digit characters from the number
        $number = preg_replace('/\D+/', '', $number);
        if ($number === '') {
            return false;
        }

        if (is_null($type)) {
            // Use the default type
            $type = 'default';
        } elseif (is_array($type)) {
            foreach ($type as $t) {
                // Test each type for validity
                if (self::credit_card($number, $t)) {
                    return true;
                }
            }
            return false;
        }

        $cards = Kohana::$config->load('credit_cards');

        // Check card type
        $type = strtolower($type);

        if (!isset($cards[$type])) {
            return false;
        }

        // Check card number length
        $length = strlen($number);

        // Validate the card length by the card type
        if (!in_array($length, preg_split('/\D+/', $cards[$type]['length']))) {
            return false;
        }

        // Check card number prefix
        if (!preg_match('/^' . $cards[$type]['prefix'] . '/', $number)) {
            return false;
        }

        // No Luhn check required
        if ($cards[$type]['luhn'] === false) {
            return true;
        }

        return self::luhn($number);
    }

    /**
     * Validate a number against the Luhn (mod10) formula.
     *
     * @param string $number The number to check.
     * @return bool True if the number is valid, otherwise false.
     */
    public static function luhn(string $number): bool
    {
        // Force the value to be a string as this method uses string functions.
        // Converting to an integer may pass PHP_INT_MAX and result in an error!
        $number = (string) $number;

        if (!ctype_digit($number)) {
            // Luhn can only be used on numbers!
            return false;
        }

        // Check number length
        $length = strlen($number);

        // Checksum of the card number
        $checksum = 0;

        for ($i = $length - 1; $i >= 0; $i -= 2) {
            // Add up every 2nd digit, starting from the right
            $checksum += substr($number, $i, 1);
        }

        for ($i = $length - 2; $i >= 0; $i -= 2) {
            // Add up every 2nd digit doubled, starting from the right
            $double = substr($number, $i, 1) * 2;

            // Subtract 9 from the double where value is greater than 10
            $checksum += ($double >= 10) ? ($double - 9) : $double;
        }

        // If the checksum is a multiple of 10, the number is valid
        return ($checksum % 10 === 0);
    }

    /**
     * Checks if a phone number is valid.
     *
     * @param string $number The phone number to check.
     * @param array|null $lengths The valid lengths for the phone number.
     * @return bool True if the phone number is valid, otherwise false.
     */
    public static function phone(string $number, ?array $lengths = null): bool
    {
        if (!is_array($lengths)) {
            $lengths = [7, 10, 11];
        }

        // Remove all non-digit characters from the number
        $number = preg_replace('/\D+/', '', $number);

        // Check if the number is within range
        return in_array(strlen($number), $lengths);
    }

    /**
     * Tests if a string is a valid date string.
     *
     * @param string $str The date string to check.
     * @return bool True if the date string is valid, otherwise false.
     */
     ublic static function date(string $str): bool
    {
    try {
        $date = new DateTime($str);
        return true;
    } catch (Exception $e) {
        return false;
    }
    }


    /**
     * Checks whether a string consists of alphabetical characters only.
     *
     * @param string $str The string to check.
     * @param bool $utf8 Whether to enforce UTF-8 compatibility.
     * @return bool True if the string consists of alphabetical characters only, otherwise false.
     */
    public static function alpha(string $str, bool $utf8 = false): bool
    {
        if ($utf8) {
            return (bool) preg_match('/^\pL++$/uD', $str);
        }

        return ctype_alpha($str);
    }

    /**
     * Checks whether a string consists of alphabetical characters and numbers only.
     *
     * @param string $str The string to check.
     * @param bool $utf8 Whether to enforce UTF-8 compatibility.
     * @return bool True if the string consists of alphabetical characters and numbers only, otherwise false.
     */
    public static function alpha_numeric(string $str, bool $utf8 = false): bool
    {
        if ($utf8) {
            return (bool) preg_match('/^[\pL\pN]++$/uD', $str);
        }

        return ctype_alnum($str);
    }

    /**
     * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
     *
     * @param string $str The string to check.
     * @param bool $utf8 Whether to enforce UTF-8 compatibility.
     * @return bool True if the string consists of alphabetical characters, numbers, underscores and dashes only, otherwise false.
     */
    public static function alpha_dash(string $str, bool $utf8 = false): bool
    {
        $regex = $utf8 ? '/^[-\pL\pN_]++$/uD' : '/^[-a-z0-9_]++$/iD';

        return (bool) preg_match($regex, $str);
    }

    /**
     * Checks whether a string consists of digits only (no dots or dashes).
     *
     * @param string $str The string to check.
     * @param bool $utf8 Whether to enforce UTF-8 compatibility.
     * @return bool True if the string consists of digits only, otherwise false.
     */
    public static function digit(string $str, bool $utf8 = false): bool
    {
        if ($utf8) {
            return (bool) preg_match('/^\pN++$/uD', $str);
        }

        return (is_int($str) && $str >= 0) || ctype_digit($str);
    }

    /**
     * Checks whether a string is a valid number (negative and decimal numbers allowed).
     *
     * @param string $str The string to check.
     * @return bool True if the string is a valid number, otherwise false.
     */
    public static function numeric(string $str): bool
    {
        // Get the decimal point for the current locale
        [$decimal] = array_values(localeconv());

        // A lookahead is used to make sure the string contains at least one digit (before or after the decimal point)
        return (bool) preg_match('/^-?+(?=.*[0-9])[0-9]*+' . preg_quote($decimal) . '?+[0-9]*+$/D', $str);
    }

    /**
     * Tests if a number is within a range.
     *
     * @param int $number The number to check.
     * @param int $min The minimum value.
     * @param int $max The maximum value.
     * @param int|null $step The increment size.
     * @return bool True if the number is within the range, otherwise false.
     */
    public static function range(int $number, int $min, int $max, ?int $step = null): bool
    {
        if ($number < $min || $number > $max) {
            // Number is outside of range
            return false;
        }

        if (!$step) {
            // Default to steps of 1
            $step = 1;
        }

        // Check step requirements
        return (($number - $min) % $step === 0);
    }

    /**
     * Checks if a string is a proper decimal format. Optionally, a specific
     * number of digits can be checked too.
     *
     * @param string $str The number to check.
     * @param int $places The number of decimal places.
     * @param int|null $digits The number of digits.
     * @return bool True if the string is a proper decimal format, otherwise false.
     */
    public static function decimal(string $str, int $places = 2, ?int $digits = null): bool
    {
        $digitsPattern = $digits > 0 ? '{' . $digits . '}' : '+';

        // Get the decimal point for the current locale
        [$decimal] = array_values(localeconv());

        return (bool) preg_match('/^[+-]?[0-9]' . $digitsPattern . preg_quote($decimal) . '[0-9]{' . $places . '}$/D', $str);
    }

    /**
     * Checks if a string is a proper hexadecimal HTML color value.
     *
     * @param string $str The input string.
     * @return bool True if the string is a valid color, otherwise false.
     */
    public static function color(string $str): bool
    {
        return (bool) preg_match('/^#?+[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $str);
    }

    /**
     * Checks if a field matches the value of another field.
     *
     * @param array $array The array of values.
     * @param string $field The field name.
     * @param string $match The field name to match.
     * @return bool True if the fields match, otherwise false.
     */
    public static function matches(array $array, string $field, string $match): bool
    {
        return ($array[$field] === $array[$match]);
    }

    /**
     * Validates that a value is a valid UUID.
     *
     * @param string $value The value to check.
     * @return bool True if the value is a valid UUID, otherwise false.
     */
    public static function uuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[4][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $value);
    }

    /**
     * Validates that a value is a valid JSON string.
     *
     * @param string $value The value to check.
     * @return bool True if the value is a valid JSON string, otherwise false.
     */
    public static function json(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Validates that a value is a valid Base64 encoded string.
     *
     * @param string $value The value to check.
     * @return bool True if the value is a valid Base64 encoded string, otherwise false.
     */
    public static function base64(string $value): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value);
    }

    /**
     * Validates a file's MIME type.
     *
     * @param string $filePath The path to the file.
     * @param string $mimeType The expected MIME type.
     * @return bool True if the file's MIME type matches the expected MIME type, otherwise false.
     */
    public static function mime_type(string $filePath, string $mimeType): bool
    {
        return mime_content_type($filePath) === $mimeType;
    }

    /**
     * Validates that a value is within an array of allowed values (enum).
     *
     * @param mixed $value The value to check.
     * @param array $allowedValues The array of allowed values.
     * @return bool True if the value is within the array of allowed values, otherwise false.
     */
    public static function in_array(mixed $value, array $allowedValues): bool
    {
        return in_array($value, $allowedValues, true);
    }

    /**
     * Validates a password based on various criteria.
     *
     * @param string $password The password to check.
     * @param array $criteria Array of criteria for password validation.
     * @return bool True if the password meets all criteria, otherwise false.
     */
    public static function password(string $password, array $criteria = []): bool
    {
        $minLength = $criteria['min_length'] ?? 8;
        $requireSpecialChars = $criteria['require_special_chars'] ?? true;
        $requireNumbers = $criteria['require_numbers'] ?? true;
        $requireUppercase = $criteria['require_uppercase'] ?? true;
        $requireLowercase = $criteria['require_lowercase'] ?? true;

        if (mb_strlen($password) < $minLength) {
            return false;
        }

        if ($requireSpecialChars && !preg_match('/[\W]/', $password)) {
            return false;
        }

        if ($requireNumbers && !preg_match('/\d/', $password)) {
            return false;
        }

        if ($requireUppercase && !preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if ($requireLowercase && !preg_match('/[a-z]/', $password)) {
            return false;
        }

        return true;
    }
}
