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
    // Check if a mock function has been provided by the test environment.
    if (isset($GLOBALS['__MOCK_PROMPT_USER_FUNC']) && is_callable($GLOBALS['__MOCK_PROMPT_USER_FUNC'])) {
        return call_user_func($GLOBALS['__MOCK_PROMPT_USER_FUNC'], $prompt);
    }

    // Otherwise, use the real readline function for the live application.
    $input = readline($prompt);
    if ($input === false) {
        return '';
    }
    return $input;
}

/**
 * Interactively collects details for a 'due' task.
 * @param SimpleXMLElement $xml The XML object to modify.
 */
function collect_due_task_details(SimpleXMLElement $xml): void
{
    $dueDate = null;
    while ($dueDate === null) {
        $dateStr = prompt_user("Enter due date (YYYY-MM-DD): ");
        if (validate_date($dateStr)) {
            $dueDate = $dateStr;
        } else {
            echo "Invalid date format. Please use YYYY-MM-DD.\n";
        }
    }
    $xml->addChild('due', $dueDate);
}

/**
 * Interactively collects details for a 'recurring' task.
 * @param SimpleXMLElement $xml The XML object to modify.
 */
function collect_recurring_task_details(SimpleXMLElement $xml): void
{
    $completedDate = null;
    while ($completedDate === null) {
        $dateStr = prompt_user("Enter last completed date (YYYY-MM-DD, press Enter for today): ");
        if (empty($dateStr)) {
            $dateStr = date('Y-m-d');
        }
        if (validate_date($dateStr)) {
            $completedDate = $dateStr;
        } else {
            echo "Invalid date format. Please use YYYY-MM-DD.\n";
        }
    }

    $duration = '';
    while (!ctype_digit($duration) || (int)$duration <= 0) {
        $duration = prompt_user("Recur every X days (e.g., 7): ");
        if (!ctype_digit($duration) || (int)$duration <= 0) {
            echo "Please enter a positive whole number for the duration.\n";
        }
    }

    $recurring = $xml->addChild('recurring');
    $recurring->addChild('completed', $completedDate);
    $recurring->addChild('duration', $duration);
}


function select_task_file(array $argv, string $prompt_verb, string $initial_filter = 'reportable'): ?string
{
    if (isset($argv[1])) {
        $filepath_arg = $argv[1];
        if (!is_file($filepath_arg)) {
            echo "Error: The file '$filepath_arg' does not exist or is not a file.\n";
            exit(1);
        }
        if (!validate_task_file($filepath_arg)) {
            exit(1);
        }
        echo "Task selected from argument: " . basename($filepath_arg) . "\n";
        return $filepath_arg;
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
            if (!validate_task_file($file, true)) continue;

            $xml = simplexml_load_file($file);

            // Apply the current filter
            if ($current_filter === 'active') {
                if (get_task_type($xml) === 'normal' && isset($xml->history)) continue; // Skip completed normal tasks
            } elseif ($current_filter === 'reportable') {
                if (!is_task_reportable($xml, $now)) continue;
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
            if ($current_page > 1) $nav_prompt[] = "(p)rev";
            if ($current_page < $total_pages) $nav_prompt[] = "(n)ext";
            $prompt .= ", " . implode(", ", $nav_prompt);
        }
        $prompt .= ": ";

        $input = prompt_user($prompt);

        if (strtolower($input) === 'q') return null;

        if (strtolower($input) === 'f') {
            $new_filter = prompt_user("Choose filter (all, active, reportable): ");
            if (in_array($new_filter, ['all', 'active', 'reportable'])) {
                $current_filter = $new_filter;
                $current_page = 1; // Reset to first page
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

    switch ($type) {
        case 'recurring':
            $completed_date = new DateTimeImmutable((string)$task->recurring->completed);
            $recur_duration = (int)$task->recurring->duration;
            $preview = isset($task->preview) ? (int)$task->preview : 0;
            $next_due = $completed_date->modify("+$recur_duration days");
            $interval = $now->diff($next_due);
            if ($interval->invert) return true; // Overdue
            return $interval->days <= $preview;

        case 'due':
            $due_date = new DateTimeImmutable((string)$task->due);
            $preview = isset($task->preview) ? (int)$task->preview : 0;
            $interval = $now->diff($due_date);
            if ($interval->invert) return true; // Overdue
            return $interval->days <= $preview;

        case 'normal':
            // A normal task is only "reportable" if it has not been completed yet.
            return !isset($task->history);

        default:
            return false;
    }
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
    if (!is_file($filepath) || !is_readable($filepath)) return false;

    // Use DOMDocument for schema validation
    $dom = new DOMDocument();
    // Suppress warnings from load(), we check the return value.
    if (!@$dom->load($filepath)) {
        if (!$silent) echo "Error: Failed to load XML file '" . basename($filepath) . "'. It may be malformed.\n";
        return false;
    }
    
    // Suppress warnings from schemaValidate(), we check the return value.
    if (!@$dom->schemaValidate(XSD_PATH)) {
        if (!$silent) echo "Error: The task file '" . basename($filepath) . "' does not conform to the required schema.\n";
        return false;
    }

    return true;
}

function get_task_type(SimpleXMLElement $xml): string
{
    if (isset($xml->due)) return 'due';
    if (isset($xml->recurring)) return 'recurring';
    return 'normal';
}

function validate_date(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Saves a SimpleXMLElement object to a file.
 * This function no longer needs to manually handle the DOCTYPE.
 *
 * @param string $filepath The path to save the file to.
 * @param SimpleXMLElement $xml The XML object to save.
 * @return bool True on success, false on failure.
 */
function save_xml_file(string $filepath, SimpleXMLElement $xml): bool
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $node = dom_import_simplexml($xml);
    $node = $dom->importNode($node, true);
    $dom->appendChild($node);
    
    // Add a reference to the XSD schema in the saved XML file.
    // This makes the file self-validating with standard XML tools.
    $dom->documentElement->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:noNamespaceSchemaLocation', XSD_PATH);

    return $dom->save($filepath) !== false;
}

/**
 * Returns 'day' or 'days' based on the number.
 *
 * @param int $number The number to check.
 * @return string The pluralized string.
 */
function pluralize_days(int $number): string
{
    return abs($number) === 1 ? 'day' : 'days';
}

