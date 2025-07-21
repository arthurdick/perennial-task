<?php

declare(strict_types=1);

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


if (!function_exists('describe_scheduled_task')) {
    /**
     * Describes a scheduled task in detail.
     *
     * @param SimpleXMLElement $task The XML object for the task.
     */
    function describe_scheduled_task(SimpleXMLElement $task): void
    {
        display_task_header($task, 'Scheduled');

        $now = new DateTimeImmutable('today');
        $next_due_date = get_next_due_date($task, $now);

        if (!$next_due_date) {
            echo "Status: Could not determine next due date.\n";
            return;
        }

        echo "Details: Due on " . $next_due_date->format('Y-m-d') . ".\n";

        // --- Display Reschedule Logic ---
        if (isset($task->reschedule)) {
            echo "Reschedule: Automatically, every " . $task->reschedule->interval . ".\n";
            echo "Basis: Calculated from the " . str_replace('_', ' ', (string)$task->reschedule->from) . ".\n";
        } elseif (isset($task->recurring)) {
            echo "Reschedule: (Legacy Format) Repeats every " . $task->recurring->duration . " days from completion.\n";
        }

        // --- Status Logic ---
        $interval = $now->diff($next_due_date);
        if ($interval->invert) {
            echo "Status: Overdue by " . $interval->days . " " . pluralize($interval->days, 'day', 'days') . ".\n";
        } elseif ($interval->days === 0) {
            echo "Status: Due today.\n";
        } else {
            echo "Status: Due in " . $interval->days . " " . pluralize($interval->days, 'day', 'days') . ".\n";
        }

        // --- Display Preview & Display Status Logic ---
        if (isset($task->preview)) {
            $preview_days = (int)$task->preview;
            echo "Preview: Set to display " . $preview_days . " " . pluralize($preview_days, 'day', 'days') . " in advance.\n";

            // Check if the task is upcoming but not yet within its preview window
            if (!$interval->invert && $interval->days > $preview_days) {
                $days_until_display = $interval->days - $preview_days;
                echo "Display Status: Will be displayed in " . $days_until_display . " " . pluralize($days_until_display, 'day', 'days') . ".\n";
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
            echo "Status: Completed.\n";
        } else {
            echo "Status: Not yet completed.\n";
        }
        display_optional_history($task);
    }
}

// --- Main Script Execution ---

echo "--- Describe a Task ---\n";

// 1. This script has no specific options to define.
$long_options = [];

// 2. Call the common function to find the correct file.
$filepath = select_task_file($argv, $long_options, 'describe', 'all');

// 3. Exit if no file was found or selected.
if ($filepath === null) {
    exit(0);
}

$xml = simplexml_load_file($filepath);
$type = get_task_type($xml);

switch ($type) {
    case 'scheduled':
        describe_scheduled_task($xml);
        break;
    case 'normal':
        describe_normal_task($xml);
        break;
}
