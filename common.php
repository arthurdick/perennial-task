<?php

// The first step is to load the application configuration.
// This defines TASKS_DIR, COMPLETIONS_LOG, XSD_PATH, and TASKS_PER_PAGE.
require_once 'config.php';


// --- Shared Functions ---


/**
 * A test-friendly wrapper for getting user input from the command line.
 * In a testing environment, this function can be replaced by a mock version
 * by setting the global variable '__MOCK_PROMPT_USER_FUNC'.
 *
 * @param string $prompt The prompt to display to the user.
 * @return string The user's input.
 */
function prompt_user(string $prompt): string
{
    if (isset($GLOBALS['__MOCK_PROMPT_USER_FUNC']) && is_callable($GLOBALS['__MOCK_PROMPT_USER_FUNC'])) {
        return call_user_func($GLOBALS['__MOCK_PROMPT_USER_FUNC'], $prompt);
    }
    $input = readline($prompt);
    return ($input === false) ? '' : $input;
}

/**
 * Prompts the user for a yes/no answer and returns a boolean.
 *
 * @param string $prompt The prompt to display to the user.
 * @param string $default The default value if the user presses enter ('y' or 'n').
 * @return bool True for 'yes', false for 'no'.
 */
function get_yes_no_input(string $prompt, string $default = ''): bool
{
    while (true) {
        $input = strtolower(prompt_user($prompt));
        if (empty($input) && !empty($default)) {
            $input = $default;
        }
        if (in_array($input, ['y', 'yes'])) {
            return true;
        }
        if (in_array($input, ['n', 'no'])) {
            return false;
        }
        echo "Invalid input. Please enter 'y' or 'n'.\n";
    }
}

/**
 * Prompts the user for a positive integer.
 *
 * @param string $prompt The prompt to display to the user.
 * @param bool   $allow_zero If true, allows 0 as a valid input.
 * @return int The validated positive integer.
 */
function get_positive_integer_input(string $prompt, bool $allow_zero = false): int
{
    while (true) {
        $input = prompt_user($prompt);
        if (ctype_digit($input)) {
            $number = (int)$input;
            if ($number > 0 || ($allow_zero && $number === 0)) {
                return $number;
            }
        }
        echo "Invalid input. Please enter a positive whole number.\n";
    }
}

/**
 * Prompts the user for an optional positive integer.
 *
 * @param string $prompt The prompt to display to the user.
 * @return ?int The validated positive integer, or null if the input is empty.
 */
function get_optional_positive_integer_input(string $prompt): ?int
{
    while (true) {
        $input = prompt_user($prompt);
        if (empty($input)) {
            return null;
        }
        if (ctype_digit($input) && (int)$input >= 0) {
            return (int)$input;
        }
        echo "Invalid input. Please enter a positive whole number or press Enter to skip.\n";
    }
}

/**
 * Prompts the user to select an option from a menu.
 *
 * @param string $prompt The prompt to display to the user.
 * @param array  $options An associative array of options, where the key is the input and the value is the description.
 * @return string The key of the selected option.
 */
function get_menu_choice(string $prompt, array $options): string
{
    echo "$prompt\n";
    foreach ($options as $key => $text) {
        echo "  ($key) $text\n";
    }
    while (true) {
        $input = strtolower(prompt_user("Enter your choice: "));
        if (array_key_exists($input, $options)) {
            return $input;
        }
        echo "Invalid choice. Please try again.\n";
    }
}

/**
 * Prompts the user for a date and validates it.
 *
 * @param string $prompt The message to display to the user.
 * @param bool   $allow_empty If true, allows the user to press Enter for the current date.
 * @return string The validated date in YYYY-MM-DD format.
 */
function get_validated_date_input(string $prompt, bool $allow_empty = false): string
{
    while (true) {
        $input = prompt_user($prompt);
        if ($allow_empty && empty($input)) {
            return date('Y-m-d');
        }
        if (validate_date($input)) {
            return $input;
        }
        echo "Invalid date format. Please use YYYY-MM-DD.\n";
    }
}

function get_interval_input(string $prompt): ?string
{
    while (true) {
        $input = strtolower(prompt_user($prompt));
        if (empty($input)) {
            return null;
        }
        if (preg_match('/^(\d+)\s+(day|week|month|year)s?$/', $input, $matches)) {
            $value = (int)$matches[1];
            $unit = rtrim($matches[2], 's');
            if ($value > 0) {
                return "$value $unit" . ($value > 1 ? 's' : '');
            }
        }
        echo "Invalid format. Use '7 days', '2 weeks', '1 month', etc.\n";
    }
}

