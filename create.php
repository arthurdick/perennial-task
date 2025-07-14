<?php

require_once 'common.php';

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

// 2. Get the task type using a letter-based menu.
$type_choice = '';
$validTypes = ['n' => 'normal', 'd' => 'due', 'r' => 'recurring'];
while (!array_key_exists($type_choice, $validTypes)) {
    echo "Select task type:\n";
    echo "  (n) Normal (a simple, one-off task)\n";
    echo "  (d) Due (a task with a specific due date)\n";
    echo "  (r) Recurring (a task that repeats)\n";
    $type_choice = strtolower(prompt_user("Enter your choice: "));

    if (!array_key_exists($type_choice, $validTypes)) {
        echo "Invalid choice. Please try again.\n";
    }
}
$type = $validTypes[$type_choice];

// Initialize the XML structure.
$xml = new SimpleXMLElement('<task></task>');
$xml->addChild('name', htmlspecialchars($name));

// 3. Branch logic based on task type, calling functions from common.php
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

