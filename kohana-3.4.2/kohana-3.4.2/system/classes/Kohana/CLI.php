<?php
use Regist\Minion_Registr;

class Kohana_CLI {
    protected static array $commands = [];
    protected static array $descriptions = [];
    protected static array $arguments = [];
    protected static array $aliases = [];

    #[Command(description: "Executes the given command.")]
    public static function execute(): void {
        global $argv;

        if (count($argv) < 2) {
            echo "No command provided.\n";
            self::show_help();
            return;
        }

        $commandName = ltrim($argv[1], '-');
        $originalCommandName = $commandName;
        $commandName = self::$aliases[$commandName] ?? $commandName;

        foreach (glob(MODPATH . 'minion/classes/Regist/*.php') as $file) {
            require_once $file;
        }

        $commandFile = MODPATH . 'minion/classes/Commands/' . ucfirst($commandName) . '.php';
        if (file_exists($commandFile)) {
            require_once $commandFile;

            try {
                Minion_Registr::register_command($commandName);

                $args = array_slice($argv, 2);

                match ($commandName) {
                    'help', '--help' => call_user_func(self::$commands['help'], $args),
                    default => self::run_command($commandName, $args, $originalCommandName),
                };
            } catch (CommandNotFoundException $e) {
                echo "Command not found: " . $e->getMessage() . "\n";
                self::show_help();
            } catch (InvalidArgumentException $e) {
                echo "Invalid argument: " . $e->getMessage() . "\n";
                self::show_help();
            } catch (RuntimeException $e) {
                echo "Runtime error: " . $e->getMessage() . "\n";
                self::show_help();
            } catch (Exception $e) {
                echo "Error occurred: " . $e->getMessage() . "\n";
                self::show_help();
            }
        } else {
            echo "Command file for '$commandName' not found.\n";
            self::show_help();
        }
    }

    private static function run_command(string $commandName, array $args, string $originalCommandName): void {
        if (isset(self::$commands[$commandName])) {
            $parsedArgs = self::parse_arguments($args);
            $missingArgs = array_diff_key(array_flip(self::$arguments[$commandName] ?? []), $parsedArgs);

            if (!empty($missingArgs)) {
                echo "Missing arguments: " . implode(', ', array_keys($missingArgs)) . "\n";
                return;
            }

            call_user_func(self::$commands[$commandName], $parsedArgs);
        } else {
            throw new CommandNotFoundException("Command '$originalCommandName' not found.");
        }
    }

    #[Command(description: "Registers a new command.")]
    public static function register_command(
        string $name,
        callable $callback,
        string $description = '',
        array $arguments = []
    ): void {
        if ($name === 'list_commands') {
            self::load_all_commands();
        }

        self::validate_callback($name, $callback);

        self::$commands[$name] = $callback;
        self::$descriptions[$name] = $description;
        self::$arguments[$name] = $arguments;
    }

    private static function load_all_commands(): void {
        $all_commands = Minion_Registr::get_all_commands();
        foreach ($all_commands as $cmd_name => $cmd_data) {
            if ($cmd_name === 'list_commands') {
                continue;
            }

            [$className, $desc, $args] = array_pad($cmd_data, 3, []);

            $commandFile = MODPATH . 'minion/classes/Commands/' . ucfirst($cmd_name) . '.php';
            if (file_exists($commandFile)) {
                require_once $commandFile;
            }

            if (class_exists($className) && method_exists($className, 'execute')) {
                self::register_command(
                    name: $cmd_name,
                    callback: [$className, 'execute'],
                    description: $desc,
                    arguments: $args
                );
            } else {
                echo "Error: Command class $className or method 'execute' not found\n";
            }
        }
    }

    private static function validate_callback(string $name, $callback): void {
        if (is_array($callback) && count($callback) === 2) {
            [$class, $method] = $callback;
            if (!class_exists($class)) {
                throw new InvalidArgumentException("Class '$class' not found");
            }
            if (!method_exists($class, $method)) {
                throw new InvalidArgumentException("Method '$method' not found in class '$class'");
            }
        } elseif (is_string($callback) && strpos($callback, '::') !== false) {
            [$class, $method] = explode('::', $callback, 2);
            if (!class_exists($class)) {
                throw new InvalidArgumentException("Class '$class' not found");
            }
            if (!method_exists($class, $method)) {
                throw new InvalidArgumentException("Method '$method' not found in class '$class'");
            }
        } else {
            throw new InvalidArgumentException("Invalid callback provided for command '$name'");
        }
    }

    public static function parse_arguments(array $args): array {
        $parsed = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (strpos($arg, '=') !== false) {
                    [$key, $value] = explode('=', $arg, 2);
                    $parsed[$key] = $value;
                } else {
                    $parsed[$arg] = true;
                }
            }
        }
        return $parsed;
    }

    #[Command(description: "Shows help information.")]
    public static function show_help(): void {
        echo "Available commands:\n";
        foreach (self::$commands as $name => $callback) {
            $description = self::$descriptions[$name] ?? '';
            $arguments = self::$arguments[$name] ?? [];
            echo "  $name [" . implode(' ', $arguments) . "] - $description\n";
        }
    }

    public static function register_alias(string $command, string $alias): void {
        self::$aliases[$alias] = $command;
    }

    public static function get_commands(): array {
        return self::$commands;
    }

    public static function get_command_descriptions(): array {
        return self::$descriptions;
    }

    public static function get_command_arguments(): array {
        return self::$arguments;
    }
}

// Custom exception for command not found
class CommandNotFoundException extends \Exception {}

// Additional classes for separation of concerns can be added here (e.g., CommandLoader, ArgumentParser)
