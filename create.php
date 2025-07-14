<?php

require_once 'common.php';

// --- Helper Functions ---

if (!function_exists('collect_due_task_details')) {
    /**
     * Interactively collects details for a 'due' task.
     * @param SimpleXMLElement $xml The XML object to modify.
     */
    function collect_due_task_details(SimpleXMLElement $xml): void
    {
        $dueDate = null;
        while ($dueDate === null) {
            $dateStr = prompt_user("Enter due date (YYYY-MM-DD): ");
            if (validate_date($dateStr)) {
                $dueDate = $dateStr;
            } else {
                echo "Invalid date format. Please use YYYY-MM-DD.\n";
            }
        }
        $xml->addChild('due', $dueDate);
    }
}

if (!function_exists('collect_recurring_task_details')) {
    /**
     * Interactively collects details for a 'recurring' task.
     * @param SimpleXMLElement $xml The XML object to modify.
     */
    function collect_recurring_task_details(SimpleXMLElement $xml): void
    {
        $completedDate = null;
        while ($completedDate === null) {
            $dateStr = prompt_user("Enter last completed date (YYYY-MM-DD, press Enter for today): ");
            if (empty($dateStr)) {
                $dateStr = date('Y-m-d');
            }
            if (validate_date($dateStr)) {
                $completedDate = $dateStr;
            } else {
                echo "Invalid date format. Please use YYYY-MM-DD.\n";
            }
        }

        $duration = '';
        while (!ctype_digit($duration) || (int)$duration <= 0) {
            $duration = prompt_user("Recur every X days (e.g., 7): ");
            if (!ctype_digit($duration) || (int)$duration <= 0) {
                echo "Please enter a positive whole number for the duration.\n";
            }
        }

        $recurring = $xml->addChild('recurring');
        $recurring->addChild('completed', $completedDate);
        $recurring->addChild('duration', $duration);
    }
}

// --- Main Script Execution ---

echo "--- Create a New Task ---\n";

// Ensure the tasks directory exists, create it if it doesn't.
if (!is_dir(TASKS_DIR)) {
    if (mkdir(TASKS_DIR, 0755, true)) {
        echo "Created tasks directory.\n";
    } else {
        echo "Error: Could not create tasks directory. Please check permissions.\n";
        exit(1);
    }
}

// 1. Get the task name (mandatory).
$name = '';
while (empty(trim($name))) {
    $name = prompt_user("Enter the task name: ");
    if (empty(trim($name))) {
        echo "Task name cannot be empty.\n";
    }
}

// 2. Get the task type using a numbered menu.
$type_choice = '';
$validTypes = ['1' => 'normal', '2' => 'due', '3' => 'recurring'];
while (!array_key_exists($type_choice, $validTypes)) {
    echo "Select task type:\n";
    echo "  [1] Normal (a simple, one-off task)\n";
    echo "  [2] Due (a task with a specific due date)\n";
    echo "  [3] Recurring (a task that repeats)\n";
    $type_choice = prompt_user("Enter your choice: ");

    if (!array_key_exists($type_choice, $validTypes)) {
        echo "Invalid choice. Please try again.\n";
    }
}
$type = $validTypes[$type_choice];

// Initialize the XML structure.
$xml = new SimpleXMLElement('<task></task>');
$xml->addChild('name', htmlspecialchars($name));

// 3. Branch logic based on task type.
switch ($type) {
    case 'due':
        collect_due_task_details($xml);
        break;
    case 'recurring':
        collect_recurring_task_details($xml);
        break;
}

// 4. Ask for an optional preview duration.
if ($type !== 'normal') {
    $preview = prompt_user("Preview days in advance? (optional, press Enter to skip): ");
    if (ctype_digit($preview)) {
        $xml->addChild('preview', $preview);
    }
}

// 5. Generate a unique filename.
$base_filename = strtolower(trim($name));
$base_filename = preg_replace('/[^a-z0-9\s-]/', '', $base_filename);
$base_filename = preg_replace('/[\s-]+/', '_', $base_filename);

$filepath = TASKS_DIR . '/' . $base_filename . '.xml';
$counter = 1;
while (file_exists($filepath)) {
    $filepath = TASKS_DIR . '/' . $base_filename . '_' . $counter . '.xml';
    $counter++;
}

// 6. Save the file using the shared function.
if (save_xml_file($filepath, $xml)) {
    echo "\nSuccess! Task file created at: $filepath\n";
} else {
    echo "\nError! Could not save the task file.\n";
}

