<?php

require_once 'common.php';

// --- Function Definitions ---

if (!function_exists('display_task_header')) {
    /**
     * Displays the common header for a task.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     * @param string $type_label The human-readable label for the task type.
     */
    function display_task_header(SimpleXMLElement $task, string $type_label): void
    {
        echo "Task: " . (string)$task->name . "\n";
        echo "Type: " . $type_label . "\n";
    }
}

if (!function_exists('display_optional_history')) {
    /**
     * Displays the completion history count if it exists.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function display_optional_history(SimpleXMLElement $task): void
    {
        if (isset($task->history)) {
            $completion_count = count($task->history->entry);
            echo "History: " . $completion_count . " " . pluralize($completion_count, "completion", "completions") . " logged.\n";
        }
    }
}

if (!function_exists('describe_recurring_task')) {
    /**
     * Describes a recurring task in detail.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function describe_recurring_task(SimpleXMLElement $task): void
    {
        display_task_header($task, 'Recurring');

        $recur_duration = (int)$task->recurring->duration;
        $completed_date_str = (string)$task->recurring->completed;

        $now = new DateTime('today');
        $completed_date = new DateTime($completed_date_str);
        $interval = $completed_date->diff($now);

        $days_since_completed = $interval->days;
        if ($interval->invert) {
            $days_since_completed *= -1;
        }

        echo "Details: Repeats every $recur_duration " . pluralize($recur_duration, 'day', 'days') . ".\n";

        if ($days_since_completed >= 0) {
            echo "Status: Last completed on $completed_date_str (" . $days_since_completed . " " . pluralize($days_since_completed, 'day', 'days') . " ago).\n";
        } else {
            echo "Status: Last completed date is in the future ($completed_date_str).\n";
        }

        display_optional_history($task);
    }
}

if (!function_exists('describe_due_task')) {
    /**
     * Describes a task with a specific due date in detail.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function describe_due_task(SimpleXMLElement $task): void
    {
        display_task_header($task, 'Due Date');

        $due_date_str = (string)$task->due;
        $preview_duration = isset($task->preview) ? (int)$task->preview : 0;

        $now = new DateTime('today');
        $due_date = new DateTime($due_date_str);
        $due_interval = $now->diff($due_date);

        $days_until_due = $due_interval->days;

        echo "Details: Due on $due_date_str.\n";

        if ($due_interval->invert) {
            echo "Status: Overdue by $days_until_due " . pluralize($days_until_due, 'day', 'days') . ".\n";
        } elseif ($days_until_due === 0) {
            echo "Status: Due today.\n";
        } else {
            echo "Status: Due in $days_until_due " . pluralize($days_until_due, 'day', 'days') . ".\n";
        }

        if ($preview_duration > 0) {
            $display_date = (clone $due_date)->modify("-$preview_duration days");
            $display_interval = $now->diff($display_date);
            $days_until_display = $display_interval->days;

            echo "Preview: Set to display $preview_duration " . pluralize($preview_duration, 'day', 'days') . " in advance (on " . $display_date->format('Y-m-d') . ").\n";

            if ($display_interval->invert) {
                echo "Display Status: Is currently being displayed (for the last $days_until_display " . pluralize($days_until_display, 'day', 'days') . ").\n";
            } elseif ($days_until_display === 0) {
                echo "Display Status: Starts displaying today.\n";
            } else {
                echo "Display Status: Will be displayed in $days_until_display " . pluralize($days_until_display, 'day', 'days') . ".\n";
            }
        }

        display_optional_history($task);
    }
}

if (!function_exists('describe_normal_task')) {
    /**
     * Describes a normal task.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function describe_normal_task(SimpleXMLElement $task): void
    {
        display_task_header($task, 'Normal');

        echo "Details: This is a simple, one-off task.\n";
        if (isset($task->history)) {
            $completion_count = count($task->history->entry);
            echo "Status: Completed. History logged.\n";
            echo "History: " . $completion_count . " " . pluralize($completion_count, "completion", "completions") . " logged.\n";
        } else {
            echo "Status: Not yet completed.\n";
        }
    }
}

// --- Main Script Execution ---

echo "--- Describe a Task ---\n";

$filepath = select_task_file($argv, 'describe', 'all');

if ($filepath === null) {
    exit(0);
}

$xml = simplexml_load_file($filepath);
$type = get_task_type($xml);

switch ($type) {
    case 'recurring':
        describe_recurring_task($xml);
        break;
    case 'due':
        describe_due_task($xml);
        break;
    case 'normal':
        describe_normal_task($xml);
        break;
}
