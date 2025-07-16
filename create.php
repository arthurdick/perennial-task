<?php

require_once 'common.php';

// --- Main Script Execution ---

echo "--- Create a New Task ---\n";

if (!is_dir(TASKS_DIR)) {
    if (!mkdir(TASKS_DIR, 0755, true)) {
        echo "Error: Could not create tasks directory.\n";
        exit(1);
    }
}

$name = '';
while (empty(trim($name))) {
    $name = prompt_user("Enter the task name: ");
    if (empty(trim($name))) {
        echo "Task name cannot be empty.\n";
    }
}

$type_options = [
    'n' => 'Normal (a simple, one-off task)',
    's' => 'Scheduled (a task with a due date that may repeat)'
];
$type_choice = get_menu_choice("Select task type:", $type_options);

$xml = new SimpleXMLElement('<task></task>');
$xml->addChild('name', htmlspecialchars($name));

if ($type_choice === 's') {
    collect_scheduled_task_details($xml);
    $preview = get_optional_positive_integer_input("Preview days in advance? (optional, press Enter to skip): ");
    if ($preview !== null && $preview > 0) {
        $xml->addChild('preview', $preview);
    }
}

$base_filename = sanitize_filename($name);
$filepath = TASKS_DIR . '/' . $base_filename . '.xml';
$counter = 1;
while (file_exists($filepath)) {
    $filepath = TASKS_DIR . '/' . $base_filename . '_' . $counter . '.xml';
    $counter++;
}

if (save_xml_file($filepath, $xml)) {
    echo "\nSuccess! Task file created at: $filepath\n";
} else {
    echo "\nError! Could not save the task file.\n";
}
