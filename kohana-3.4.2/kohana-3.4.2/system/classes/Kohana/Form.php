<?php

declare(strict_types=1);

/**
 * Form helper class. Unless otherwise noted, all generated HTML will be made
 * safe using the [HTML::chars] method. This prevents against simple XSS
 * attacks that could otherwise be triggered by inserting HTML characters into
 * form fields.
 *
 * @package    Kohana
 * @category   Helpers
 * @author     Kohana Team
 * @version    Updated for PHP 8.3 compatibility
 */
class Kohana_Form
{
    /**
     * Generates an opening HTML form tag.
     *
     *     // Form will submit back to the current page using POST
     *     echo Form::open();
     *
     *     // Form will submit to 'search' using GET
     *     echo Form::open('search', ['method' => 'get']);
     *
     *     // When "file" inputs are present, you must include the "enctype"
     *     echo Form::open(null, ['enctype' => 'multipart/form-data']);
     *
     * @param   string|null $action     form action, defaults to the current request URI
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function open(string|null $action = null, array $attributes = null): string
    {
        if ($action instanceof Request) {
            // Use the current URI
            $action = $action->uri();
        }

        if (!$action) {
            // Allow empty form actions (submits back to the current url).
            $action = '';
        } elseif (strpos($action, '://') === false) {
            // Make the URI absolute
            $action = URL::site($action);
        }

        // Add the form action to the attributes
        $attributes['action'] = $action;

        // Only accept the default character set
        $attributes['accept-charset'] = Kohana::$charset;

        if (!isset($attributes['method'])) {
            // Use POST method
            $attributes['method'] = 'post';
        }

        return '<form' . HTML::attributes($attributes) . '>';
    }

    /**
     * Creates the closing form tag.
     *
     *     echo Form::close();
     *
     * @return  string
     */
    public static function close(): string
    {
        return '</form>';
    }

    /**
     * Creates a form input. If no type is specified, a "text" type input will
     * be returned.
     *
     *     echo Form::input('username', $username);
     *
     * @param   string      $name       input name
     * @param   string|null $value      input value
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function input(string $name, string|null $value = null, array $attributes = null): string
    {
        // Set the input name
        $attributes['name'] = $name;

        // Set the input value
        $attributes['value'] = $value;

        if (!isset($attributes['type'])) {
            // Default type is text
            $attributes['type'] = 'text';
        }

        return '<input' . HTML::attributes($attributes) . ' />';
    }

    /**
     * Creates a hidden form input.
     *
     *     echo Form::hidden('csrf', $token);
     *
     * @param   string      $name       input name
     * @param   string|null $value      input value
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function hidden(string $name, string|null $value = null, array $attributes = null): string
    {
        $attributes['type'] = 'hidden';

        return Form::input($name, $value, $attributes);
    }

    /**
     * Creates a password form input.
     *
     *     echo Form::password('password');
     *
     * @param   string      $name       input name
     * @param   string|null $value      input value
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function password(string $name, string|null $value = null, array $attributes = null): string
    {
        $attributes['type'] = 'password';

        return Form::input($name, $value, $attributes);
    }

    /**
     * Creates a file upload form input. No input value can be specified.
     *
     *     echo Form::file('image');
     *
     * @param   string      $name       input name
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function file(string $name, array $attributes = null): string
    {
        $attributes['type'] = 'file';

        return Form::input($name, null, $attributes);
    }

    /**
     * Creates a checkbox form input.
     *
     *     echo Form::checkbox('remember_me', 1, (bool) $remember);
     *
     * @param   string      $name       input name
     * @param   string|null $value      input value
     * @param   bool        $checked    checked status
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function checkbox(string $name, string|null $value = null, bool $checked = false, array $attributes = null): string
    {
        $attributes['type'] = 'checkbox';

        if ($checked === true) {
            // Make the checkbox active
            $attributes[] = 'checked';
        }

        return Form::input($name, $value, $attributes);
    }

    /**
     * Creates a radio form input.
     *
     *     echo Form::radio('like_cats', 1, $cats);
     *     echo Form::radio('like_cats', 0, ! $cats);
     *
     * @param   string      $name       input name
     * @param   string|null $value      input value
     * @param   bool        $checked    checked status
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function radio(string $name, string|null $value = null, bool $checked = false, array $attributes = null): string
    {
        $attributes['type'] = 'radio';

        if ($checked === true) {
            // Make the radio active
            $attributes[] = 'checked';
        }

        return Form::input($name, $value, $attributes);
    }

    /**
     * Creates a textarea form input.
     *
     *     echo Form::textarea('about', $about);
     *
     * @param   string      $name           textarea name
     * @param   string      $body           textarea body
     * @param   array|null  $attributes     html attributes
     * @param   bool        $double_encode  encode existing HTML characters
     * @return  string
     */
    public static function textarea(string $name, string $body = '', array $attributes = null, bool $double_encode = true): string
    {
        // Set the input name
        $attributes['name'] = $name;

        // Add default rows and cols attributes (required)
        $attributes += ['rows' => 10, 'cols' => 50];

        return '<textarea' . HTML::attributes($attributes) . '>' . HTML::chars($body, $double_encode) . '</textarea>';
    }

