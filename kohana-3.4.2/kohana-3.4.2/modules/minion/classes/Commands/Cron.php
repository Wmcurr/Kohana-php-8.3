<?php
namespace Commands;

class MinionCommand_Schedule {
    protected static $tasks = [];
    protected static $retryCount = 3; // Количество попыток при неудаче
    protected static $stop = false;   // Флаг остановки

    public static function execute(array $args): void {
        if (empty($args) || !isset($args['schedule'])) {
            echo "Error: You must provide a cron schedule as an argument.\n";
            echo "Usage: minion cron --schedule=\"* * * * *\"\n";
            return;
        }

        // Обработка сигнала завершения
        pcntl_signal(SIGINT, [__CLASS__, 'stop']);
        pcntl_signal(SIGTERM, [__CLASS__, 'stop']);

        self::addTask('default_task', $args['schedule'], function() {
            echo "Task executed at " . date('Y-m-d H:i:s') . "\n";
        });

        while (!self::$stop) {
            pcntl_signal_dispatch(); // Проверка наличия сигнала завершения
            $currentTime = new \DateTime();
            foreach (self::$tasks as $task) {
                if (self::isDue($task['schedule'], $currentTime)) {
                    self::runTask($task);
                }
            }

            // Рассчитываем время до следующего выполнения задачи
            $nextRun = new \DateTime();
            $nextRun->modify('+1 minute');
            $sleepTime = $nextRun->getTimestamp() - time();
            sleep($sleepTime);
        }

        echo "Scheduler stopped.\n";
    }

    protected static function addTask(string $name, string $schedule, callable $callback): void {
        self::$tasks[] = [
            'name' => $name,
            'schedule' => $schedule,
            'callback' => $callback,
            'attempts' => 0 // Число попыток выполнения
        ];
    }

    protected static function removeTask(string $name): void {
        foreach (self::$tasks as $index => $task) {
            if ($task['name'] === $name) {
                unset(self::$tasks[$index]);
                echo "Task '{$name}' removed.\n";
                return;
            }
        }
        echo "Task '{$name}' not found.\n";
    }

    protected static function runTask(array &$task): void {
        try {
            call_user_func($task['callback']);
            self::log("Task '{$task['name']}' executed successfully.");
            $task['attempts'] = 0; // Сбросить попытки после успешного выполнения
        } catch (\Exception $e) {
            $task['attempts']++;
            self::log("Error executing task '{$task['name']}': " . $e->getMessage(), 'error');

            if ($task['attempts'] < self::$retryCount) {
                self::log("Retrying task '{$task['name']}' (attempt {$task['attempts']} of " . self::$retryCount . ").", 'warning');
                self::runTask($task); // Повторное выполнение
            } else {
                self::log("Task '{$task['name']}' failed after " . self::$retryCount . " attempts.", 'error');
            }
        }
    }

    protected static function isDue(string $schedule, \DateTime $currentTime): bool {
        $cronParts = explode(' ', $schedule);
        if (count($cronParts) !== 5) {
            echo "Invalid cron schedule format.\n";
            return false;
        }
        list($min, $hour, $day, $month, $weekday) = $cronParts;
        return self::matchCronPart($min, (int)$currentTime->format('i')) &&
               self::matchCronPart($hour, (int)$currentTime->format('H')) &&
               self::matchCronPart($day, (int)$currentTime->format('d')) &&
               self::matchCronPart($month, (int)$currentTime->format('m')) &&
               self::matchCronPart($weekday, (int)$currentTime->format('w'));
    }

    protected static function matchCronPart(string $cronPart, int $timePart): bool {
        if ($cronPart === '*') {
            return true;
        }
        if (strpos($cronPart, ',') !== false) {
            $values = explode(',', $cronPart);
            return in_array((string)$timePart, $values, true);
        }
        if (strpos($cronPart, '-') !== false) {
            list($start, $end) = explode('-', $cronPart);
            return $timePart >= (int)$start && $timePart <= (int)$end;
        }
        if (strpos($cronPart, '/') !== false) {
            list($base, $step) = explode('/', $cronPart);
            return ($base === '*' || $timePart >= (int)$base) && $timePart % (int)$step === 0;
        }
        return (int)$cronPart === $timePart;
    }

    protected static function log(string $message, string $level = 'info'): void {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] [$level] $message\n";
        // Тут можете дописать логирование 
    }

    public static function stop(): void {
        self::$stop = true;
    }
}
