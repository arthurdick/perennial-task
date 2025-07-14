<?php

require_once 'common.php';

// --- Main Script Execution ---

echo "--- Complete a Task ---\n";

// Use the shared function to select a task file.
$filepath = select_task_file($argv, 'complete', 'reportable');

// If no file was selected or found, exit gracefully.
if ($filepath === null) {
    exit(0);
}

// --- Main Completion Process ---

// Load the selected XML file.
$xml = simplexml_load_file($filepath);
$task_name = (string)$xml->name;

// 1. Add a completion record to the task's history.
if (!isset($xml->history)) {
    $xml->addChild('history');
}
$xml->history->addChild('entry', date('Y-m-d'));


// 2. Handle the task based on its type.
$type = get_task_type($xml);

switch ($type) {
    case 'normal':
        echo "Task '$task_name' has been marked as complete.\n";
        break;

    case 'due':
        while (true) {
            $input = prompt_user("Enter new due date (YYYY-MM-DD), or 'never' to remove: ");
            if (strtolower($input) === 'never') {
                unset($xml->due); // Remove the due date
                echo "Task '$task_name' has been marked as complete and will no longer have a due date.\n";
                break; // Exit loop
            } elseif (validate_date($input)) {
                $xml->due = $input;
                echo "Task '$task_name' has been updated with a new due date of $input.\n";
                break; // Exit loop
            } else {
                echo "Invalid input. Please use YYYY-MM-DD format or type 'never'.\n";
            }
        }
        break;

    case 'recurring':
        while (true) {
            $input = strtolower(prompt_user("Will this task recur? (y/n): "));
            if ($input === 'n') {
                unset($xml->recurring); // Remove the recurring element
                echo "Task '$task_name' has been marked as complete and will no longer recur.\n";
                break; // Exit loop
            } elseif ($input === 'y') {
                $new_completed_date = null;
                while($new_completed_date === null) {
                    $date_input = prompt_user("Enter new completion date (YYYY-MM-DD, press Enter for today): ");
                    if (empty($date_input)) {
                        $date_input = date('Y-m-d');
                    }
                    if (validate_date($date_input)) {
                        $new_completed_date = $date_input;
                    } else {
                        echo "Invalid date format. Please use YYYY-MM-DD.\n";
                    }
                }
                
                $xml->recurring->completed = $new_completed_date;
                echo "Task '$task_name' has been updated with a new completion date of $new_completed_date.\n";
                break; // Exit loop
            } else {
                echo "Invalid input. Please enter 'y' or 'n'.\n";
            }
        }
        break;
}

// 3. Save the modified file.
if (save_xml_file($filepath, $xml)) {
    echo "Task file for '$task_name' updated successfully.\n";
} else {
    echo "Error: Could not save the updated task file.\n";
}

// 4. Log to the central completions log.
$log_entry = date('c') . " | Completed: " . $task_name . "\n";
file_put_contents(COMPLETIONS_LOG, $log_entry, FILE_APPEND);

echo "Completion process finished.\n";

