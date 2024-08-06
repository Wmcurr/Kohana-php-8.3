<?php

declare(strict_types=1);

/**
 * HTML helper class. Provides generic methods for generating various HTML
 * tags and making output HTML safe.
 *
 * @package    Kohana
 * @category   Helpers
 * @author     Kohana Team
 * @copyright  (c) 2024
 * @php 8.3
 */
class Kohana_HTML
{
    /**
     * @var array Preferred order of attributes
     */
    public static array $attribute_order = [
        'action', 'method', 'type', 'id', 'name', 'value', 'href', 'src',
        'width', 'height', 'cols', 'rows', 'size', 'maxlength', 'rel', 'media',
        'accept-charset', 'accept', 'tabindex', 'accesskey', 'alt', 'title',
        'class', 'style', 'selected', 'checked', 'readonly', 'disabled',
        'aria-label', 'role'
    ];

    /**
     * @var bool Use strict XHTML mode?
     */
    public static bool $strict = true;

    /**
     * @var bool Automatically target external URLs to a new window?
     */
    public static bool $windowed_urls = false;

    /**
     * Convert special characters to HTML entities. All untrusted content
     * should be passed through this method to prevent XSS injections.
     *
     * @param string $value The string to convert
     * @param bool $double_encode Whether to encode existing entities
     * @return string The encoded string
     */
    public static function chars(string $value, bool $double_encode = true): string
    {
        return htmlspecialchars($value, ENT_QUOTES, Kohana::$charset, $double_encode);
    }

    /**
     * Convert all applicable characters to HTML entities. All characters
     * that cannot be represented in HTML with the current character set
     * will be converted to entities.
     *
     * @param string $value The string to convert
     * @param bool $double_encode Whether to encode existing entities
     * @return string The encoded string
     */
    public static function entities(string $value, bool $double_encode = true): string
    {
        return htmlentities($value, ENT_QUOTES, Kohana::$charset, $double_encode);
    }

