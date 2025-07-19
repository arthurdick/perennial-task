<?php

require_once 'common.php';

// --- Argument Parsing ---

$options = getopt('', [
    "set-name:",
    "set-due::",
    "set-preview::",
    "remove-preview",
    "set-reschedule-interval::",
    "set-reschedule-from::",
    "remove-reschedule",
    "rename-file"
]);

// Remove the script name and potential filepath from argv to check for flags
$potential_flags = $argv;
array_shift($potential_flags);
if (isset($potential_flags[0]) && !str_starts_with($potential_flags[0], '-')) {
    array_shift($potential_flags);
}
$is_non_interactive = !empty($potential_flags);


// --- Helper Functions ---

if (!function_exists('display_current_details')) {
    function display_current_details(SimpleXMLElement $xml): void
    {
        echo "\n--- Current Task Details ---\n";
        echo "Name: " . $xml->name . "\n";
        $type = get_task_type($xml);
        echo "Type: " . ucfirst($type) . "\n";

        if ($type === 'scheduled') {
            if (isset($xml->due)) {
                echo "Due Date: " . $xml->due . "\n";
            }
            if (isset($xml->reschedule)) {
                echo "Reschedules: Every " . $xml->reschedule->interval . "\n";
                echo "Reschedule from: " . ucfirst(str_replace('_', ' ', $xml->reschedule->from)) . "\n";
            }
        }

        if (isset($xml->preview)) {
            echo "Preview: " . $xml->preview . " days in advance\n";
        }
        echo "---------------------------\n";
    }
}

if (!function_exists('process_edit_choice')) {
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

            case 't': // Change Task Type
                if ($currentType === 'normal') {
                    echo "Converting to a Scheduled task.\n";
                    collect_scheduled_task_details($xml);
                } else { // Is scheduled
                    if (get_yes_no_input("Convert to a Normal task? This will remove due date and reschedule settings. (y/N): ", 'n')) {
                        unset($xml->due);
                        if (isset($xml->reschedule)) {
                            unset($xml->reschedule);
                        }
                        if (isset($xml->preview)) {
                            unset($xml->preview);
                        }
                    }
                }
                break;

            case 'd': // Edit Due Date
                $xml->due = get_validated_date_input("Enter new due date (YYYY-MM-DD): ");
                break;

            case 'p': // Edit Preview
                $preview_days = get_optional_positive_integer_input("Enter new preview days (or press Enter to remove): ");
                if ($preview_days === null) {
                    if (isset($xml->preview)) {
                        unset($xml->preview);
                        echo "Preview setting removed.\n";
                    }
                } else {
                    $xml->preview = $preview_days;
                    echo "Preview set to $preview_days days.\n";
                }
                break;

            case 'r': // Edit Reschedule settings
                if (isset($xml->reschedule)) {
                    if (get_yes_no_input("Do you want to remove the existing reschedule settings? (y/N): ", 'n')) {
                        unset($xml->reschedule);
                        echo "Reschedule settings have been removed.\n";
                        break;
                    }
                }

                echo "Editing reschedule settings...\n";
                get_reschedule_input($xml);
                break;
        }
        return get_task_type($xml);
    }
}

// --- Main Script Execution ---

$filepath = select_task_file($argv, 'edit', 'active');
if ($filepath === null) {
    exit(0);
}

$xml = simplexml_load_file($filepath);
$original_name = (string)$xml->name;

// --- Automatic Migration from Legacy Format ---
if (migrate_legacy_task_if_needed($xml)) {
    echo "Notice: This task used a legacy format and has been automatically updated.\n";
}

// --- Mode Selection ---

