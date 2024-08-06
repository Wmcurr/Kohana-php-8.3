<?php

declare(strict_types=1);

/**
 * Array and variable validation.
 *
 * @package    Kohana
 * @category   Security
 * @php 8.3
 */
 namespace Kohana\Validation;
 
class Kohana_Validation implements \ArrayAccess
{
    /**
     * Creates a new Validation instance.
     *
     * @param array $array Array to use for validation.
     * @return Kohana_Validation
     */
    public static function factory(array $array): self
    {
        return new self($array);
    }

    // Bound values
    protected array $_bound = [];
    // Field rules
    protected array $_rules = [];
    // Field labels
    protected array $_labels = [];
    // Rules that are executed even when the value is empty
    protected array $_empty_rules = ['not_empty', 'matches'];
    // Error list, field => rule
    protected array $_errors = [];
    // Array to validate
    protected array $_data = [];

    /**
     * Sets the unique "any field" key and creates an ArrayObject from the passed array.
     *
     * @param array $array Array to validate.
     */
    public function __construct(array $array)
    {
        $this->_data = $array;
    }

    /**
     * Throws an exception because Validation is read-only. Implements ArrayAccess method.
     *
     * @throws Kohana_Exception
     * @param string $offset Key to set.
     * @param mixed $value Value to set.
     */
    public function offsetSet($offset, $value): void
    {
        throw new Kohana_Exception('Validation objects are read-only.');
    }

    /**
     * Checks if key is set in array data. Implements ArrayAccess method.
     *
     * @param string $offset Key to check.
     * @return bool Whether the key is set.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->_data[$offset]);
    }

    /**
     * Throws an exception because Validation is read-only. Implements ArrayAccess method.
     *
     * @throws Kohana_Exception
     * @param string $offset Key to unset.
     */
    public function offsetUnset($offset): void
    {
        throw new Kohana_Exception('Validation objects are read-only.');
    }

    /**
     * Gets a value from the array data. Implements ArrayAccess method.
     *
     * @param string $offset Key to return.
     * @return mixed Value from array.
     */
    public function offsetGet($offset): mixed
    {
        return $this->_data[$offset];
    }

    /**
     * Copies the current rules to a new array.
     *
     * @param array $array New data set.
     * @return Kohana_Validation
     */
    public function copy(array $array): self
    {
        // Create a copy of the current validation set
        $copy = clone $this;

        // Replace the data set
        $copy->_data = $array;

        return $copy;
    }

    /**
     * Returns the array of data to be validated.
     *
     * @return array
     */
    public function data(): array
    {
        return $this->_data;
    }

    /**
     * Sets or overwrites the label name for a field.
     *
     * @param string $field Field name.
     * @param string $label Label.
     * @return $this
     */
    public function label(string $field, string $label): self
    {
        // Set the label for this field
        $this->_labels[$field] = $label;

        return $this;
    }

    /**
     * Sets labels using an array.
     *
     * @param array $labels List of field => label names.
     * @return $this
     */
    public function labels(array $labels): self
    {
        $this->_labels = $labels + $this->_labels;

        return $this;
    }

    /**
     * Overwrites or appends rules to a field. Each rule will be executed once.
     *
     * @param string $field Field name.
     * @param callable $rule Valid PHP callback or closure.
     * @param array|null $params Extra parameters for the rule.
     * @return $this
     */
    public function rule(string $field, callable $rule, ?array $params = null): self
    {
        if ($params === null) {
            // Default to [':value']
            $params = [':value'];
        }

        if ($field !== true && !isset($this->_labels[$field])) {
            // Set the field label to the field name
            $this->_labels[$field] = $field;
        }

        // Store the rule and params for this rule
        $this->_rules[$field][] = [$rule, $params];

        return $this;
    }

