<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CompleteTest extends TestCase
{
    private $script_path = __DIR__ . '/../complete.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    /**
     * Updated helper to run the complete script and capture stderr.
     *
     * @param string $task_filepath The path to the task file.
     * @param array $options An associative array of options, e.g., ['--date' => 'YYYY-MM-DD'].
     * @return string The script's combined stdout and stderr.
     */
    private function runCompleteScript(string $task_filepath, array $options = []): string
    {
        $prn_path = realpath(__DIR__ . '/../prn');
        $script_args = ['complete'];

        foreach ($options as $key => $value) {
            $script_args[] = $key;
            if ($value !== null) {
                $script_args[] = escapeshellarg($value);
            }
        }
        $script_args[] = escapeshellarg($task_filepath);

        $bootstrap_path = realpath(__DIR__ . '/bootstrap.php');
        $command = "php -d auto_prepend_file=$bootstrap_path " . escapeshellarg($prn_path) . " " . implode(' ', $script_args) . " 2>&1";

        return shell_exec($command);
    }

    public function testCompleteNormalTask()
    {
        $xml = new SimpleXMLElement('<task><name>Normal Task To Complete</name></task>');
        $filepath = TASKS_DIR . '/normal_complete.xml';
        save_xml_file($filepath, $xml);

        $this->assertFileExists($filepath);

        $output = $this->runCompleteScript($filepath, ['--date' => '2025-07-15']);

        $this->assertStringContainsString("Task 'Normal Task To Complete' has been marked as complete on 2025-07-15.", $output);
        $this->assertFileExists($filepath);

        $updated_xml = simplexml_load_file($filepath);
        $this->assertTrue(isset($updated_xml->history));
        $this->assertEquals('2025-07-15', (string)$updated_xml->history->entry);
    }

    public function testCompleteOneOffScheduledTaskCleansUpTags()
    {
        $xml = new SimpleXMLElement('<task>
            <name>One-off Project</name>
            <due>2025-08-15</due>
            <preview>10</preview>
        </task>');
        $filepath = TASKS_DIR . '/one_off.xml';
        save_xml_file($filepath, $xml);

        $this->runCompleteScript($filepath, ['--date' => '2025-08-10']);

        $updated_xml = simplexml_load_file($filepath);

        $this->assertFalse(isset($updated_xml->due));
        $this->assertFalse(isset($updated_xml->preview));
        $this->assertTrue(isset($updated_xml->history));
    }

    public function testCompleteScheduledTask_RescheduleFromDueDate()
    {
        $xml = new SimpleXMLElement('<task>
            <name>Pay Rent</name>
            <due>2025-08-01</due>
            <reschedule><interval>1 month</interval><from>due_date</from></reschedule>
        </task>');
        $filepath = TASKS_DIR . '/rent.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['--date' => '2025-08-01']);
        $this->assertStringContainsString("Task has been rescheduled to 2025-09-01", $output);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2025-09-01', (string)$updated_xml->due);
        $this->assertEquals('2025-08-01', (string)$updated_xml->history->entry);
    }

    public function testCompleteScheduledTask_RescheduleFromCompletionDate()
    {
        $xml = new SimpleXMLElement('<task>
            <name>Water Plants</name>
            <due>2025-07-18</due>
            <reschedule><interval>3 days</interval><from>completion_date</from></reschedule>
        </task>');
        $filepath = TASKS_DIR . '/plants.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['--date' => '2025-07-20']);
        $this->assertStringContainsString("Task has been rescheduled to 2025-07-23", $output);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertEquals('2025-07-23', (string)$updated_xml->due);
        $this->assertEquals('2025-07-20', (string)$updated_xml->history->entry);
    }

    public function testMigrationOfLegacyRecurringTask()
    {
        $xml = new SimpleXMLElement('<task>
            <name>Old Recurring Task</name>
            <recurring><completed>2025-07-01</completed><duration>7</duration></recurring>
        </task>');
        $filepath = TASKS_DIR . '/legacy_recurring.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['--date' => '2025-07-08']);
        $this->assertStringContainsString("Migrated task from old 'recurring' format", $output);
        $this->assertStringContainsString("Task has been rescheduled to 2025-07-15", $output);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertFalse(isset($updated_xml->recurring));
        $this->assertTrue(isset($updated_xml->reschedule));
        $this->assertEquals('7 days', (string)$updated_xml->reschedule->interval);
        $this->assertEquals('completion_date', (string)$updated_xml->reschedule->from);
        $this->assertEquals('2025-07-15', (string)$updated_xml->due);
    }

    /**
     * @test
     * Verifies that the script exits with an error if an invalid date is provided.
     */
    public function testCompleteFailsWithInvalidDate()
    {
        $xml = new SimpleXMLElement('<task><name>Fail Date Completion</name></task>');
        $filepath = TASKS_DIR . '/fail_complete.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runCompleteScript($filepath, ['--date' => 'this-is-not-a-date']);

        $this->assertStringContainsString('Error: Invalid format for --date. Use YYYY-MM-DD.', $output);

        // Verify the original file was not modified
        $original_xml = simplexml_load_file($filepath);
        $this->assertFalse(isset($original_xml->history));
    }
}
