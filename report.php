#!/usr/bin/env php
<?php

require_once 'common.php';

// --- Constants ---

if (!defined('IS_INTERACTIVE')) {
    // Check if the script is running in an interactive terminal.
    define('IS_INTERACTIVE', stream_isatty(STDOUT));

    // Define ANSI color codes. If not interactive, the codes are empty strings.
    define('COLOR_RED', IS_INTERACTIVE ? "\033[31m" : '');
    define('COLOR_YELLOW', IS_INTERACTIVE ? "\033[33m" : '');
    define('COLOR_BLUE', IS_INTERACTIVE ? "\033[34m" : '');
    define('COLOR_RESET', IS_INTERACTIVE ? "\033[0m" : '');
}


// --- Function Definitions ---

if (!function_exists('get_recurring_task_report')) {
    /**
     * Processes a recurring task and returns its status and report message.
     *
     * @param SimpleXMLElement $task The XML element for the task.
     * @param DateTimeImmutable $now The current date for comparison.
     * @return array|null An array with status and message, or null if not reportable.
     */
    function get_recurring_task_report(SimpleXMLElement $task, DateTimeImmutable $now): ?array
    {
        $name = (string)$task->name;
        $completed_date = new DateTimeImmutable((string)$task->recurring->completed);
        $recur_duration = (int)$task->recurring->duration;
        $preview_duration = isset($task->preview) ? (int)$task->preview : 0;

        // Calculate the next due date by adding the duration to the last completed date.
        $next_due_date = $completed_date->modify("+$recur_duration days");
        
        $interval = $now->diff($next_due_date);
        $days_until_due = $interval->days;

        if ($interval->invert) {
            // If the interval is inverted, the next due date is in the past, so it's overdue.
            $days_overdue = $days_until_due;
            return [
                'status' => 'overdue',
                'message' => COLOR_RED . "OVERDUE" . COLOR_RESET . ": $name (was due $days_overdue " . pluralize_days($days_overdue) . " ago)\n"
            ];
        } elseif ($days_until_due === 0) {
            // Due today.
            return [
                'status' => 'due_today',
                'message' => COLOR_YELLOW . "DUE TODAY" . COLOR_RESET . ": $name\n"
            ];
        } elseif ($days_until_due <= $preview_duration) {
            // Due within the preview window.
            return [
                'status' => 'upcoming',
                'message' => COLOR_BLUE . "UPCOMING" . COLOR_RESET . ": $name (due in $days_until_due " . pluralize_days($days_until_due) . ")\n"
            ];
        }
        
        // If none of the above, the task is not yet within the preview window, so we don't report it.
        return null;
    }
}

if (!function_exists('get_due_task_report')) {
    /**
     * Processes a task with a specific due date and returns its status and report message.
     *
     * @param SimpleXMLElement $task The XML element for the task.
     * @param DateTimeImmutable $now The current date for comparison.
     * @return array|null An array with status and message, or null if not reportable.
     */
    function get_due_task_report(SimpleXMLElement $task, DateTimeImmutable $now): ?array
    {
        $name = (string)$task->name;
        $due_date = new DateTimeImmutable((string)$task->due);
        $preview_duration = isset($task->preview) ? (int)$task->preview : 0;

        $interval = $now->diff($due_date);
        $days_until_due = $interval->days;

        if ($interval->invert) {
            // Due date is in the past.
            $days_overdue = $days_until_due;
            return [
                'status' => 'overdue',
                'message' => COLOR_RED . "OVERDUE" . COLOR_RESET . ": $name (was due $days_overdue " . pluralize_days($days_overdue) . " ago)\n"
            ];
        } elseif ($days_until_due === 0) {
            // Due today.
            return [
                'status' => 'due_today',
                'message' => COLOR_YELLOW . "DUE TODAY" . COLOR_RESET . ": $name\n"
            ];
        } elseif ($days_until_due <= $preview_duration) {
            // Due within the preview window.
            return [
                'status' => 'upcoming',
                'message' => COLOR_BLUE . "UPCOMING" . COLOR_RESET . ": $name (due in $days_until_due " . pluralize_days($days_until_due) . ")\n"
            ];
        }

        // If not within the preview window, do not report.
        return null;
    }
}

if (!function_exists('get_normal_task_report')) {
    /**
     * Processes a normal task and returns its status and report message.
     *
     * @param SimpleXMLElement $task The XML element for the task.
     * @return array An array with status and message.
     */
    function get_normal_task_report(SimpleXMLElement $task): array
    {
        // A normal task is always considered active.
        $name = (string)$task->name;
        return [
            'status' => 'due_today',
            'message' => COLOR_YELLOW . "DUE TODAY" . COLOR_RESET . ": $name\n"
        ];
    }
}

// --- Initialization ---

echo "--- Task Report ---\n";

// Use today's date at midnight as the baseline.
// If a date is provided as a command-line argument, use that instead for testing.
$now = new DateTimeImmutable('today');
if (isset($argv[1])) {
    try {
        $now = new DateTimeImmutable($argv[1]);
        echo "Generating report for date: " . $now->format('Y-m-d') . "\n";
    } catch (Exception $e) {
        echo "Error: Invalid date format provided. Please use a format like 'YYYY-MM-DD'.\n";
        exit(1);
    }
}
echo "-------------------\n";

// --- Main Processing Loop ---

$files = glob(TASKS_DIR . '/*.xml');

// Arrays to hold tasks by status
$overdue_tasks = [];
$due_today_tasks = [];
$upcoming_tasks = [];

foreach ($files as $file) {
    // Use the shared validation function. Silently skip invalid files in report mode.
    if (!validate_task_file($file, true)) {
        continue;
    }

    $xml = simplexml_load_file($file);
    $type = get_task_type($xml);
    $report_data = null;

    // Dispatch to the appropriate function to get report data.
    switch ($type) {
        case 'recurring':
            $report_data = get_recurring_task_report($xml, $now);
            break;
        case 'due':
            $report_data = get_due_task_report($xml, $now);
            break;
        case 'normal':
            $report_data = get_normal_task_report($xml);
            break;
    }
    
    // If the task is reportable, add its message to the correct category.
    if ($report_data) {
        switch ($report_data['status']) {
            case 'overdue':
                $overdue_tasks[] = $report_data['message'];
                break;
            case 'due_today':
                $due_today_tasks[] = $report_data['message'];
                break;
            case 'upcoming':
                $upcoming_tasks[] = $report_data['message'];
                break;
        }
    }
}

// --- Report Output ---

if (empty($overdue_tasks) && empty($due_today_tasks) && empty($upcoming_tasks)) {
    echo "No tasks to report on at this time.\n";
    exit(0);
}

// Print tasks in the desired order: Overdue -> Due Today -> Upcoming
if (!empty($overdue_tasks)) {
    foreach ($overdue_tasks as $task_line) {
        echo $task_line;
    }
}

if (!empty($due_today_tasks)) {
    foreach ($due_today_tasks as $task_line) {
        echo $task_line;
    }
}

if (!empty($upcoming_tasks)) {
    foreach ($upcoming_tasks as $task_line) {
        echo $task_line;
    }
}
