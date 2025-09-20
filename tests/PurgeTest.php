<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PurgeTest extends TestCase
{
    private $script_path = __DIR__ . '/../purge.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    private function runPurgeScript(string $task_filepath = null, array $options = [], string $input = ''): string
    {
        $prn_path = realpath(__DIR__ . '/../prn');
        $script_args = ['purge'];

        foreach ($options as $key => $value) {
            $script_args[] = $key;
            if ($value !== null) {
                $script_args[] = escapeshellarg($value);
            }
        }
        if ($task_filepath) {
            $script_args[] = escapeshellarg($task_filepath);
        }

        $bootstrap_path = realpath(__DIR__ . '/bootstrap.php');
        $command = "php -d auto_prepend_file=$bootstrap_path " . escapeshellarg($prn_path) . " " . implode(' ', $script_args) . " 2>&1";

        if (!empty($input)) {
            $command = "echo \"$input\" | " . $command;
        }

        return shell_exec($command);
    }

    public function testPurgeSingleTaskWithHistory()
    {
        $xml = new SimpleXMLElement('<task><name>Task With History</name><history><entry>2025-01-01</entry></history></task>');
        $filepath = TASKS_DIR . '/task_with_history.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runPurgeScript($filepath);

        $this->assertStringContainsString('History purged for task: Task With History', $output);
        $updated_xml = simplexml_load_file($filepath);
        $this->assertFalse(isset($updated_xml->history));
    }

    public function testPurgeSingleTaskWithoutHistory()
    {
        $xml = new SimpleXMLElement('<task><name>Task Without History</name></task>');
        $filepath = TASKS_DIR . '/task_without_history.xml';
        save_xml_file($filepath, $xml);

        $output = $this->runPurgeScript($filepath);

        $this->assertStringContainsString('No history found for task: Task Without History', $output);
    }

    public function testPurgeAllTasksWithConfirmationYes()
    {
        save_xml_file(TASKS_DIR . '/task1.xml', new SimpleXMLElement('<task><name>Task 1</name><history><entry>2025-01-01</entry></history></task>'));
        save_xml_file(TASKS_DIR . '/task2.xml', new SimpleXMLElement('<task><name>Task 2</name></task>'));
        save_xml_file(TASKS_DIR . '/task3.xml', new SimpleXMLElement('<task><name>Task 3</name><history><entry>2025-01-01</entry></history></task>'));

        $output = $this->runPurgeScript(null, [], 'y');

        $this->assertStringContainsString('History purged for 2 tasks.', $output);
        $xml1 = simplexml_load_file(TASKS_DIR . '/task1.xml');
        $this->assertFalse(isset($xml1->history));
        $xml3 = simplexml_load_file(TASKS_DIR . '/task3.xml');
        $this->assertFalse(isset($xml3->history));
    }

    public function testPurgeAllTasksWithConfirmationNo()
    {
        save_xml_file(TASKS_DIR . '/task1.xml', new SimpleXMLElement('<task><name>Task 1</name><history><entry>2025-01-01</entry></history></task>'));
        $output = $this->runPurgeScript(null, [], 'n');
        $this->assertStringContainsString('Operation cancelled.', $output);
        $xml1 = simplexml_load_file(TASKS_DIR . '/task1.xml');
        $this->assertTrue(isset($xml1->history));
    }

    public function testPurgeAllTasksWithForce()
    {
        save_xml_file(TASKS_DIR . '/task1.xml', new SimpleXMLElement('<task><name>Task 1</name><history><entry>2025-01-01</entry></history></task>'));
        save_xml_file(TASKS_DIR . '/task2.xml', new SimpleXMLElement('<task><name>Task 2</name></task>'));

        $output = $this->runPurgeScript(null, ['--force' => null]);
        $this->assertStringContainsString('History purged for 1 task.', $output);
        $xml1 = simplexml_load_file(TASKS_DIR . '/task1.xml');
        $this->assertFalse(isset($xml1->history));
    }
}
