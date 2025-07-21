<?php

declare(strict_types=1);

require_once 'common.php';

echo "--- Complete a Task ---\n";

// 1. Define all possible long options for the 'complete' command.
$long_options = [
    "date:", // The colon indicates it requires a value.
];

// 2. Use the new manual parser to get options and a potential filepath.
$cli_args = parse_argv_manual($argv, $long_options);
$parsed_options = $cli_args['options'];
$filepath = $cli_args['filepath'];

// 3. If no filepath was provided on the command line, fall back to the interactive selector.
if ($filepath === null) {
    $filepath = select_task_file($argv, $long_options, 'complete', 'reportable');
}

// 4. Exit if no valid file was found or selected.
if ($filepath === null) {
    exit(0);
}

$xml = simplexml_load_file($filepath);
$task_name = (string)$xml->name;

// Determine completion date
$completion_date = date('Y-m-d'); // Default to today
if (isset($parsed_options['date'])) {
    if (validate_date($parsed_options['date'])) {
        $completion_date = $parsed_options['date'];
    } else {
        file_put_contents('php://stderr', "Error: Invalid format for --date. Use YYYY-MM-DD.\n");
        exit(1);
    }
}


if (!isset($xml->history)) {
    $xml->addChild('history');
}
$xml->history->addChild('entry', $completion_date);

$type = get_task_type($xml);

if ($type === 'normal') {
    echo "Task '$task_name' has been marked as complete on $completion_date.\n";
} elseif ($type === 'scheduled') {
    echo "Task '$task_name' was completed on $completion_date.\n";

    if (migrate_legacy_task_if_needed($xml)) {
        echo "Notice: Migrated task from old 'recurring' format.\n";
    }

    $is_reschedulable = isset($xml->reschedule);

    if (!$is_reschedulable) {
        echo "This task does not reschedule. Removing due date.\n";
        unset($xml->due);
        if (isset($xml->preview)) {
            unset($xml->preview);
        }
    } else {
        $reschedule_settings = $xml->reschedule;
        $base_date_str = ($reschedule_settings->from == 'due_date') ? (string)$xml->due : $completion_date;
        $interval = (string)$reschedule_settings->interval;

        try {
            $new_due_date = (new DateTime($base_date_str))->modify('+' . $interval)->format('Y-m-d');
            $xml->due = $new_due_date;
            echo "Task has been rescheduled to $new_due_date.\n";
        } catch (Exception $e) {
            echo "Error: Could not calculate next due date from invalid interval '$interval'. Task not rescheduled.\n";
        }
    }
}

if (save_xml_file($filepath, $xml)) {
    echo "Task file for '$task_name' updated successfully.\n";
} else {
    echo "Error: Could not save the updated task file.\n";
    exit(1);
}

$log_entry = date('c') . " | Completed: " . $task_name . " on " . $completion_date . "\n";
file_put_contents(COMPLETIONS_LOG, $log_entry, FILE_APPEND);
