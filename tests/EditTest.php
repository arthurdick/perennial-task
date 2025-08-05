<?php

declare(strict_types=1);

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
        $argv = ['edit.php', 'edit', $task_filepath];

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
        try {
            include $this->script_path;
        } catch (Exception $e) {
            // Catches app_exit
        }
        $output = ob_get_clean();

        unset($GLOBALS['__MOCK_PROMPT_USER_FUNC']);
        fclose($input_stream);

        return $output;
    }

    /**
     * Updated helper to run the edit script non-interactively and capture stderr.
     *
     * @param string $task_filepath The path to the task file to edit.
     * @param array $options An associative array of command-line options.
     * @return array An array containing stdout, stderr, and the final path of the file.
     */
    private function runEditScript_nonInteractive(string $task_filepath, array $options): array
    {
        $prn_path = realpath(__DIR__ . '/../prn');
        $script_args = ['edit'];
        foreach ($options as $key => $value) {
            $script_args[] = $key;
            if ($value !== null) {
                $script_args[] = escapeshellarg($value);
            }
        }
        $script_args[] = escapeshellarg($task_filepath);

        $bootstrap_path = realpath(__DIR__ . '/bootstrap.php');
        $command = "php -d auto_prepend_file=$bootstrap_path " . escapeshellarg($prn_path) . " " . implode(' ', $script_args) . " 2>&1";

        $output = shell_exec($command);

        // After running the script, the filepath might have changed.
        $new_filepath = $task_filepath;
        if (array_key_exists('--rename-file', $options) && array_key_exists('--set-name', $options)) {
            $sanitized_name = sanitize_filename($options['--set-name']);
            $files = glob(TASKS_DIR . '/' . $sanitized_name . '*.xml');
            if (!empty($files)) {
                $new_filepath = $files[0];
            } else {
                $new_filepath = TASKS_DIR . '/' . $sanitized_name . '.xml';
            }
        }

        return ['output' => $output, 'new_filepath' => $new_filepath];
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

    public function testEditPriorityInteractive()
    {
        $filepath = TASKS_DIR . '/interactive_prio.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Prio Task</name></task>'));

        // Set initial priority
        $this->runEditScript($filepath, ['i', '8', 's']);
        $xml = simplexml_load_file($filepath);
        $this->assertEquals(8, (int)$xml->priority);

        // Change priority to negative
        $this->runEditScript($filepath, ['i', '-3', 's']);
        $xml = simplexml_load_file($filepath);
        $this->assertEquals(-3, (int)$xml->priority);

        // Reset priority by entering nothing
        $this->runEditScript($filepath, ['i', '', 's']);
        $xml = simplexml_load_file($filepath);
        $this->assertFalse(isset($xml->priority), "Priority should be unset, not 0.");
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

    public function testEditSingleField_NonInteractive()
    {
        $filepath = TASKS_DIR . '/edit_single.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Edit Me</name><due>2025-01-01</due></task>'));

        $result = $this->runEditScript_nonInteractive($filepath, ['--set-due' => '2099-12-31']);

        $xml = simplexml_load_file($result['new_filepath']);
        $this->assertEquals('2099-12-31', (string)$xml->due);
    }

    public function testEditMultipleFields_NonInteractive()
    {
        $filepath = TASKS_DIR . '/edit_multiple.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Original Name</name></task>'));

        $options = [
            '--set-name' => 'New Name',
            '--set-due' => '2025-02-02',
            '--set-preview' => '7',
            '--set-reschedule-interval' => '1 month',
            '--set-reschedule-from' => 'due_date',
        ];
        $result = $this->runEditScript_nonInteractive($filepath, $options);

        $xml = simplexml_load_file($result['new_filepath']);
        $this->assertEquals('New Name', (string)$xml->name);
        $this->assertEquals('2025-02-02', (string)$xml->due);
        $this->assertEquals('7', (string)$xml->preview);
        $this->assertEquals('1 month', (string)$xml->reschedule->interval);
        $this->assertEquals('due_date', (string)$xml->reschedule->from);
    }

    public function testRemoveFields_NonInteractive()
    {
        $xml_content = '<task><name>Remove From Me</name><preview>10</preview><reschedule><interval>1 day</interval><from>due_date</from></reschedule></task>';
        $filepath = TASKS_DIR . '/remove_fields.xml';
        save_xml_file($filepath, new SimpleXMLElement($xml_content));

        $options = [
            '--remove-preview' => null,
            '--remove-reschedule' => null,
        ];
        $result = $this->runEditScript_nonInteractive($filepath, $options);

        $xml = simplexml_load_file($result['new_filepath']);
        $this->assertFalse(isset($xml->preview));
        $this->assertFalse(isset($xml->reschedule));
    }

    public function testRemoveDue_NonInteractive()
    {
        $xml_content = '<task>
            <name>Convert to Normal</name>
            <due>2025-10-10</due>
            <preview>5</preview>
            <reschedule><interval>1 month</interval><from>due_date</from></reschedule>
        </task>';
        $filepath = TASKS_DIR . '/remove_due_task.xml';
        save_xml_file($filepath, new SimpleXMLElement($xml_content));

        $options = [
            '--remove-due' => null,
        ];
        $result = $this->runEditScript_nonInteractive($filepath, $options);

        $this->assertStringContainsString('Due date and all scheduling information removed.', $result['output']);

        $xml = simplexml_load_file($result['new_filepath']);
        $this->assertFalse(isset($xml->due));
        $this->assertFalse(isset($xml->preview));
        $this->assertFalse(isset($xml->reschedule));
        $this->assertEquals('normal', get_task_type($xml));
    }

    public function testEditTaskNameAndRenameFile_NonInteractive()
    {
        $original_filepath = TASKS_DIR . '/original_interactive.xml';
        save_xml_file($original_filepath, new SimpleXMLElement('<task><name>Original Name</name></task>'));

        $options = [
            '--set-name' => 'New Non-Interactive',
            '--rename-file' => null,
        ];

        $result = $this->runEditScript_nonInteractive($original_filepath, $options);
        $new_filepath = $result['new_filepath'];


        $this->assertStringContainsString("File successfully renamed to 'new_non_interactive.xml'", $result['output']);
        $this->assertFileDoesNotExist($original_filepath);
        $this->assertFileExists($new_filepath);

        $updated_xml = simplexml_load_file($new_filepath);
        $this->assertEquals('New Non-Interactive', (string)$updated_xml->name);
    }

    public function testSetPriority_NonInteractive()
    {
        $filepath = TASKS_DIR . '/edit_prio_non_interactive.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Edit Me</name><priority>1</priority></task>'));

        $result = $this->runEditScript_nonInteractive($filepath, ['--set-priority' => '-5']);

        $xml = simplexml_load_file($result['new_filepath']);
        $this->assertEquals(-5, (int)$xml->priority);
    }

    /**
     * @test
     */
    public function testEditFailsWithInvalidDate_NonInteractive()
    {
        $filepath = TASKS_DIR . '/fail_date.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Fail Date</name></task>'));

        $result = $this->runEditScript_nonInteractive($filepath, ['--set-due' => '2025-99-99']);

        $this->assertStringContainsString('Error: Invalid format for --set-due. Use YYYY-MM-DD.', $result['output']);
    }

    /**
     * @test
     */
    public function testEditFailsWithInvalidPriority_NonInteractive()
    {
        $filepath = TASKS_DIR . '/fail_prio.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Fail Prio</name></task>'));

        $result = $this->runEditScript_nonInteractive($filepath, ['--set-priority' => 'high']);

        $this->assertStringContainsString('Error: --set-priority must be an integer.', $result['output']);
    }


    /**
     * @test
     */
    public function testEditFailsWithRenameWithoutSetName_NonInteractive()
    {
        $filepath = TASKS_DIR . '/fail_rename.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Fail Rename</name></task>'));

        $result = $this->runEditScript_nonInteractive($filepath, ['--rename-file' => null]);

        $this->assertStringContainsString('Error: --rename-file can only be used when also using --set-name.', $result['output']);
    }

    /**
     * @test
     */
    public function testEditFailsWithInvalidRescheduleFrom_NonInteractive()
    {
        $filepath = TASKS_DIR . '/fail_reschedule.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Fail Reschedule</name></task>'));

        $options = [
            '--set-reschedule-interval' => '1 week',
            '--set-reschedule-from' => 'bad_value'
        ];
        $result = $this->runEditScript_nonInteractive($filepath, $options);

        $this->assertStringContainsString("Error: --set-reschedule-from must be 'due_date' or 'completion_date'.", $result['output']);
    }

    /**
     * @test
     */
    public function testEditFailsWithIncompleteRescheduleRule_SetIntervalOnly()
    {
        $filepath = TASKS_DIR . '/fail_incomplete.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Incomplete Rule</name></task>'));
        $result = $this->runEditScript_nonInteractive($filepath, ['--set-reschedule-interval' => '1 week']);
        $this->assertStringContainsString('Error: When setting a reschedule rule, both interval and basis are required.', $result['output']);
    }
    /**
     * @test
     */
    public function testEditFailsWithIncompleteRescheduleRule_SetFromOnly()
    {
        $filepath = TASKS_DIR . '/fail_incomplete_2.xml';
        save_xml_file($filepath, new SimpleXMLElement('<task><name>Incomplete Rule 2</name></task>'));
        $result = $this->runEditScript_nonInteractive($filepath, ['--set-reschedule-from' => 'due_date']);
        $this->assertStringContainsString('Error: When setting a reschedule rule, both interval and basis are required.', $result['output']);
    }
}
