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
        $menu_options = ['name' => 'Edit Name', 'type' => 'Change Task Type'];
        switch ($type) {
            case 'due':
                $menu_options['due'] = 'Edit Due Date';
                $menu_options['preview'] = 'Edit/Add Preview Days';
                break;
            case 'recurring':
                $menu_options['completed'] = 'Edit Last Completed Date';
                $menu_options['duration'] = 'Edit Recurrence Duration';
                $menu_options['preview'] = 'Edit/Add Preview Days';
                break;
        }
        $menu_options['save'] = 'Save and Exit';

        echo "What would you like to edit?\n";
        $i = 1;
        $indexed_options = [];
        foreach ($menu_options as $key => $text) {
            echo "  [$i] $text\n";
            $indexed_options[$i] = $key;
            $i++;
        }
        
        while (true) {
            $input = prompt_user("Enter your choice: ");
            if (ctype_digit($input) && isset($indexed_options[(int)$input])) {
                return $indexed_options[(int)$input];
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
            case 'name':
                $newName = '';
                while (empty(trim($newName))) {
                    $newName = prompt_user("Enter the new task name: ");
                    if (empty(trim($newName))) echo "Name cannot be empty.\n";
                }
                $xml->name = htmlspecialchars($newName);
                break;
            case 'type':
                $type_choice = '';
                $validTypes = ['1' => 'normal', '2' => 'due', '3' => 'recurring'];
                while (!array_key_exists($type_choice, $validTypes)) {
                    echo "Select new task type:\n";
                    echo "  [1] Normal (a simple, one-off task)\n";
                    echo "  [2] Due (a task with a specific due date)\n";
                    echo "  [3] Recurring (a task that repeats)\n";
                    $type_choice = prompt_user("Enter your choice: ");
                }
                $newType = $validTypes[$type_choice];

                if ($newType !== $currentType) {
                    // Remove old type-specific elements
                    if (isset($xml->due)) unset($xml->due);
                    if (isset($xml->recurring)) unset($xml->recurring);
                    if (isset($xml->preview)) unset($xml->preview); // Preview is tied to due/recurring

                    // Add new type-specific elements by calling the shared functions
                    if ($newType === 'due') {
                        collect_due_task_details($xml);
                    } elseif ($newType === 'recurring') {
                        collect_recurring_task_details($xml);
                    }
                    echo "Task type changed to '" . ucfirst($newType) . "'.\n";
                    return $newType;
                }
                break;
            case 'due':
                $dueDate = null;
                while ($dueDate === null) {
                    $dateStr = prompt_user("Enter new due date (YYYY-MM-DD): ");
                    if (validate_date($dateStr)) $dueDate = $dateStr;
                    else echo "Invalid date format.\n";
                }
                $xml->due = $dueDate;
                break;
            case 'completed':
                $completedDate = null;
                while ($completedDate === null) {
                    $dateStr = prompt_user("Enter new last completed date (YYYY-MM-DD): ");
                    if (validate_date($dateStr)) $completedDate = $dateStr;
                    else echo "Invalid date format.\n";
                }
                $xml->recurring->completed = $completedDate;
                break;
            case 'duration':
                $duration = '';
                while (!ctype_digit($duration) || (int)$duration <= 0) {
                    $duration = prompt_user("Recur every X days (e.g., 7): ");
                    if (!ctype_digit($duration) || (int)$duration <= 0) echo "Please enter a positive number.\n";
                }
                $xml->recurring->duration = $duration;
                break;
            case 'preview':
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
// By this point, $filepath is set and validated.

// Load the selected XML file for editing.
$xml = simplexml_load_file($filepath);
$type = get_task_type($xml); // Get initial type

// Enter the editing loop.
while (true) {
    display_current_details($xml);
    $choice = show_edit_menu($type);

    if ($choice === 'save') {
        break; // Exit the loop to save the file.
    }
    
    $type = process_edit_choice($xml, $choice); // Update type in case it changed
}

// Save the modified XML file using the shared function.
if (save_xml_file($filepath, $xml)) {
    echo "\nSuccess! Task file updated at: $filepath\n";
} else {
    echo "\nError! Could not save the updated task file.\n";
}