if ($is_non_interactive) {
    // Non-Interactive Mode
    echo "--- Editing Task (Non-Interactive) ---\n";

    if (isset($options['set-name'])) {
        $xml->name = htmlspecialchars($options['set-name']);
        echo "Name set to: " . $options['set-name'] . "\n";
    }
    if (isset($options['set-due'])) {
        $xml->due = $options['set-due'];
        echo "Due date set to: " . $options['set-due'] . "\n";
    }
    if (isset($options['set-preview'])) {
        $xml->preview = $options['set-preview'];
        echo "Preview set to: " . $options['set-preview'] . " days\n";
    }
    if (isset($options['remove-preview'])) {
        unset($xml->preview);
        echo "Preview removed.\n";
    }
    if (isset($options['set-reschedule-interval'])) {
        if (!isset($xml->reschedule)) {
            $xml->addChild('reschedule');
        }
        $xml->reschedule->interval = $options['set-reschedule-interval'];
        echo "Reschedule interval set to: " . $options['set-reschedule-interval'] . "\n";
    }
    if (isset($options['set-reschedule-from'])) {
        if (!isset($xml->reschedule)) {
            $xml->addChild('reschedule');
        }
        $xml->reschedule->from = $options['set-reschedule-from'];
        echo "Reschedule basis set to: " . $options['set-reschedule-from'] . "\n";
    }
    if (isset($options['remove-reschedule'])) {
        unset($xml->reschedule);
        echo "Reschedule settings removed.\n";
    }

    if (isset($options['rename-file']) && isset($options['set-name'])) {
        $new_name = (string)$xml->name;
        $base_filename = sanitize_filename($new_name);
        $new_filepath = TASKS_DIR . '/' . $base_filename . '.xml';
        $counter = 1;
        while (file_exists($new_filepath) && realpath($new_filepath) !== realpath($filepath)) {
            $new_filepath = TASKS_DIR . '/' . $base_filename . '_' . $counter . '.xml';
            $counter++;
        }
        if (rename($filepath, $new_filepath)) {
            echo "File successfully renamed to '" . basename($new_filepath) . "'.\n";
            $filepath = $new_filepath;
        } else {
            echo "Error: Could not rename the file. Name changed in XML, but file not renamed.\n";
        }
    }
} else {
    // Interactive Mode
    echo "--- Edit an Existing Task ---\n";
    while (true) {
        display_current_details($xml);
        $type = get_task_type($xml);

        $menu_options = [
            'n' => 'Edit Name',
            't' => 'Change Task Type',
        ];
        if ($type === 'scheduled') {
            if (isset($xml->due)) {
                $menu_options['d'] = 'Edit Due Date';
            }
            $menu_options['r'] = 'Edit/Add Reschedule Settings';
            $menu_options['p'] = 'Edit Preview Days';
        }
        $menu_options['s'] = 'Save and Exit';

        $choice = get_menu_choice("What would you like to edit?", $menu_options);
        if ($choice === 's') {
            break;
        }

        process_edit_choice($xml, $choice);

        $new_name = (string)$xml->name;
        if ($new_name !== $original_name) {
            if (get_yes_no_input("Task name has changed. Rename the file on disk? (Y/n): ", 'y')) {
                $base_filename = sanitize_filename($new_name);
                $new_filepath = TASKS_DIR . '/' . $base_filename . '.xml';
                $counter = 1;
                while (file_exists($new_filepath) && realpath($new_filepath) !== realpath($filepath)) {
                    $new_filepath = TASKS_DIR . '/' . $base_filename . '_' . $counter . '.xml';
                    $counter++;
                }
                if (rename($filepath, $new_filepath)) {
                    echo "File successfully renamed to '" . basename($new_filepath) . "'.\n";
                    $filepath = $new_filepath;
                    $original_name = $new_name;
                } else {
                    echo "Error: Could not rename the file. Reverting name change.\n";
                    $xml->name = htmlspecialchars($original_name);
                }
            } else {
                $original_name = $new_name;
            }
        }
    }
}

// --- File Saving (Common to both modes) ---

if (save_xml_file($filepath, $xml)) {
    echo "\nSuccess! Task file updated at: $filepath\n";
} else {
    echo "\nError! Could not save the updated task file.\n";
    exit(1);
}
