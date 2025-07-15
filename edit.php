<?php

require_once 'common.php';

// --- Helper Functions ---

if (!function_exists('display_current_details')) {
    /**
     * Displays the current details of the loaded task.
     * @param SimpleXMLElement $xml The task's XML object.
     */
    function display_current_details(SimpleXMLElement $xml): void
    {
        echo "\n--- Current Task Details ---\n";
        echo "Name: " . $xml->name . "\n";
        $type = get_task_type($xml);
        echo "Type: " . ucfirst($type) . "\n";

        switch ($type) {
            case 'due':
                echo "Due Date: " . $xml->due . "\n";
                break;
            case 'recurring':
                echo "Last Completed: " . $xml->recurring->completed . "\n";
                echo "Recurs Every: " . $xml->recurring->duration . " days\n";
                break;
        }
        if (isset($xml->preview)) {
            echo "Preview: " . $xml->preview . " days in advance\n";
        }
        echo "---------------------------\n";
    }
}

if (!function_exists('process_edit_choice')) {
    /**
     * Calls the correct function to handle the user's edit choice.
     * @param SimpleXMLElement $xml The XML object to modify.
     * @param string $choice The user's chosen action.
     * @return string The potentially new task type.
     */
    function process_edit_choice(SimpleXMLElement $xml, string $choice): string
    {
        $currentType = get_task_type($xml);

        switch ($choice) {
            case 'n': // Name
                $newName = '';
                while (empty(trim($newName))) {
                    $newName = prompt_user("Enter the new task name: ");
                    if (empty(trim($newName))) {
                        echo "Name cannot be empty.\n";
                    }
                }
                $xml->name = htmlspecialchars($newName);
                break;
            case 't': // Type
                $type_options = [
                    'n' => 'Normal',
                    'd' => 'Due',
                    'r' => 'Recurring'
                ];
                $type_choice = get_menu_choice("Select new task type:", $type_options);
                $validTypes = ['n' => 'normal', 'd' => 'due', 'r' => 'recurring'];
                $newType = $validTypes[$type_choice];

                if ($newType !== $currentType) {
                    if (isset($xml->due)) {
                        unset($xml->due);
                    }
                    if (isset($xml->recurring)) {
                        unset($xml->recurring);
                    }
                    if (isset($xml->preview)) {
                        unset($xml->preview);
                    }

                    if ($newType === 'due') {
                        collect_due_task_details($xml);
                    } elseif ($newType === 'recurring') {
                        collect_recurring_task_details($xml);
                    }
                    echo "Task type changed to '" . ucfirst($newType) . "'.\n";
                    return $newType;
                }
                break;
            case 'd': // Due date
                $xml->due = get_validated_date_input("Enter new due date (YYYY-MM-DD): ");
                break;
            case 'c': // Completed date
                $xml->recurring->completed = get_validated_date_input("Enter new last completed date (YYYY-MM-DD): ");
                break;
            case 'r': // Recurrence duration
                $xml->recurring->duration = get_positive_integer_input("Recur every X days (e.g., 7): ");
                break;
            case 'p': // Preview
                $preview = get_optional_positive_integer_input("Preview days in advance? (Enter 0 to remove, Enter to skip): ");
                if ($preview !== null) {
                    if ($preview > 0) {
                        if (isset($xml->preview)) {
                            $xml->preview = $preview;
                        } else {
                            $xml->addChild('preview', $preview);
                        }
                    } else { // $preview is 0
                        if (isset($xml->preview)) {
                            unset($xml->preview);
                        }
                    }
                }
                break;
        }
        return $currentType; // Return original type if no change occurred
    }
}

// --- Main Script Execution ---

echo "--- Edit an Existing Task ---\n";

// Use the shared function to select a task file.
$filepath = select_task_file($argv, 'edit', 'active');

// If no file was selected or found, exit gracefully.
if ($filepath === null) {
    exit(0);
}

// --- Main Edit Process ---
$xml = simplexml_load_file($filepath);
$type = get_task_type($xml);
$original_name = (string)$xml->name; // Store original name before loop

// Enter the editing loop.
while (true) {
    display_current_details($xml);

    $menu_options = [
        'n' => 'Edit Name',
        't' => 'Change Task Type',
    ];
    switch ($type) {
        case 'due':
            $menu_options['d'] = 'Edit Due Date';
            $menu_options['p'] = 'Edit/Add Preview Days';
            break;
        case 'recurring':
            $menu_options['c'] = 'Edit Last Completed Date';
            $menu_options['r'] = 'Edit Recurrence Duration';
            $menu_options['p'] = 'Edit/Add Preview Days';
            break;
    }
    $menu_options['s'] = 'Save and Exit';

    $choice = get_menu_choice("What would you like to edit?", $menu_options);

    if ($choice === 's') { // Save
        break;
    }

    $type = process_edit_choice($xml, $choice);

    // --- RENAME LOGIC ---
    $new_name = (string)$xml->name;
    if ($new_name !== $original_name) {
        if (get_yes_no_input("Task name has changed. Rename the file on disk? (Y/n): ", 'y')) {
            $base_filename = sanitize_filename($new_name);
            $new_filepath = TASKS_DIR . '/' . $base_filename . '.xml';
            $counter = 1;

            // Find a unique filename if the desired one exists
            while (file_exists($new_filepath) && realpath($new_filepath) !== realpath($filepath)) {
                $new_filepath = TASKS_DIR . '/' . $base_filename . '_' . $counter . '.xml';
                $counter++;
            }

            if (rename($filepath, $new_filepath)) {
                echo "File successfully renamed to '" . basename($new_filepath) . "'.\n";
                $filepath = $new_filepath; // IMPORTANT: Update filepath for saving
                $original_name = $new_name; // Update original_name to prevent re-prompting
            } else {
                echo "Error: Could not rename the file. Please check permissions. Task name has been reverted.\n";
                $xml->name = htmlspecialchars($original_name); // Revert name in XML
            }
        } else {
            // If user says 'n', update original_name to prevent asking again in this session
            $original_name = $new_name;
        }
    }
    // --- END RENAME LOGIC ---
}

// Save the modified XML file using the shared function.
if (save_xml_file($filepath, $xml)) {
    echo "\nSuccess! Task file updated at: $filepath\n";
} else {
    echo "\nError! Could not save the updated task file.\n";
}
