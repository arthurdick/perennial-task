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
        if (isset($xml->history)) {
            echo "Completion History: " . $xml->history->count() . " " . ($xml->history->count() === 1 ? "entry" : "entries") . "\n";
        }
        echo "---------------------------\n";
    }
}

if (!function_exists('show_edit_menu')) {
    /**
     * Displays the appropriate edit menu and gets the user's choice.
     * @param string $type The type of task.
     * @return string The user's validated choice.
     */
    function show_edit_menu(string $type): string
    {
        // Define menu options with sensible letter commands
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

        echo "What would you like to edit?\n";
        foreach ($menu_options as $key => $text) {
            echo "  ($key) $text\n";
        }
        
        while (true) {
            $input = strtolower(prompt_user("Enter your choice: "));
            if (array_key_exists($input, $menu_options)) {
                return $input;
            }
            echo "Invalid choice. Please try again.\n";
        }
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
                    if (empty(trim($newName))) echo "Name cannot be empty.\n";
                }
                $xml->name = htmlspecialchars($newName);
                break;
            case 't': // Type
                $type_choice = '';
                $validTypes = ['n' => 'normal', 'd' => 'due', 'r' => 'recurring'];
                while (!array_key_exists($type_choice, $validTypes)) {
                    echo "Select new task type:\n";
                    echo "  (n) Normal\n  (d) Due\n  (r) Recurring\n";
                    $type_choice = strtolower(prompt_user("Enter your choice: "));
                }
                $newType = $validTypes[$type_choice];

                if ($newType !== $currentType) {
                    if (isset($xml->due)) unset($xml->due);
                    if (isset($xml->recurring)) unset($xml->recurring);
                    if (isset($xml->preview)) unset($xml->preview);

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
                $dueDate = null;
                while ($dueDate === null) {
                    $dateStr = prompt_user("Enter new due date (YYYY-MM-DD): ");
                    if (validate_date($dateStr)) $dueDate = $dateStr;
                    else echo "Invalid date format.\n";
                }
                $xml->due = $dueDate;
                break;
            case 'c': // Completed date
                $completedDate = null;
                while ($completedDate === null) {
                    $dateStr = prompt_user("Enter new last completed date (YYYY-MM-DD): ");
                    if (validate_date($dateStr)) $completedDate = $dateStr;
                    else echo "Invalid date format.\n";
                }
                $xml->recurring->completed = $completedDate;
                break;
            case 'r': // Recurrence duration
                $duration = '';
                while (!ctype_digit($duration) || (int)$duration <= 0) {
                    $duration = prompt_user("Recur every X days (e.g., 7): ");
                    if (!ctype_digit($duration) || (int)$duration <= 0) echo "Please enter a positive number.\n";
                }
                $xml->recurring->duration = $duration;
                break;
            case 'p': // Preview
                $preview = prompt_user("Preview days in advance? (Enter a number, or 0 to remove): ");
                if (ctype_digit($preview)) {
                    if ((int)$preview > 0) {
                        if (isset($xml->preview)) {
                            $xml->preview = $preview;
                        } else {
                            $xml->addChild('preview', $preview);
                        }
                    } else {
                        if (isset($xml->preview)) {
                            unset($xml->preview);
                        }
                    }
                } else {
                    echo "Invalid input. Please enter a whole number.\n";
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

// Enter the editing loop.
while (true) {
    display_current_details($xml);
    $choice = show_edit_menu($type);

    if ($choice === 's') { // Save
        break; 
    }
    
    $type = process_edit_choice($xml, $choice);
}

// Save the modified XML file using the shared function.
if (save_xml_file($filepath, $xml)) {
    echo "\nSuccess! Task file updated at: $filepath\n";
} else {
    echo "\nError! Could not save the updated task file.\n";
}

