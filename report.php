<?php

declare(strict_types=1);

require_once 'common.php';

// --- Constants ---

if (!defined('IS_INTERACTIVE')) {
    define('IS_INTERACTIVE', stream_isatty(STDOUT));
    define('COLOR_RED', IS_INTERACTIVE ? "\033[31m" : '');
    define('COLOR_YELLOW', IS_INTERACTIVE ? "\033[33m" : '');
    define('COLOR_BLUE', IS_INTERACTIVE ? "\033[34m" : '');
    define('COLOR_RESET', IS_INTERACTIVE ? "\033[0m" : '');
}


// --- Function Definitions ---

if (!function_exists('get_scheduled_task_report')) {
    /**
     * Processes a scheduled task and returns its status and report message.
     *
     * @param SimpleXMLElement $task The XML element for the task.
     * @param DateTimeImmutable $now The current date for comparison.
     * @return array|null An array with status and message, or null if not reportable.
     */
    function get_scheduled_task_report(SimpleXMLElement $task, DateTimeImmutable $now): ?array
    {
        $next_due_date = get_next_due_date($task, $now);
        if (!$next_due_date) {
            return null; // Should not happen for a scheduled task, but a good safeguard.
        }

        $priority = isset($task->priority) ? (int)$task->priority : 0;
        $preview_duration = isset($task->preview) ? (int)$task->preview : 0;
        $interval = $now->diff($next_due_date);
        $days_diff = $interval->days;

        if ($interval->invert) {
            // Due date is in the past.
            return [
                'status' => 'overdue',
                'priority' => $priority,
                'message' => COLOR_RED . "OVERDUE" . COLOR_RESET . ": " . (string)$task->name . " (was due $days_diff " . pluralize($days_diff, 'day', 'days') . " ago)\n"
            ];
        } elseif ($days_diff === 0) {
            // Due today.
            return [
                'status' => 'due_today',
                'priority' => $priority,
                'message' => COLOR_YELLOW . "DUE TODAY" . COLOR_RESET . ": " . (string)$task->name . "\n"
            ];
        } elseif ($days_diff <= $preview_duration) {
            // Due within the preview window.
            return [
                'status' => 'upcoming',
                'priority' => $priority,
                'message' => COLOR_BLUE . "UPCOMING" . COLOR_RESET . ": " . (string)$task->name . " (due in $days_diff " . pluralize($days_diff, 'day', 'days') . ")\n"
            ];
        }

        return null;
    }
}

if (!function_exists('get_normal_task_report')) {
    /**
     * Processes a normal task and returns its status and report message.
     * A normal task is only reportable if it has not been completed.
     */
    function get_normal_task_report(SimpleXMLElement $task): ?array
    {
        if (isset($task->history)) {
            return null;
        }
        $priority = isset($task->priority) ? (int)$task->priority : 0;
        return [
            'status' => 'due_today',
            'priority' => $priority,
            'message' => COLOR_YELLOW . "DUE TODAY" . COLOR_RESET . ": " . (string)$task->name . "\n"
        ];
    }
}

// --- Main Execution ---

echo "--- Task Report ---\n";

$now = new DateTimeImmutable('today');
// When called from `prn`, the command is at $argv[1], so the optional date is at $argv[2]
$date_arg = $argv[2] ?? null;

if ($date_arg) {
    try {
        $now = new DateTimeImmutable($date_arg);
        echo "Generating report for date: " . $now->format('Y-m-d') . "\n";
    } catch (Exception $e) {
        file_put_contents('php://stderr', "Error: Invalid date format provided. Please use a format like 'YYYY-MM-DD'.\n");
        exit(10);
    }
}
echo "-------------------\n";

$files = glob(TASKS_DIR . '/*.xml');
if (empty($files)) {
    echo "No tasks found.\n";
    exit(0);
}

$report_lines = [];
$invalid_files = [];
foreach ($files as $file) {
    if (!validate_task_file($file, true)) {
        $invalid_files[] = basename($file);
        continue;
    }

    $xml = simplexml_load_file($file);
    $type = get_task_type($xml);
    $report_data = null;

    if ($type === 'scheduled') {
        $report_data = get_scheduled_task_report($xml, $now);
    } elseif ($type === 'normal') {
        $report_data = get_normal_task_report($xml);
    }

    if ($report_data) {
        $report_lines[$report_data['status']][] = $report_data;
    }
}

$status_order = ['overdue', 'due_today', 'upcoming'];
$output_generated = false;

foreach ($status_order as $status) {
    if (!empty($report_lines[$status])) {
        // Sort the tasks within this status group by priority (descending)
        usort($report_lines[$status], function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        foreach ($report_lines[$status] as $line) {
            echo $line['message'];
        }
        $output_generated = true;
    }
}

if (!$output_generated) {
    echo "No tasks to report on at this time.\n";
}

// Display warnings for any any invalid files that were skipped.
if (!empty($invalid_files)) {
    echo "\n" . COLOR_YELLOW . "Warning:" . COLOR_RESET . " The following task files are invalid or corrupt and were skipped:\n";
    foreach ($invalid_files as $invalid_file) {
        echo "  - $invalid_file\n";
    }
}
