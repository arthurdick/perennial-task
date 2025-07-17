<?php

require_once 'common.php';

// --- Main Script Execution ---

echo "--- Complete a Task ---\n";

$filepath = select_task_file($argv, 'complete', 'reportable');
if ($filepath === null) {
    exit(0);
}

$xml = simplexml_load_file($filepath);
$task_name = (string)$xml->name;

$completion_date = get_validated_date_input("Enter completion date (YYYY-MM-DD, press Enter for today): ", true);

if (!isset($xml->history)) {
    $xml->addChild('history');
}
$xml->history->addChild('entry', $completion_date);

$type = get_task_type($xml);

if ($type === 'normal') {
    echo "Task '$task_name' has been marked as complete on $completion_date.\n";
} elseif ($type === 'scheduled') {
    echo "Task '$task_name' was completed on $completion_date.\n";

    // --- Migration from old formats ---
    if (migrate_legacy_task_if_needed($xml)) {
        echo "Notice: Migrated task from old 'recurring' format.\n";
    }

    $is_reschedulable = isset($xml->reschedule);

    if (!$is_reschedulable) {
        if (get_yes_no_input("This task does not reschedule. Mark as complete and remove due date? (Y/n): ", 'y')) {
            unset($xml->due);
            if (isset($xml->preview)) {
                unset($xml->preview);
            }
        }
    } else {
        // --- New Reschedule Logic ---
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