    /**
     * Creates a select form input.
     *
     *     echo Form::select('country', $countries, $country);
     *
     * @param   string      $name       input name
     * @param   array|null  $options    available options
     * @param   mixed       $selected   selected option string, or an array of selected options
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function select(string $name, array $options = null, mixed $selected = null, array $attributes = null): string
    {
        // Set the input name
        $attributes['name'] = $name;

        if (is_array($selected)) {
            // This is a multi-select
            $attributes[] = 'multiple';
        }

        if (!is_array($selected)) {
            if ($selected === null) {
                // Use an empty array
                $selected = [];
            } else {
                // Convert the selected options to an array
                $selected = [(string) $selected];
            }
        }

        if (empty($options)) {
            // There are no options
            $options = '';
        } else {
            foreach ($options as $value => $name) {
                if (is_array($name)) {
                    // Create a new optgroup
                    $group = ['label' => $value];

                    // Create a new list of options
                    $_options = [];

                    foreach ($name as $_value => $_name) {
                        // Force value to be string
                        $_value = (string) $_value;

                        // Create a new attribute set for this option
                        $option = ['value' => $_value];

                        if (in_array($_value, $selected)) {
                            // This option is selected
                            $option[] = 'selected';
                        }

                        // Change the option to the HTML string
                        $_options[] = '<option' . HTML::attributes($option) . '>' . HTML::chars($_name, false) . '</option>';
                    }

                    // Compile the options into a string
                    $_options = "\n" . implode("\n", $_options) . "\n";

                    $options[$value] = '<optgroup' . HTML::attributes($group) . '>' . $_options . '</optgroup>';
                } else {
                    // Force value to be string
                    $value = (string) $value;

                    // Create a new attribute set for this option
                    $option = ['value' => $value];

                    if (in_array($value, $selected)) {
                        // This option is selected
                        $option[] = 'selected';
                    }

                    // Change the option to the HTML string
                    $options[$value] = '<option' . HTML::attributes($option) . '>' . HTML::chars($name, false) . '</option>';
                }
            }

            // Compile the options into a single string
            $options = "\n" . implode("\n", $options) . "\n";
        }

        return '<select' . HTML::attributes($attributes) . '>' . $options . '</select>';
    }

    /**
     * Creates a submit form input.
     *
     *     echo Form::submit(null, 'Login');
     *
     * @param   string|null $name       input name
     * @param   string      $value      input value
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function submit(string|null $name, string $value, array $attributes = null): string
    {
        $attributes['type'] = 'submit';

        return Form::input($name ?? '', $value, $attributes);
    }

    /**
     * Creates a image form input.
     *
     *     echo Form::image(null, null, ['src' => 'media/img/login.png']);
     *
     * @param   string|null $name       input name
     * @param   string|null $value      input value
     * @param   array|null  $attributes html attributes
     * @param   bool        $index      add index file to URL?
     * @return  string
     */
    public static function image(string|null $name, string|null $value, array $attributes = null, bool $index = false): string
    {
        if (!empty($attributes['src'])) {
            if (strpos($attributes['src'], '://') === false) {
                // Add the base URL
                $attributes['src'] = URL::base($index) . $attributes['src'];
            }
        }

        $attributes['type'] = 'image';

        return Form::input($name ?? '', $value, $attributes);
    }

    /**
     * Creates a button form input. Note that the body of a button is NOT escaped,
     * to allow images and other HTML to be used.
     *
     *     echo Form::button('save', 'Save Profile', ['type' => 'submit']);
     *
     * @param   string      $name       input name
     * @param   string      $body       input value
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function button(string $name, string $body, array $attributes = null): string
    {
        // Set the input name
        $attributes['name'] = $name;

        return '<button' . HTML::attributes($attributes) . '>' . $body . '</button>';
    }

    /**
     * Creates a form label. Label text is not automatically translated.
     *
     *     echo Form::label('username', 'Username');
     *
     * @param   string      $input      target input
     * @param   string|null $text       label text
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function label(string $input, string|null $text = null, array $attributes = null): string
    {
        if ($text === null) {
            // Use the input name as the text
            $text = ucwords(preg_replace('/[\W_]+/', ' ', $input));
        }

        // Set the label target
        $attributes['for'] = $input;

        return '<label' . HTML::attributes($attributes) . '>' . $text . '</label>';
    }

    /**
     * Generates a CSRF token input.
     *
     *     echo Form::csrf();
     *
     * @return string
     */
    public static function csrf(): string
    {
        // Получаем токен из класса Security
        $token = Kohana_Security::token();
        // Возвращаем скрытое поле с токеном
        return Form::hidden(Kohana_Security::$token_name, $token);
    }

    /**
     * Creates a color input form element.
     *
     *     echo Form::color('favorite_color', '#ff0000');
     *
     * @param   string      $name       input name
     * @param   string|null $value      input value
     * @param   array|null  $attributes html attributes
     * @return  string
     */
    public static function color(string $name, string|null $value = null, array $attributes = null): string
    {
        $attributes['type'] = 'color';
        return Form::input($name, $value, $attributes);
    }
}