    /**
     * Add rules using an array.
     *
     * @param string $field Field name.
     * @param array $rules List of callbacks.
     * @return $this
     */
    public function rules(string $field, array $rules): self
    {
        foreach ($rules as $rule) {
            $this->rule($field, $rule[0], $rule[1] ?? []);
        }

        return $this;
    }

    /**
     * Bind a value to a parameter definition.
     *
     * @param string|array $key Variable name or an array of variables.
     * @param mixed $value Value.
     * @return $this
     */
    public function bind(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $name => $val) {
                $this->_bound[$name] = $val;
            }
        } else {
            $this->_bound[$key] = $value;
        }

        return $this;
    }

    /**
     * Executes all validation rules.
     *
     * @return bool
     */
    public function check(): bool
    {
        if (Kohana::$profiling === true) {
            // Start a new benchmark
            $benchmark = Profiler::start('Validation', __FUNCTION__);
        }

        // New data set
        $data = $this->_errors = [];

        // Store the original data because this class should not modify it post-validation
        $original = $this->_data;

        // Get a list of the expected fields
        $expected = array_merge(array_keys($original), array_keys($this->_labels));

        // Import the rules locally
        $rules = $this->_rules;

        foreach ($expected as $field) {
            // Use the submitted value or null if no data exists
            $data[$field] = $this->_data[$field] ?? null;

            if (isset($rules[true])) {
                if (!isset($rules[$field])) {
                    // Initialize the rules for this field
                    $rules[$field] = [];
                }

                // Append the rules
                $rules[$field] = array_merge($rules[$field], $rules[true]);
            }
        }

        // Overload the current array with the new one
        $this->_data = $data;

        // Remove the rules that apply to every field
        unset($rules[true]);

        // Bind the validation object to :validation
        $this->bind(':validation', $this);
        // Bind the data to :data
        $this->bind(':data', $this->_data);

        // Execute the rules
        foreach ($rules as $field => $set) {
            // Get the field value
            $value = $this[$field];

            // Bind the field name and value to :field and :value respectively
            $this->bind([
                ':field' => $field,
                ':value' => $value,
            ]);

            foreach ($set as $array) {
                // Rules are defined as [$rule, $params]
                [$rule, $params] = $array;

                foreach ($params as $key => $param) {
                    if (is_string($param) && array_key_exists($param, $this->_bound)) {
                        // Replace with bound value
                        $params[$key] = $this->_bound[$param];
                    }
                }

                // Default the error name to be the rule (except array and lambda rules)
                $error_name = $rule;

                if (is_array($rule)) {
                    // Allows rule('field', [':model', 'some_rule']);
                    if (is_string($rule[0]) && array_key_exists($rule[0], $this->_bound)) {
                        // Replace with bound value
                        $rule[0] = $this->_bound[$rule[0]];
                    }

                    // This is an array callback, the method name is the error name
                    $error_name = $rule[1];
                    $passed = call_user_func_array($rule, $params);
                } elseif (!is_string($rule)) {
                    // This is a lambda function, there is no error name (errors must be added manually)
                    $error_name = false;
                    $passed = call_user_func_array($rule, $params);
                } elseif (method_exists('Valid', $rule)) {
                    // Use a method in this object
                    $method = new ReflectionMethod('Valid', $rule);

                    // Call static::$rule($this[$field], $param, ...) with Reflection
                    $passed = $method->invokeArgs(null, $params);
                } elseif (strpos($rule, '::') === false) {
                    // Use a function call
                    $function = new ReflectionFunction($rule);

                    // Call $function($this[$field], $param, ...) with Reflection
                    $passed = $function->invokeArgs($params);
                } else {
                    // Split the class and method of the rule
                    [$class, $method] = explode('::', $rule, 2);

                    // Use a static method call
                    $method = new ReflectionMethod($class, $method);

                    // Call $Class::$method($this[$field], $param, ...) with Reflection
                    $passed = $method->invokeArgs(null, $params);
                }

                // Ignore return values from rules when the field is empty
                if (!in_array($rule, $this->_empty_rules) && !Valid::not_empty($value)) {
                    continue;
                }

                if ($passed === false && $error_name !== false) {
                    // Add the rule to the errors
                    $this->error($field, $error_name, $params);

                    // This field has an error, stop executing rules
                    break;
                } elseif (isset($this->_errors[$field])) {
                    // The callback added the error manually, stop checking rules
                    break;
                }
            }
        }

        // Unbind all the automatic bindings to avoid memory leaks.
        unset($this->_bound[':validation']);
        unset($this->_bound[':data']);
        unset($this->_bound[':field']);
        unset($this->_bound[':value']);

        // Restore the data to its original form
        $this->_data = $original;

        if (isset($benchmark)) {
            // Stop benchmarking
            Profiler::stop($benchmark);
        }

        return empty($this->_errors);
    }

    /**
     * Add an error to a field.
     *
     * @param string $field Field name.
     * @param string $error Error message.
     * @param array|null $params Parameters.
     * @return $this
     */
    public function error(string $field, string $error, ?array $params = null): self
    {
        $this->_errors[$field] = [$error, $params];

        return $this;
    }

    /**
     * Returns the error messages.
     *
     * @uses Kohana::message
     * @param string|null $file File to load error messages from.
     * @param mixed $translate Translate the message.
     * @return array
     */
    public function errors(?string $file = null, $translate = true): array
    {
        if ($file === null) {
            // Return the error list
            return $this->_errors;
        }

        // Create a new message list
        $messages = [];

        foreach ($this->_errors as $field => $set) {
            [$error, $params] = $set;

            // Get the label for this field
            $label = $this->_labels[$field];

            if ($translate) {
                if (is_string($translate)) {
                    // Translate the label using the specified language
                    $label = __($label, null, $translate);
                } else {
                    // Translate the label
                    $label = __($label);
                }
            }

            // Start the translation values list
            $values = [
                ':field' => $label,
                ':value' => $this->_data[$field] ?? null,
            ];

            if (is_array($values[':value'])) {
                // All values must be strings
                $values[':value'] = implode(', ', array_flatten($values[':value']));
            }

            if ($params) {
                foreach ($params as $key => $value) {
                    if (is_array($value)) {
                        // All values must be strings
                        $value = implode(', ', array_flatten($value));
                    } elseif (is_object($value)) {
                        // Objects cannot be used in message files
                        continue;
                    }

                    // Check if a label for this parameter exists
                    if (isset($this->_labels[$value])) {
                        // Use the label as the value, eg: related field name for "matches"
                        $value = $this->_labels[$value];

                        if ($translate) {
                            if (is_string($translate)) {
                                // Translate the value using the specified language
                                $value = __($value, null, $translate);
                            } else {
                                // Translate the value
                                $value = __($value);
                            }
                        }
                    }

                    // Add each parameter as a numbered value, starting from 1
                    $values[':param' . ($key + 1)] = $value;
                }
            }

            if ($message = Kohana::message($file, "{$field}.{$error}") AND is_string($message)) {
                // Found a message for this field and error
            } elseif ($message = Kohana::message($file, "{$field}.default") AND is_string($message)) {
                // Found a default message for this field
            } elseif ($message = Kohana::message($file, $error) AND is_string($message)) {
                // Found a default message for this error
            } elseif ($message = Kohana::message('validation', $error) AND is_string($message)) {
                // Found a default message for this error
            } else {
                // No message exists, display the path expected
                $message = "{$file}.{$field}.{$error}";
            }

            if ($translate) {
                if (is_string($translate)) {
                    // Translate the message using specified language
                    $message = __($message, $values, $translate);
                } else {
                    // Translate the message using the default language
                    $message = __($message, $values);
                }
            } else {
                // Do not translate, just replace the values
                $message = strtr($message, $values);
            }

            // Set the message for this field
            $messages[$field] = $message;
        }

        return $messages;
    }
}
