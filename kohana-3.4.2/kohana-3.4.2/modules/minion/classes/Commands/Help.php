<?php


namespace Commands;

class MinionCommand_Help {

    public static function execute(array $args): void {
        if (empty($args)) {
            echo "Usage: minion help <command>\n";
            echo "Example: minion help greet\n";
            echo "Use 'list_commands' to see all available commands.\n";
        } else {
            $command = $args[0];
            $description = self::getCommandDescription($command);
            $arguments = self::getCommandArguments($command);
            
            echo "Help for command '$command':\n";
            echo "  Description: $description\n";
            
            if (!empty($arguments)) {
                echo "  Arguments:\n";
                foreach ($arguments as $arg) {
                    echo "    --$arg\n";
                }
            } else {
                echo "  No arguments.\n";
            }
            
            // Примеры использования
            echo "\nExamples:\n";
            echo self::getExamples($command, $arguments);
        }
    }

    private static function getCommandDescription(string $command): string {
        $descriptions = [
            'cursor_helper' => 'Provides various terminal cursor and display manipulations.',
            // Add descriptions for other commands here
        ];

        return $descriptions[$command] ?? 'No description available.';
    }

    private static function getCommandArguments(string $command): array {
        $arguments = [
            'cursor_helper' => ['action', 'x', 'y', 'text', 'color', 'style', 'width', 'height', 'duration', 'options', 'current', 'total'],
            // Add arguments for other commands here
        ];

        return $arguments[$command] ?? [];
    }

    private static function getExamples(string $command, array $arguments): string {
        $examples = [
            'cursor_helper' => "  minion cursor_helper --action=clearScreen\n"
                              . "  minion cursor_helper --action=drawBox --width=30 --height=10\n"
                              . "  minion cursor_helper --action=writeAt --x=5 --y=5 --text='Hello' --color=\\033[0;31m --style=\\033[1m\n"
                              . "  minion cursor_helper --action=showProgressBar --current=30 --total=100\n"
                              . "  minion cursor_helper --action=showLoadingAnimation --duration=5\n"
                              . "  minion cursor_helper --action=showDropdownMenu --options='Option 1,Option 2,Option 3'\n",
            // Add examples for other commands here
        ];

        return $examples[$command] ?? 'No examples available.';
    }
}
