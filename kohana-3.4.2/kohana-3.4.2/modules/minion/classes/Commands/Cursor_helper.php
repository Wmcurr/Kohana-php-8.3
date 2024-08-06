<?php

namespace Commands;

use Regist\Minion_Registr;

class MinionCommand_CursorHelper {
    // Конфигурационные константы
 // Конфигурационные константы
    public const DEFAULT_BOX_WIDTH = 40;
    public const DEFAULT_BOX_HEIGHT = 10;
    public const DEFAULT_PROGRESS_BAR_LENGTH = 50;
    public const DEFAULT_LOADING_DURATION = 5;

    // Constants for colors, background colors, and styles
    public const COLOR_RESET = "\033[0m";
    public const COLOR_BLACK = "\033[0;30m";
    public const COLOR_RED = "\033[0;31m";
    public const COLOR_GREEN = "\033[0;32m";
    public const COLOR_YELLOW = "\033[0;33m";
    public const COLOR_BLUE = "\033[0;34m";
    public const COLOR_PURPLE = "\033[0;35m";
    public const COLOR_CYAN = "\033[0;36m";
    public const COLOR_WHITE = "\033[0;37m";

    public const BG_BLACK = "\033[40m";
    public const BG_RED = "\033[41m";
    public const BG_GREEN = "\033[42m";
    public const BG_YELLOW = "\033[43m";
    public const BG_BLUE = "\033[44m";
    public const BG_PURPLE = "\033[45m";
    public const BG_CYAN = "\033[46m";
    public const BG_WHITE = "\033[47m";

    public const STYLE_BOLD = "\033[1m";
    public const STYLE_UNDERLINE = "\033[4m";
    public const STYLE_REVERSED = "\033[7m";
    public const STYLE_BLINK = "\033[5m";
    public const STYLE_HIDDEN = "\033[8m";

    // Пользовательские именованные стили
    protected static array $namedStyles = [];

    // Определение аргументов и опций команд
    protected static array $commands = [
        'clearScreen' => [
            'description' => 'Clear the terminal screen.',
            'args' => [],
        ],
        'drawBox' => [
            'description' => 'Draw a box with specified dimensions.',
            'args' => [
                'width' => ['required' => false, 'type' => 'int', 'default' => self::DEFAULT_BOX_WIDTH],
                'height' => ['required' => false, 'type' => 'int', 'default' => self::DEFAULT_BOX_HEIGHT],
            ],
        ],
        'writeAt' => [
            'description' => 'Write text at a specified position.',
            'args' => [
                'x' => ['required' => true, 'type' => 'int'],
                'y' => ['required' => true, 'type' => 'int'],
                'text' => ['required' => true, 'type' => 'string'],
                'color' => ['required' => false, 'type' => 'string', 'default' => self::COLOR_RESET],
                'style' => ['required' => false, 'type' => 'string', 'default' => ''],
            ],
        ],
        'showProgressBar' => [
            'description' => 'Display a progress bar.',
            'args' => [
                'current' => ['required' => true, 'type' => 'int'],
                'total' => ['required' => true, 'type' => 'int'],
                'length' => ['required' => false, 'type' => 'int', 'default' => self::DEFAULT_PROGRESS_BAR_LENGTH],
                'color' => ['required' => false, 'type' => 'string', 'default' => self::COLOR_GREEN],
            ],
        ],
        'showLoadingAnimation' => [
            'description' => 'Display a loading animation.',
            'args' => [
                'duration' => ['required' => false, 'type' => 'int', 'default' => self::DEFAULT_LOADING_DURATION],
                'interruptKey' => ['required' => false, 'type' => 'string', 'default' => 'q'],
            ],
        ],
        // Другие команды
    ];

