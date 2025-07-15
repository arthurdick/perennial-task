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

// 1. Get the completion date from the user FIRST.
$completion_date = get_validated_date_input("Enter completion date (YYYY-MM-DD, press Enter for today): ", true);

// 2. Add the correct completion record to the task's history.
if (!isset($xml->history)) {
    $xml->addChild('history');
}
$xml->history->addChild('entry', $completion_date);

// 3. Handle the task based on its type.
$type = get_task_type($xml);

switch ($type) {
    case 'normal':
        echo "Task '$task_name' has been marked as complete on $completion_date.\n";
        break;

    case 'due':
        echo "Task '$task_name' was completed on $completion_date.\n";
        while (true) {
            $input = prompt_user("Enter new due date (YYYY-MM-DD), or 'never' to remove: ");
            if (strtolower($input) === 'never') {
                unset($xml->due);
                echo "Task will no longer have a due date.\n";
                break;
            } elseif (validate_date($input)) {
                $xml->due = $input;
                echo "Task has been updated with a new due date of $input.\n";
                break;
            } else {
                echo "Invalid input. Please use YYYY-MM-DD format or type 'never'.\n";
            }
        }
        break;

    case 'recurring':
        echo "Task '$task_name' was completed on $completion_date.\n";
        while (true) {
            $input = strtolower(prompt_user("Will this task recur? (y/n): "));
            if ($input === 'n') {
                unset($xml->recurring);
                echo "Task will no longer recur.\n";
                break;
            } elseif ($input === 'y') {
                // Use the already-provided completion date for the recurring->completed tag
                $xml->recurring->completed = $completion_date;
                echo "Task has been updated with a new completion date of $completion_date.\n";
                break;
            } else {
                echo "Invalid input. Please enter 'y' or 'n'.\n";
            }
        }
        break;
}

// 4. Save the modified file.
if (save_xml_file($filepath, $xml)) {
    echo "Task file for '$task_name' updated successfully.\n";
} else {
    echo "Error: Could not save the updated task file.\n";
}

// 5. Log to the central completions log.
$log_entry = date('c') . " | Completed: " . $task_name . " on " . $completion_date . "\n";
file_put_contents(COMPLETIONS_LOG, $log_entry, FILE_APPEND);

echo "Completion process finished.\n";
