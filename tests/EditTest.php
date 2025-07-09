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
            '2', // Save and Exit (assuming normal task)
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
            '2', // Edit Due Date
            '2025-11-01',
            '4', // Save and Exit
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
            '2', // Edit Last Completed Date
            '2025-06-15',
            '3', // Edit Recurrence Duration
            '15',
            '5'  // Save and Exit
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
            '3', // Edit/Add Preview
            '7',
            '4'  // Save
        ];
        $this->runEditScript($filepath, $inputs_add);
        $xml_with_preview = simplexml_load_file($filepath);
        $this->assertEquals('7', (string)$xml_with_preview->preview);

        // Remove preview
        $inputs_remove = [
            '3', // Edit/Add Preview
            '0',
            '4'  // Save
        ];
        $this->runEditScript($filepath, $inputs_remove);
        $xml_without_preview = simplexml_load_file($filepath);
        $this->assertFalse(isset($xml_without_preview->preview));
    }
}
