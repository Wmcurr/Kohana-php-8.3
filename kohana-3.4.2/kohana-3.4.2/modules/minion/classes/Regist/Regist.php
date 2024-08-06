<?php
namespace Regist;

use Kohana_CLI;
use Exception;

class Minion_Registr {
    private static $commandMap = [
        'help' => ['Commands\MinionCommand_Help', 'Displays help information'],
        'cron' => ['Commands\MinionCommand_Schedule', 'Schedules and executes tasks based on specified cron expressions'],
        'list_commands' => ['Commands\MinionCommand_ListCommands', 'Lists all available commands'],
        'cursor_helper' => ['Commands\MinionCommand_CursorHelper', 'Provides cursor manipulation commands in the console']
    ];
    
    public static function register_command(string $commandName): void {
        if (!isset(self::$commandMap[$commandName])) {
            throw new Exception("Unknown command: $commandName");
        }

        [$className, $description, $arguments] = array_pad(self::$commandMap[$commandName], 3, []);

        if (!class_exists($className)) {
            throw new Exception("Command class $className not found");
        }

        if (!method_exists($className, 'execute')) {
            throw new Exception("Method 'execute' not found in class $className");
        }

        Kohana_CLI::register_command($commandName, [$className, 'execute'], $description, $arguments);
    }
    
    public static function get_all_commands(): array {
        return self::$commandMap;
    }
}

