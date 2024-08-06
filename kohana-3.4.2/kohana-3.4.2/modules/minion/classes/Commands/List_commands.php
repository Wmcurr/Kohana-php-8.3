<?php

namespace Commands;

use Regist\Minion_Registr;

class MinionCommand_ListCommands {

    public static function execute(array $args): void {
        // Только для команды `list_commands` мы регистрируем все команды
        foreach (Minion_Registr::get_all_commands() as $name => $commandData) {
            try {
                Minion_Registr::register_command($name, $commandData[0], $commandData[1], $commandData[2] ?? []);
            } catch (\Exception $e) {
                echo "Error registering command $name: " . $e->getMessage() . "\n";
            }
        }

        // Получение всех зарегистрированных команд
        $commands = \Kohana_CLI::get_command_descriptions();

        if (empty($commands)) {
            echo "No commands available.\n";
        } else {
            echo "Available commands:\n";
            foreach ($commands as $command => $description) {
                echo "  $command - $description\n";
            }
        }
    }
}
