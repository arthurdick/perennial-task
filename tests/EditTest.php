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

        $GLOBALS['__MOCK_PROMPT_USER_FUNC'] = function (string $prompt) use ($input_stream): string {
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

    public function testEditTaskNameAndRenameFile()
    {
        $original_filepath = TASKS_DIR . '/original_name.xml';
        $new_filepath = TASKS_DIR . '/new_awesome_name.xml';

        save_xml_file($original_filepath, new SimpleXMLElement('<task><name>Original Name</name></task>'));

        $inputs = [
            'n',                // Edit Name
            'New Awesome Name',
            'y',                // Yes, rename the file
            's',                // Save and Exit
        ];

        $output = $this->runEditScript($original_filepath, $inputs);

        $this->assertStringContainsString('File successfully renamed', $output);
        $this->assertFileDoesNotExist($original_filepath);
        $this->assertFileExists($new_filepath);

        $updated_xml = simplexml_load_file($new_filepath);
        $this->assertEquals('New Awesome Name', (string)$updated_xml->name);
    }

    public function testEditTaskNameWithoutRenamingFile()
    {
        $original_filepath = TASKS_DIR . '/original_name.xml';
        save_xml_file($original_filepath, new SimpleXMLElement('<task><name>Original Name</name></task>'));

        $inputs = [
            'n',                // Edit Name
            'A Different Name',
            'n',                // No, do not rename
            's',                // Save and Exit
        ];

        $this->runEditScript($original_filepath, $inputs);

        $this->assertFileExists($original_filepath);
        $updated_xml = simplexml_load_file($original_filepath);
        $this->assertEquals('A Different Name', (string)$updated_xml->name);
    }

    public function testRenameFileCollisionGeneratesUniqueName()
    {
        $original_filepath = TASKS_DIR . '/original.xml';
        $colliding_filepath = TASKS_DIR . '/new_name.xml';
        $expected_new_filepath = TASKS_DIR . '/new_name_1.xml';

        // Create the file we are going to edit
        save_xml_file($original_filepath, new SimpleXMLElement('<task><name>Original</name></task>'));
        // Create the file that will cause the name collision
        save_xml_file($colliding_filepath, new SimpleXMLElement('<task><name>This one already exists</name></task>'));

        $inputs = [
            'n',          // Edit Name
            'New Name',   // This name will collide with 'new_name.xml'
            'y',          // Yes, try to rename
            's',          // Save and Exit
        ];

        $output = $this->runEditScript($original_filepath, $inputs);

        $this->assertStringContainsString("File successfully renamed to 'new_name_1.xml'", $output);

        // The original file should be gone
        $this->assertFileDoesNotExist($original_filepath);
        // The colliding file should be untouched
        $this->assertFileExists($colliding_filepath);
        // A new file with a numbered suffix should have been created
        $this->assertFileExists($expected_new_filepath);

        // The new file should have the updated name
        $xml = simplexml_load_file($expected_new_filepath);
        $this->assertEquals('New Name', (string)$xml->name);
    }

    public function testConvertNormalToScheduled()
    {
        $filepath = TASKS_DIR . '/convert_me.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Convert Me</name></task>'));

        $inputs = [
            't',          // Change Task Type
            '2025-10-31', // Enter due date
            'y',          // Yes, reschedule
            '1 year',     // Interval
            'd',          // From due_date
            's'           // Save and Exit
        ];

        $this->runEditScript($filepath, $inputs);
        $updated_xml = simplexml_load_file($filepath);

        $this->assertEquals('scheduled', get_task_type($updated_xml));
        $this->assertEquals('2025-10-31', (string)$updated_xml->due);
        $this->assertTrue(isset($updated_xml->reschedule));
        $this->assertEquals('1 year', (string)$updated_xml->reschedule->interval);
        $this->assertEquals('due_date', (string)$updated_xml->reschedule->from);
    }

    public function testEditRescheduleSettings()
    {
        $xml = new SimpleXMLElement('<task>
            <name>Task To Edit</name>
            <due>2025-01-01</due>
            <reschedule><interval>7 days</interval><from>completion_date</from></reschedule>
        </task>');
        $filepath = TASKS_DIR . '/edit_reschedule.xml';
        save_xml_file($filepath, $xml);

        $inputs = [
            'r',          // Edit Reschedule Settings
            'n',          // No, don't remove existing
            'y',          // Yes, it reschedules
            '2 weeks',    // New Interval
            'd',          // New From: due_date
            's'           // Save and Exit
        ];

        $this->runEditScript($filepath, $inputs);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2 weeks', (string)$updated_xml->reschedule->interval);
        $this->assertEquals('due_date', (string)$updated_xml->reschedule->from);
    }

    public function testEditPreviewDays()
    {
        $xml = new SimpleXMLElement('<task><name>Preview Task</name><due>2025-12-25</due></task>');
        $filepath = TASKS_DIR . '/preview_task.xml';
        save_xml_file($filepath, $xml);

        // 1. Add preview
        $this->runEditScript($filepath, ['p', '5', 's']);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertTrue(isset($updated_xml->preview));
        $this->assertEquals('5', (string)$updated_xml->preview);

        // 2. Change preview
        $this->runEditScript($filepath, ['p', '10', 's']);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('10', (string)$updated_xml->preview);

        // 3. Remove preview
        $this->runEditScript($filepath, ['p', '', 's']);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertFalse(isset($updated_xml->preview));
    }

    public function testMigrationOfLegacyRecurringTaskOnEdit()
    {
        $xml = new SimpleXMLElement('<task>
            <name>Legacy Task</name>
            <recurring><completed>2025-07-01</completed><duration>14</duration></recurring>
        </task>');
        $filepath = TASKS_DIR . '/legacy_for_edit.xml';
        save_xml_file($filepath, $xml);

        // Just open and save to trigger the migration
        $inputs = ['s'];

        $output = $this->runEditScript($filepath, $inputs);

        $this->assertStringContainsString('Notice: This task used a legacy format and has been automatically updated.', $output);

        $updated_xml = simplexml_load_file($filepath);

        // Verify old tag is gone
        $this->assertFalse(isset($updated_xml->recurring));

        // Verify new tags are present and correct
        $this->assertTrue(isset($updated_xml->reschedule));
        $this->assertEquals('14 days', (string)$updated_xml->reschedule->interval);
        $this->assertEquals('completion_date', (string)$updated_xml->reschedule->from);

        // Verify due date was calculated and added
        $this->assertTrue(isset($updated_xml->due));
        $this->assertEquals('2025-07-15', (string)$updated_xml->due); // 2025-07-01 + 14 days
    }
}