    /**
     * Create HTML link anchors. Note that the title is not escaped, to allow
     * HTML elements within links (images, etc).
     *
     * @param string $uri URL or URI string
     * @param string|null $title The link text
     * @param array|null $attributes HTML anchor attributes
     * @param string|null $protocol Protocol to pass to URL::base()
     * @param bool $index Include the index page
     * @return string The generated HTML anchor
     */
    public static function anchor(
        string $uri,
        ?string $title = null,
        ?array $attributes = null,
        ?string $protocol = null,
        bool $index = true
    ): string {
        if ($title === null) {
            $title = $uri;
        }

        if ($uri === '') {
            $uri = URL::base($protocol, $index);
        } else {
            if (strpos($uri, '://') !== false) {
                if (self::$windowed_urls && empty($attributes['target'])) {
                    $attributes['target'] = '_blank';
                }
            } elseif ($uri[0] !== '#' && $uri[0] !== '?') {
                $uri = URL::site($uri, $protocol, $index);
            }
        }

        $attributes['href'] = $uri;
        return '<a' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates an HTML anchor to a file.
     *
     * @param string $file The file name
     * @param string|null $title The link text
     * @param array|null $attributes HTML anchor attributes
     * @param string|null $protocol Protocol to pass to URL::base()
     * @param bool $index Include the index page
     * @return string The generated HTML file anchor
     */
    public static function file_anchor(
        string $file,
        ?string $title = null,
        ?array $attributes = null,
        ?string $protocol = null,
        bool $index = false
    ): string {
        if ($title === null) {
            $title = basename($file);
        }

        $attributes['href'] = URL::site($file, $protocol, $index);
        return '<a' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates an email (mailto:) anchor.
     *
     * @param string $email The email address
     * @param string|null $title The link text
     * @param array|null $attributes HTML anchor attributes
     * @return string The generated mailto link
     */
    public static function mailto(
        string $email,
        ?string $title = null,
        ?array $attributes = null
    ): string {
        if ($title === null) {
            $title = $email;
        }

        $encoded_email = implode('', array_map(fn($char) => "&#".ord($char).";", str_split($email)));
        return '<a href="mailto:' . $encoded_email . '"' . self::attributes($attributes) . '>' . $title . '</a>';
    }

    /**
     * Creates a style sheet link element.
     *
     * @param string $file The file name
     * @param array|null $attributes HTML attributes
     * @param string|null $protocol Protocol to pass to URL::base()
     * @param bool $index Include the index page
     * @return string The generated HTML link tag
     */
    public static function style(
        string $file,
        ?array $attributes = null,
        ?string $protocol = null,
        bool $index = false
    ): string {
        if (strpos($file, '://') === false && strpos($file, '//') !== 0) {
            $file = URL::site($file, $protocol, $index);
        }

        $attributes['href'] = $file;
        $attributes['rel'] = $attributes['rel'] ?? 'stylesheet';
        $attributes['type'] = 'text/css';

        return '<link' . self::attributes($attributes) . ' />';
    }

    /**
     * Creates a script link.
     *
     * @param string $file The file name
     * @param array|null $attributes HTML attributes
     * @param string|null $protocol Protocol to pass to URL::base()
     * @param bool $index Include the index page
     * @return string The generated HTML script tag
     */
    public static function script(
        string $file,
        ?array $attributes = null,
        ?string $protocol = null,
        bool $index = false
    ): string {
        if (strpos($file, '://') === false && strpos($file, '//') !== 0) {
            $file = URL::site($file, $protocol, $index);
        }

        $attributes['src'] = $file;
        $attributes['type'] = 'text/javascript';

        return '<script' . self::attributes($attributes) . '></script>';
    }

    /**
     * Creates an image element.
     *
     * @param string $file The file name
     * @param array|null $attributes HTML attributes
     * @param string|null $protocol Protocol to pass to URL::base()
     * @param bool $index Include the index page
     * @return string The generated HTML image tag
     */
    public static function image(
        string $file,
        ?array $attributes = null,
        ?string $protocol = null,
        bool $index = false
    ): string {
        if (strpos($file, '://') === false) {
            $file = URL::site($file, $protocol, $index);
        }

        $attributes['src'] = $file;

        return '<img' . self::attributes($attributes) . ' />';
    }

    /**
     * Creates a video element.
     *
     * @param string $file The file name
     * @param array|null $attributes HTML attributes
     * @return string The generated HTML video tag
     */
    public static function video(string $file, ?array $attributes = null): string
    {
        $attributes['src'] = URL::site($file);
        return '<video' . self::attributes($attributes) . '></video>';
    }

    /**
     * Creates an audio element.
     *
     * @param string $file The file name
     * @param array|null $attributes HTML attributes
     * @return string The generated HTML audio tag
     */
    public static function audio(string $file, ?array $attributes = null): string
    {
        $attributes['src'] = URL::site($file);
        return '<audio' . self::attributes($attributes) . '></audio>';
    }

    /**
     * Creates a source element for video or audio.
     *
     * @param string $file The file name
     * @param string $type The MIME type of the file
     * @param array|null $attributes HTML attributes
     * @return string The generated HTML source tag
     */
    public static function source(string $file, string $type, ?array $attributes = null): string
    {
        $attributes['src'] = URL::site($file);
        $attributes['type'] = $type;
        return '<source' . self::attributes($attributes) . ' />';
    }

    /**
     * Creates an HTML form element.
     *
     * @param string $action The form action URL
     * @param string $method The form method (get, post, etc.)
     * @param array|null $attributes HTML attributes
     * @return string The generated HTML form tag
     */
    public static function form(string $action, string $method = 'post', ?array $attributes = null): string
    {
        $attributes['action'] = URL::site($action);
        $attributes['method'] = $method;
        return '<form' . self::attributes($attributes) . '>';
    }

    /**
     * Compiles an array of HTML attributes into an attribute string.
     * Attributes are sorted by HTML::$attribute_order for consistency.
     *
     * @param array|null $attributes The attribute list
     * @return string The compiled attribute string
     */
    public static function attributes(?array $attributes = null): string
    {
        if (empty($attributes)) {
            return '';
        }

        $sorted = [];
        foreach (self::$attribute_order as $key) {
            if (isset($attributes[$key])) {
                $sorted[$key] = $attributes[$key];
            }
        }

        $attributes = $sorted + $attributes;

        $compiled = '';
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_int($key)) {
                $key = $value;

                if (!self::$strict) {
                    $value = false;
                }
            }

            $compiled .= ' ' . $key;

            if ($value || self::$strict) {
                $compiled .= '="' . self::chars((string)$value) . '"';
            }
        }

        return $compiled;
    }

    /**
     * Adds a new attribute to the preferred order list.
     *
     * @param string $attribute The attribute name
     */
    public static function addAttribute(string $attribute): void
    {
        if (!in_array($attribute, self::$attribute_order)) {
            self::$attribute_order[] = $attribute;
        }
    }

    /**
     * Adds a class to the list of HTML classes in the attributes array.
     *
     * @param array &$attributes The attributes array
     * @param string $class The class name to add
     */
    public static function addClass(array &$attributes, string $class): void
    {
        $attributes['class'] = isset($attributes['class']) ? $attributes['class'] . ' ' . $class : $class;
    }

    /**
     * Removes a class from the list of HTML classes in the attributes array.
     *
     * @param array &$attributes The attributes array
     * @param string $class The class name to remove
     */
    public static function removeClass(array &$attributes, string $class): void
    {
        if (isset($attributes['class'])) {
            $attributes['class'] = trim(str_replace($class, '', $attributes['class']));
        }
    }

    /**
     * Adds a style to the style attribute in the attributes array.
     *
     * @param array &$attributes The attributes array
     * @param string $style The style to add
     */
    public static function addStyle(array &$attributes, string $style): void
    {
        $attributes['style'] = isset($attributes['style']) ? $attributes['style'] . '; ' . $style : $style;
    }

    /**
     * Removes a style from the style attribute in the attributes array.
     *
     * @param array &$attributes The attributes array
     * @param string $style The style to remove
     */
    public static function removeStyle(array &$attributes, string $style): void
    {
        if (isset($attributes['style'])) {
            $attributes['style'] = trim(str_replace($style, '', $attributes['style']));
        }
    }
}
