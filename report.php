#!/usr/bin/env php
<?php

require_once 'common.php';

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

if (empty($files)) {
    echo "No tasks found to report on.\n";
    exit(0);
}

foreach ($files as $file) {
    // Use the shared validation function. Silently skip invalid files in report mode.
    if (!validate_task_file($file, true)) {
        continue;
    }

    $xml = simplexml_load_file($file);
    $type = get_task_type($xml);

    // Dispatch to the appropriate reporting function based on type.
    switch ($type) {
        case 'recurring':
            report_on_recurring_task($xml, $now);
            break;
        case 'due':
            report_on_due_task($xml, $now);
            break;
        case 'normal':
            report_on_normal_task($xml);
            break;
    }
}


// --- Function Definitions ---

/**
 * Processes and reports on a recurring task.
 *
 * @param SimpleXMLElement $task The XML element for the task.
 * @param DateTimeImmutable $now The current date for comparison.
 */
function report_on_recurring_task(SimpleXMLElement $task, DateTimeImmutable $now): void
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
        echo "OVERDUE: $name (was due $days_overdue " . pluralize_days($days_overdue) . " ago)\n";
    } elseif ($days_until_due === 0) {
        // Due today.
        echo "DUE TODAY: $name\n";
    } elseif ($days_until_due <= $preview_duration) {
        // Due within the preview window.
        echo "UPCOMING: $name (due in $days_until_due " . pluralize_days($days_until_due) . ")\n";
    }
    // If none of the above, the task is not yet within the preview window, so we don't report it.
}

/**
 * Processes and reports on a task with a specific due date.
 *
 * @param SimpleXMLElement $task The XML element for the task.
 * @param DateTimeImmutable $now The current date for comparison.
 */
function report_on_due_task(SimpleXMLElement $task, DateTimeImmutable $now): void
{
    $name = (string)$task->name;
    $due_date = new DateTimeImmutable((string)$task->due);
    $preview_duration = isset($task->preview) ? (int)$task->preview : 0;

    $interval = $now->diff($due_date);
    $days_until_due = $interval->days;

    if ($interval->invert) {
        // Due date is in the past.
        $days_overdue = $days_until_due;
        echo "OVERDUE: $name (was due $days_overdue " . pluralize_days($days_overdue) . " ago)\n";
    } elseif ($days_until_due === 0) {
        // Due today.
        echo "DUE TODAY: $name\n";
    } elseif ($days_until_due <= $preview_duration) {
        // Due within the preview window.
        echo "UPCOMING: $name (due in $days_until_due " . pluralize_days($days_until_due) . ")\n";
    }
    // If not within the preview window, do not report.
}

/**
 * Processes and reports on a normal task.
 *
 * @param SimpleXMLElement $task The XML element for the task.
 */
function report_on_normal_task(SimpleXMLElement $task): void
{
    // A normal task is always considered active.
    $name = (string)$task->name;
    echo "DUE TODAY: $name\n";
}