    /**
     * Executes the CursorHelper command.
     * @param array $args
     * @return void
     */
    public static function execute(array $args = []): void {
        try {
            if (empty($args) || !isset($args['action'])) {
                self::showHelp();
                return;
            }

            $action = $args['action'];

            if (!isset(self::$commands[$action])) {
                echo "Unknown action: $action\n";
                self::showHelp();
                return;
            }

            $command = self::$commands[$action];
            $validatedArgs = self::validateArgs($command['args'], $args);

            switch ($action) {
                case 'clearScreen':
                    self::clearScreen();
                    break;
                case 'drawBox':
                    self::drawBox($validatedArgs['width'], $validatedArgs['height']);
                    break;
                case 'writeAt':
                    self::writeAt($validatedArgs['x'], $validatedArgs['y'], $validatedArgs['text'], $validatedArgs['color'], $validatedArgs['style']);
                    break;
                case 'showProgressBar':
                    self::showProgressBar($validatedArgs['current'], $validatedArgs['total'], $validatedArgs['length'], $validatedArgs['color']);
                    break;
                case 'showLoadingAnimation':
                    self::showLoadingAnimation($validatedArgs['duration'], $validatedArgs['interruptKey']);
                    break;
                // Обработка других команд
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Displays help information.
     * @return void
     */
    protected static function showHelp(): void {
        echo "Usage: minion cursor_helper [options]\n\n";
        echo "Commands:\n";
        foreach (self::$commands as $command => $info) {
            echo "  $command  - {$info['description']}\n";
            if (!empty($info['args'])) {
                echo "    Arguments:\n";
                foreach ($info['args'] as $arg => $details) {
                    $required = $details['required'] ? '(required)' : '';
                    $default = isset($details['default']) ? " (default: {$details['default']})" : '';
                    echo "      $arg $required - {$details['type']}$default\n";
                }
            }
        }
    }

    /**
     * Validates and prepares arguments for a command.
     * @param array $definition
     * @param array $args
     * @return array
     * @throws \Exception
     */
    protected static function validateArgs(array $definition, array $args): array {
        $validated = [];
        foreach ($definition as $key => $details) {
            if ($details['required'] && !isset($args[$key])) {
                throw new \InvalidArgumentException("Missing required argument: $key");
            }

            if (isset($args[$key])) {
                $validated[$key] = self::castValue($args[$key], $details['type']);
            } elseif (isset($details['default'])) {
                $validated[$key] = $details['default'];
            }
        }
        return $validated;
    }

    /**
     * Casts a value to the specified type.
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected static function castValue(mixed $value, string $type): mixed {
        return match ($type) {
            'int' => (int)$value,
            'string' => (string)$value,
            default => throw new \InvalidArgumentException("Unsupported argument type: $type"),
        };
    }

    // Реализация команд

    public static function moveTo(int $x, int $y): void {
        echo "\033[{$y};{$x}H";
    }

    public static function clearScreen(): void {
        echo "\033[2J\033[H";
    }

    public static function moveUp(int $lines = 1): void {
        echo "\033[{$lines}A";
    }

    public static function moveDown(int $lines = 1): void {
        echo "\033[{$lines}B";
    }

    public static function moveRight(int $columns = 1): void {
        echo "\033[{$columns}C";
    }

    public static function moveLeft(int $columns = 1): void {
        echo "\033[{$columns}D";
    }

    public static function hideCursor(): void {
        echo "\033[?25l";
    }

    public static function showCursor(): void {
        echo "\033[?25h";
    }

    public static function clearLineFromCursor(): void {
        echo "\033[K";
    }

    public static function clearCurrentLine(): void {
        echo "\033[2K\r";
    }

    public static function clearLines(int $lines = 1): void {
        for ($i = 0; $i < $lines; $i++) {
            self::clearCurrentLine();
            if ($i < $lines - 1) {
                self::moveDown();
            }
        }
    }

    public static function writeAt(int $x, int $y, string $text, string $color = self::COLOR_RESET, string $style = ''): void {
        self::moveTo($x, $y);
        echo self::getStyledText($text, $color, $style);
    }

    public static function getTerminalSize(): array {
        $output = [];
        exec('stty size 2>&1', $output);
        if (count($output) === 1) {
            [$rows, $columns] = explode(' ', $output[0]);
            return ['width' => (int)$columns, 'height' => (int)$rows];
        }
        return ['width' => 80, 'height' => 24]; // Default values
    }

    public static function drawBox(int $width, int $height, string $char = '#'): void {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('Width and height must be positive integers.');
        }

        $horizontalLine = str_repeat($char, $width);
        self::writeAt(1, 1, $horizontalLine);
        for ($i = 2; $i < $height; $i++) {
            self::writeAt(1, $i, $char);
            self::writeAt($width, $i, $char);
        }
        self::writeAt(1, $height, $horizontalLine);
    }

public static function showProgressBar(int $current, int $total, int $length = self::DEFAULT_PROGRESS_BAR_LENGTH, string $color = self::COLOR_GREEN): void {
    if ($total <= 0) {
        throw new \InvalidArgumentException('Total must be greater than zero.');
    }

    $progress = (int)(($current / $total) * $length);
    $bar = str_repeat('=', $progress) . str_repeat(' ', $length - $progress);

    echo "\r[" . self::getStyledText($bar, $color) . "] " . number_format(($current / $total) * 100, 2) . "%";
}

    public static function showLoadingAnimation(int $duration = 5, string $interruptKey = 'q'): void {
        $frames = ['|', '/', '-', '\\'];
        $start = microtime(true);
        $end = $start + $duration;

        echo "Starting loading animation... Press '{$interruptKey}' to interrupt.\n";

        // Попытка настройки терминала, но игнорирование ошибок
        @system('stty cbreak -echo');

        // Установка неблокирующего режима для STDIN
        if (stream_set_blocking(STDIN, false) === false) {
            echo "Warning: Unable to set non-blocking mode for input.\n";
        }

        while (microtime(true) < $end) {
            foreach ($frames as $frame) {
                $elapsed = number_format(microtime(true) - $start, 1);
                $remaining = number_format($end - microtime(true), 1);
                
                echo "\r{$frame} Loading... Elapsed: {$elapsed}s, Remaining: {$remaining}s ";
                
                flush();
                ob_flush();
                usleep(100000); // 0.1 секунды

                // Проверка нажатий клавиш
                $input = fread(STDIN, 1);
                if ($input === $interruptKey) {
                    echo "\nAnimation interrupted by user.\n";
                    break 2;
                }
            }
        }

        // Попытка восстановления настроек терминала, но игнорирование ошибок
        @system('stty sane');

        // Восстановление блокирующего режима для STDIN
        if (stream_set_blocking(STDIN, true) === false) {
            echo "Warning: Unable to restore blocking mode for input.\n";
        }

        echo "\nLoading complete.\n";
    }

    public static function showDropdownMenu(array $options): int {
        $selected = 0;
        $count = count($options);

        while (true) {
            foreach ($options as $index => $option) {
                if ($index === $selected) {
                    echo "\033[7m{$option}\033[0m\n"; // Reverse color for selected option
                } else {
                    echo "{$option}\n";
                }
            }

            $key = self::getKeyPress();
            if ($key === "\033[A") { // Up
                $selected = ($selected - 1 + $count) % $count;
            } elseif ($key === "\033[B") { // Down
                $selected = ($selected + 1) % $count;
            } elseif ($key === "\n") { // Enter
                break;
            }

            // Clear previous output
            self::moveUp($count);
        }

        return $selected;
    }

    private static function getKeyPress(): string {
        system('stty cbreak -echo');
        $key = fread(STDIN, 3);
        system('stty -cbreak echo');
        return $key;
    }

    /**
     * Creates styled text with optional styles and background colors.
     * @param string $text
     * @param string $color
     * @param string|array $style
     * @param string $bgColor
     * @return string
     */
public static function getStyledText(string $text, string $color = self::COLOR_RESET, string|array $style = '', string $bgColor = ''): string {
    // Убедимся, что color начинается с \033[
    if (str_starts_with($color, "\\033[")) {
        $color = "\033[" . substr($color, 5); // Заменяем экранированный \033[ на реальный символ ESC[
    }

    $styleCode = is_array($style) ? implode('', $style) : $style;

    return "{$color}{$bgColor}{$styleCode}{$text}" . self::COLOR_RESET;
}

    /**
     * Outputs data as a formatted table.
     * @param array $headers
     * @param array $rows
     * @return void
     */
    public static function showTable(array $headers, array $rows): void {
        // Calculate column widths
        $colWidths = [];
        foreach ($headers as $index => $header) {
            $colWidths[$index] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $colWidths[$index] = max($colWidths[$index], strlen($cell));
            }
        }

        // Print headers
        self::printRow($headers, $colWidths);
        echo "\n";
        self::printRow(array_fill(0, count($headers), str_repeat('-', max($colWidths))), $colWidths);
        echo "\n";

        // Print rows
        foreach ($rows as $row) {
            self::printRow($row, $colWidths);
            echo "\n";
        }
    }

    /**
     * Prints a single row of a table with specified column widths.
     * @param array $row
     * @param array $colWidths
     * @return void
     */
    protected static function printRow(array $row, array $colWidths): void {
        foreach ($row as $index => $cell) {
            echo str_pad($cell, $colWidths[$index]) . " ";
        }
    }

    /**
     * Handles OS signals.
     * @param int $signal
     * @return void
     */
    public static function handleSignal(int $signal): void {
        switch ($signal) {
            case SIGINT:
                echo "Process interrupted by user.\n";
                exit;
            case SIGTERM:
                echo "Process terminated.\n";
                exit;
        }
    }

    /**
     * Register a named style.
     * @param string $name
     * @param string $color
     * @param string|array $style
     * @param string $bgColor
     */
    public static function registerNamedStyle(string $name, string $color, string|array $style = '', string $bgColor = ''): void {
        self::$namedStyles[$name] = compact('color', 'style', 'bgColor');
    }

    /**
     * Get a named style.
     * @param string $name
     * @return string
     */
    public static function getNamedStyle(string $name): string {
        if (!isset(self::$namedStyles[$name])) {
            throw new \InvalidArgumentException("Named style '$name' not found.");
        }
        $style = self::$namedStyles[$name];
        return self::getStyledText('', $style['color'], $style['style'], $style['bgColor']);
    }

    /**
     * Ask the user a question and get their response.
     * @param string $question
     * @return string
     */
    public static function ask(string $question): string {
        echo self::getStyledText($question, self::COLOR_YELLOW) . ": ";
        return trim(fgets(STDIN));
    }

    /**
     * Ask the user a yes/no question.
     * @param string $question
     * @return bool
     */
    public static function confirm(string $question): bool {
        $response = self::ask($question . " (y/n)");
        return strtolower($response) === 'y';
    }

    /**
     * Ask the user to choose from a list of options.
     * @param string $question
     * @param array $options
     * @return int
     */
    public static function choice(string $question, array $options): int {
        echo self::getStyledText($question, self::COLOR_YELLOW) . ":\n";
        foreach ($options as $index => $option) {
            echo self::getStyledText("[$index] $option", self::COLOR_CYAN) . "\n";
        }
        $choice = (int)self::ask("Enter your choice number");
        if (isset($options[$choice])) {
            return $choice;
        }
        echo "Invalid choice.\n";
        return self::choice($question, $options); // Ask again
    }
}

// Register signal handlers
pcntl_signal(SIGINT, ['Commands\MinionCommand_CursorHelper', 'handleSignal']);
pcntl_signal(SIGTERM, ['Commands\MinionCommand_CursorHelper', 'handleSignal']);