function get_reschedule_input(SimpleXMLElement $xml): void
{
    if (!get_yes_no_input("Does this task reschedule automatically? (y/N): ", 'n')) {
        return;
    }

    $interval = get_interval_input("Reschedule interval (e.g., '30 days', '1 month'): ");
    if (!$interval) {
        return;
    }

    $from_options = [
        'd' => 'From its previous due date (for fixed schedules like rent)',
        'c' => 'From its completion date (for flexible tasks like cleaning the gutters)'
    ];
    $from_choice = get_menu_choice("Reschedule from?", $from_options);
    $from_map = ['d' => 'due_date', 'c' => 'completion_date'];

    if (!isset($xml->reschedule)) {
        $xml->addChild('reschedule');
    }
    $xml->reschedule->interval = $interval;
    $xml->reschedule->from = $from_map[$from_choice];
}

function collect_scheduled_task_details(SimpleXMLElement $xml): void
{
    $dueDate = get_validated_date_input("Enter due date (YYYY-MM-DD): ");
    $xml->addChild('due', $dueDate);
    get_reschedule_input($xml);
}

/**
* Sanitizes a task name into a base filename.
* @param string $name The name of the task.
* @return string The sanitized base filename (without .xml extension).
*/
function sanitize_filename(string $name): string
{
    $base = strtolower(trim($name));
    $base = preg_replace('/[^a-z0-9\s-]/', '', $base);
    $base = preg_replace('/[\s-]+/', '_', $base);
    return $base;
}

function select_task_file(array $argv, string $prompt_verb, string $initial_filter = 'reportable'): ?string
{
    // The `prn` script ensures the filepath is the LAST argument.
    $filepath_arg = null;
    if (count($argv) > 1) {
        $last_arg = end($argv);
        // Check if the last argument is a non-option; if so, assume it's the filepath.
        if ($last_arg && !str_starts_with($last_arg, '-')) {
            $filepath_arg = $last_arg;
        }
    }

    if ($filepath_arg !== null) {
        if (!is_file($filepath_arg)) {
            echo "Error: The file '$filepath_arg' does not exist or is not a file.\n";
            // Fall through to interactive selection.
        } else {
            if (!validate_task_file($filepath_arg)) {
                exit(1);
            }
            // Do not echo here, to keep non-interactive mode clean.
            // echo "Task selected from argument: " . basename($filepath_arg) . "\n";
            return $filepath_arg;
        }
    }


    if (!is_dir(TASKS_DIR)) {
        echo "Tasks directory not found at " . TASKS_DIR . ". Please check your configuration.\n";
        return null;
    }

    $task_files = glob(TASKS_DIR . '/*.xml');
    if (empty($task_files)) {
        echo "No tasks found.\n";
        return null;
    }

    $current_filter = $initial_filter;
    $now = new DateTimeImmutable('today');
    $current_page = 1;

    while (true) {
        $visible_tasks = [];
        foreach ($task_files as $file) {
            if (!validate_task_file($file, true)) {
                continue;
            }

            $xml = simplexml_load_file($file);

            if ($current_filter === 'active') {
                if (get_task_type($xml) === 'normal' && isset($xml->history)) {
                    continue;
                }
            } elseif ($current_filter === 'reportable') {
                if (!is_task_reportable($xml, $now)) {
                    continue;
                }
            }

            $visible_tasks[] = ['path' => $file, 'name' => (string)$xml->name];
        }

        $total_tasks = count($visible_tasks);
        $total_pages = ceil($total_tasks / TASKS_PER_PAGE);

        if ($total_tasks === 0) {
            echo "\nNo tasks match the current filter ('" . ucfirst($current_filter) . "').\n";
        } else {
            $page_info = ($total_pages > 1) ? " (Page $current_page of $total_pages)" : "";
            echo "\n--- Select a task to $prompt_verb --- Filter: " . ucfirst($current_filter) . "$page_info\n";

            $start_index = ($current_page - 1) * TASKS_PER_PAGE;
            $tasks_on_page = array_slice($visible_tasks, $start_index, TASKS_PER_PAGE);

            foreach ($tasks_on_page as $index_on_page => $task) {
                $display_number = $start_index + $index_on_page + 1;
                echo "  [" . $display_number . "] " . $task['name'] . "\n";
            }
        }

        $prompt = "Enter #, (f)ilter, (q)uit";
        if ($total_pages > 1) {
            $nav_prompt = [];
            if ($current_page > 1) {
                $nav_prompt[] = "(p)rev";
            }
            if ($current_page < $total_pages) {
                $nav_prompt[] = "(n)ext";
            }
            $prompt .= ", " . implode(", ", $nav_prompt);
        }
        $prompt .= ": ";

        $input = prompt_user($prompt);

        if (strtolower($input) === 'q') {
            return null;
        }

        if (strtolower($input) === 'f') {
            $new_filter = prompt_user("Choose filter (all, active, reportable): ");
            if (in_array($new_filter, ['all', 'active', 'reportable'])) {
                $current_filter = $new_filter;
                $current_page = 1;
            } else {
                echo "Invalid filter.\n";
            }
            continue;
        }

        if (($input === '' || strtolower($input) === 'n') && $current_page < $total_pages) {
            $current_page++;
            continue;
        }
        if (strtolower($input) === 'p' && $current_page > 1) {
            $current_page--;
            continue;
        }

        if (ctype_digit($input)) {
            $selected_index = (int)$input - 1;
            if (isset($visible_tasks[$selected_index])) {
                return $visible_tasks[$selected_index]['path'];
            }
        }

        echo "Invalid selection. Please try again.\n";
    }
}

