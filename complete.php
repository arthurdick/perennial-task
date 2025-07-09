<?php

require_once 'common.php';

// --- Main Script Execution ---

echo "--- Complete a Task ---\n";

// Use the shared function to select a task file.
$filepath = select_task_file($argv, 'complete');

// If no file was selected or found, exit gracefully.
if ($filepath === null) {
    exit(0);
}

// --- Main Completion Process ---

// Load the selected XML file.
$xml = simplexml_load_file($filepath);
$task_name = (string)$xml->name;

// 1. Create a completion record in the log file.
$log_entry = date('c') . " | Completed: " . $task_name . "\n";
file_put_contents(COMPLETIONS_LOG, $log_entry, FILE_APPEND);

// 2. Handle the task based on its type.
$type = get_task_type($xml);

switch ($type) {
    case 'normal':
        if (unlink($filepath)) {
            echo "Task '$task_name' was a normal task and has been deleted.\n";
        } else {
            echo "Error: Could not delete the task file for '$task_name'.\n";
        }
        break;

    case 'due':
        while (true) {
            $input = prompt_user("Enter new due date (YYYY-MM-DD), or 'never' to remove: ");
            if (strtolower($input) === 'never') {
                if (unlink($filepath)) {
                    echo "Task '$task_name' will not have a new due date and has been deleted.\n";
                } else {
                    echo "Error: Could not delete the task file for '$task_name'.\n";
                }
                break; // Exit loop
            } elseif (validate_date($input)) {
                $xml->due = $input;
                if (save_xml_file($filepath, $xml)) {
                    echo "Task '$task_name' has been updated with a new due date of $input.\n";
                } else {
                    echo "Error: Could not save the updated task file.\n";
                }
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
                if (unlink($filepath)) {
                    echo "Task '$task_name' will not recur and has been deleted.\n";
                } else {
                    echo "Error: Could not delete the task file for '$task_name'.\n";
                }
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
                if (save_xml_file($filepath, $xml)) {
                    echo "Task '$task_name' has been updated with a new completion date of $new_completed_date.\n";
                } else {
                    echo "Error: Could not save the updated task file.\n";
                }
                break; // Exit loop
            } else {
                echo "Invalid input. Please enter 'y' or 'n'.\n";
            }
        }
        break;
}

echo "Completion process finished.\n";

