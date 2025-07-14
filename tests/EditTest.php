<?php

use PHPUnit\Framework\TestCase;

class EditTest extends TestCase
{
    private $script_path = __DIR__ . '/../edit.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    private function runEditScript(string $task_filepath, array $inputs): string
    {
        global $argv;
        $argv = ['edit.php', $task_filepath];
        
        $input_stream = fopen('php://memory', 'r+');
        foreach ($inputs as $input) {
            fwrite($input_stream, $input . PHP_EOL);
        }
        rewind($input_stream);

        $GLOBALS['__MOCK_PROMPT_USER_FUNC'] = function(string $prompt) use ($input_stream): string {
            $line = fgets($input_stream);
            return $line !== false ? trim($line) : '';
        };

        ob_start();
        // Use `include` so the script is re-evaluated for each test run.
        include $this->script_path;
        $output = ob_get_clean();
        
        unset($GLOBALS['__MOCK_PROMPT_USER_FUNC']);
        fclose($input_stream);

        return $output;
    }

    public function testEditTaskName()
    {
        $xml = new SimpleXMLElement('<task><name>Original Name</name></task>');
        $filepath = TASKS_DIR . '/edit_name.xml';
        save_xml_file($filepath, $xml);

        $inputs = [
            '1', // Edit Name
            'New Awesome Name',
            '3', // Save and Exit (normal task menu)
        ];

        $output = $this->runEditScript($filepath, $inputs);

        $this->assertStringContainsString('Success! Task file updated', $output);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('New Awesome Name', (string)$updated_xml->name);
    }

    public function testEditDueDate()
    {
        $xml = new SimpleXMLElement('<task><name>Due Task</name><due>2025-10-31</due></task>');
        $filepath = TASKS_DIR . '/edit_due.xml';
        save_xml_file($filepath, $xml);

        $inputs = [
            '3', // Edit Due Date
            '2025-11-01',
            '5', // Save and Exit
        ];

        $this->runEditScript($filepath, $inputs);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2025-11-01', (string)$updated_xml->due);
    }
    
    public function testEditRecurringDetails()
    {
        $xml = new SimpleXMLElement('<task><name>Recurring</name><recurring><completed>2025-06-01</completed><duration>10</duration></recurring></task>');
        $filepath = TASKS_DIR . '/edit_recurring.xml';
        save_xml_file($filepath, $xml);

        $inputs = [
            '3', // Edit Last Completed Date
            '2025-06-15',
            '4', // Edit Recurrence Duration
            '15',
            '6'  // Save and Exit
        ];

        $this->runEditScript($filepath, $inputs);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2025-06-15', (string)$updated_xml->recurring->completed);
        $this->assertEquals('15', (string)$updated_xml->recurring->duration);
    }
    
    public function testAddAndRemovePreview()
    {
        $xml = new SimpleXMLElement('<task><name>Preview Task</name><due>2025-12-01</due></task>');
        $filepath = TASKS_DIR . '/edit_preview.xml';
        save_xml_file($filepath, $xml);
        
        // Add preview
        $inputs_add = [
            '4', // Edit/Add Preview
            '7',
            '5'  // Save
        ];
        $this->runEditScript($filepath, $inputs_add);
        $xml_with_preview = simplexml_load_file($filepath);
        $this->assertEquals('7', (string)$xml_with_preview->preview);

        // Remove preview
        $inputs_remove = [
            '4', // Edit/Add Preview
            '0',
            '5'  // Save
        ];
        $this->runEditScript($filepath, $inputs_remove);
        $xml_without_preview = simplexml_load_file($filepath);
        $this->assertFalse(isset($xml_without_preview->preview));
    }
    
    public function testChangeTaskType()
    {
        $xml = new SimpleXMLElement('<task><name>Convert Me</name><due>2025-01-01</due></task>');
        $filepath = TASKS_DIR . '/convert_type.xml';
        save_xml_file($filepath, $xml);

        $inputs = [
            '2', // Change Task Type (from 'due' menu)
            '3', // Select 'recurring'
            '2025-07-10', // Enter last completed date
            '7',          // Recur every 7 days
            '6'           // Save and Exit (from the new 'recurring' menu)
        ];
        
        $this->runEditScript($filepath, $inputs);
        
        $updated_xml = simplexml_load_file($filepath);
        
        // Assert the old structure is gone
        $this->assertFalse(isset($updated_xml->due));
        
        // Assert the new structure is present and correct
        $this->assertTrue(isset($updated_xml->recurring));
        $this->assertEquals('2025-07-10', (string)$updated_xml->recurring->completed);
        $this->assertEquals('7', (string)$updated_xml->recurring->duration);
    }
}