function get_next_due_date(SimpleXMLElement $task, DateTimeImmutable $now): ?DateTimeImmutable
{
    if (get_task_type($task) !== 'scheduled') {
        return null;
    }

    if (isset($task->reschedule)) {
        if ($task->reschedule->from == 'completion_date') {
            if (isset($task->history->entry)) {
                $latest_completion = '1970-01-01';
                foreach ($task->history->entry as $entry) {
                    if ((string)$entry > $latest_completion) {
                        $latest_completion = (string)$entry;
                    }
                }
                return (new DateTimeImmutable($latest_completion))->modify('+' . (string)$task->reschedule->interval);
            }
        }
        return new DateTimeImmutable((string)$task->due);
    }

    if (isset($task->recurring)) {
        $completed_date = new DateTimeImmutable((string)$task->recurring->completed);
        $duration = (int)$task->recurring->duration;
        return $completed_date->modify("+$duration days");
    }

    return new DateTimeImmutable((string)$task->due);
}

/**
 * Checks if a task is "reportable" (overdue, due today, or upcoming).
 *
 * @param SimpleXMLElement $task The XML element for the task.
 * @param DateTimeImmutable $now The current date for comparison.
 * @return bool True if the task is reportable, false otherwise.
 */
function is_task_reportable(SimpleXMLElement $task, DateTimeImmutable $now): bool
{
    $type = get_task_type($task);
    if ($type === 'normal') {
        return !isset($task->history);
    }

    if ($type === 'scheduled') {
        $next_due_date = get_next_due_date($task, $now);
        if (!$next_due_date) {
            return false;
        }

        $preview = isset($task->preview) ? (int)$task->preview : 0;
        $interval = $now->diff($next_due_date);

        if ($interval->invert) {
            return true;
        }
        return $interval->days <= $preview;
    }

    return false;
}

/**
 * Validates a single task XML file against the XSD schema.
 *
 * @param string $filepath The path to the XML file.
 * @param bool $silent If true, suppresses error messages.
 * @return bool True if the file is valid, false otherwise.
 */
function validate_task_file(string $filepath, bool $silent = false): bool
{
    if (!is_file($filepath) || !is_readable($filepath)) {
        return false;
    }

    $dom = new DOMDocument();
    if (!@$dom->load($filepath)) {
        if (!$silent) {
            echo "Error: Malformed XML file '" . basename($filepath) . "'.\n";
        }
        return false;
    }
    if (!@$dom->schemaValidate(XSD_PATH)) {
        if (!$silent) {
            echo "Error: Task file '" . basename($filepath) . "' does not conform to schema.\n";
        }
        return false;
    }
    return true;
}

function get_task_type(SimpleXMLElement $xml): string
{
    if (isset($xml->due) || isset($xml->reschedule)) {
        return 'scheduled';
    }
    if (isset($xml->recurring)) {
        return 'scheduled';
    }
    return 'normal';
}

function validate_date(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Saves a SimpleXMLElement object to a file with proper formatting.
 *
 * @param string $filepath The path to save the file to.
 * @param SimpleXMLElement $xml The XML object to save.
 * @return bool True on success, false on failure.
 */
function save_xml_file(string $filepath, SimpleXMLElement $xml): bool
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    if ($dom->loadXML($xml->asXML()) === false) {
        return false;
    }

    $dom->documentElement->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:noNamespaceSchemaLocation', XSD_PATH);
    return $dom->save($filepath) !== false;
}

/**
 * Returns the singular or plural form of a word based on a number.
 *
 * @param int    $number   The number to test.
 * @param string $singular The singular form of the word.
 * @param string $plural   The plural form of the word.
 * @return string The correct word form.
 */
function pluralize(int $number, string $singular, string $plural): string
{
    return abs($number) === 1 ? $singular : $plural;
}

/**
 * Checks for and migrates a task from the legacy <recurring> format to the new <reschedule> format.
 *
 * @param SimpleXMLElement $xml The task's XML object, passed by reference.
 * @return bool True if a migration was performed, false otherwise.
 */
function migrate_legacy_task_if_needed(SimpleXMLElement &$xml): bool
{
    if (isset($xml->recurring)) {
        $xml->addChild('reschedule');
        $xml->reschedule->addChild('interval', (string)$xml->recurring->duration . ' days');
        $xml->reschedule->addChild('from', 'completion_date');

        if (isset($xml->recurring->completed) && !isset($xml->due)) {
            try {
                $completed_date = new DateTime((string)$xml->recurring->completed);
                $duration = (int)$xml->recurring->duration;
                $xml->addChild('due', $completed_date->modify("+$duration days")->format('Y-m-d'));
            } catch (Exception $e) {
            }
        }

        unset($xml->recurring);
        return true;
    }
    return false;
}
