<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CreateTest extends TestCase
{
    private $script_path = __DIR__ . '/../create.php';

    protected function setUp(): void
    {
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    private function runCreateScript(array $inputs): array
    {
        global $argv;
        // Mock the argv variable for the script being included.
        $argv = ['create.php', 'create'];

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

        return ['output' => $output, 'files' => glob(TASKS_DIR . '/*.xml')];
    }

    /**
     * Updated helper to run the create script non-interactively and capture stderr.
     *
     * @param array $options An associative array of command-line options.
     * @return array An array containing stdout, stderr, and a list of created files.
     */
    private function runCreateScript_nonInteractive(array $options): array
    {
        $prn_path = realpath(__DIR__ . '/../prn');
        $script_args = ['create'];
        foreach ($options as $key => $value) {
            $script_args[] = $key;
            if ($value !== null) {
                $script_args[] = escapeshellarg($value);
            }
        }

        $bootstrap_path = realpath(__DIR__ . '/bootstrap.php');
        $command = "php -d auto_prepend_file=$bootstrap_path " . escapeshellarg($prn_path) . " " . implode(' ', $script_args) . " 2>&1";

        $output = shell_exec($command);

        return ['output' => $output, 'files' => glob(TASKS_DIR . '/*.xml')];
    }


    public function testCreateNormalTask()
    {
        $inputs = ['Test Normal Task', 'n', ''];
        $result = $this->runCreateScript($inputs);
        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $xml = simplexml_load_file($result['files'][0]);
        $this->assertEquals('Test Normal Task', (string)$xml->name);
        $this->assertFalse(isset($xml->due));
        $this->assertFalse(isset($xml->priority)); // Should default to 0, not be set
    }

    public function testCreateScheduledTask()
    {
        $inputs = [
            'Test Scheduled Task', // name
            's',                   // type (scheduled)
            '2025-08-01',          // due date
            'y',                   // yes, it reschedules
            '1 month',             // interval
            'd',                   // from due_date
            '5',                   // preview days
            ''                     // priority (default)
        ];

        $result = $this->runCreateScript($inputs);
        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $filepath = $result['files'][0];
        $xml = simplexml_load_file($filepath);

        $this->assertEquals('Test Scheduled Task', (string)$xml->name);
        $this->assertEquals('2025-08-01', (string)$xml->due);
        $this->assertEquals('1 month', (string)$xml->reschedule->interval);
        $this->assertEquals('due_date', (string)$xml->reschedule->from);
        $this->assertEquals('5', (string)$xml->preview);
        $this->assertFalse(isset($xml->priority));
    }

    public function testCreateTaskWithCustomPriority()
    {
        $inputs = ['High Prio Task', 'n', '10'];
        $result = $this->runCreateScript($inputs);
        $this->assertCount(1, $result['files']);
        $xml = simplexml_load_file($result['files'][0]);
        $this->assertEquals(10, (int)$xml->priority);
    }

    public function testCreateTaskWithNegativePriority()
    {
        $inputs = ['Low Prio Task', 'n', '-5'];
        $result = $this->runCreateScript($inputs);
        $this->assertCount(1, $result['files']);
        $xml = simplexml_load_file($result['files'][0]);
        $this->assertEquals(-5, (int)$xml->priority);
    }


    public function testFilenameSanitizationAndUniqueness()
    {
        // First task
        $this->runCreateScript(['Test @Task!', 'n', '']);
        $this->assertFileExists(TASKS_DIR . '/test_task.xml');

        // Second task with the same name
        $this->runCreateScript(['Test @Task!', 'n', '']);
        $this->assertFileExists(TASKS_DIR . '/test_task_1.xml');

        // Third task
        $this->runCreateScript(['Test @Task!', 'n', '']);
        $this->assertFileExists(TASKS_DIR . '/test_task_2.xml');
    }

    public function testCreateNormalTask_NonInteractive()
    {
        $options = ['--name' => 'Non-interactive Normal'];
        $result = $this->runCreateScript_nonInteractive($options);

        $this->assertStringContainsString('Creating New Task (Non-Interactive)', $result['output']);
        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $this->assertCount(1, $result['files']);

        $xml = simplexml_load_file($result['files'][0]);
        $this->assertEquals('Non-interactive Normal', (string)$xml->name);
        $this->assertFalse(isset($xml->due));
    }

    public function testCreateScheduledTask_NonInteractive()
    {
        $options = [
            '--name' => 'Non-interactive Scheduled',
            '--due' => '2025-12-01',
            '--preview' => '10',
            '--reschedule-interval' => '2 weeks',
            '--reschedule-from' => 'completion_date'
        ];
        $result = $this->runCreateScript_nonInteractive($options);

        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $this->assertCount(1, $result['files']);

        $xml = simplexml_load_file($result['files'][0]);
        $this->assertEquals('Non-interactive Scheduled', (string)$xml->name);
        $this->assertEquals('2025-12-01', (string)$xml->due);
        $this->assertEquals('10', (string)$xml->preview);
        $this->assertEquals('2 weeks', (string)$xml->reschedule->interval);
        $this->assertEquals('completion_date', (string)$xml->reschedule->from);
    }

    public function testCreateTaskWithPriority_NonInteractive()
    {
        $options = [
            '--name' => 'CLI Prio Task',
            '--priority' => '7'
        ];
        $result = $this->runCreateScript_nonInteractive($options);
        $this->assertCount(1, $result['files']);
        $xml = simplexml_load_file($result['files'][0]);
        $this->assertEquals(7, (int)$xml->priority);
    }

    /**
     * @test
     * Verifies that the script exits with an error if the --name flag is empty.
     */
    public function testCreateFailsWithEmptyName_NonInteractive()
    {
        $options = ['--name' => ''];
        $result = $this->runCreateScript_nonInteractive($options);

        $this->assertStringContainsString(
            'Error: --name is required for non-interactive creation and cannot be empty.',
            $result['output']
        );
        $this->assertCount(0, $result['files']); // No file should be created.
    }

    /**
     * @test
     * Verifies that the script exits with an error if an invalid date is provided.
     */
    public function testCreateFailsWithInvalidDate_NonInteractive()
    {
        $options = ['--name' => 'Invalid Date Task', '--due' => 'not-a-real-date'];
        $result = $this->runCreateScript_nonInteractive($options);

        $this->assertStringContainsString('Error: Invalid format for --due. Use YYYY-MM-DD.', $result['output']);
        $this->assertCount(0, $result['files']);
    }

    /**
     * @test
     */
    public function testCreateFailsWithInvalidPriority_NonInteractive()
    {
        $options = ['--name' => 'Invalid Prio', '--priority' => 'five'];
        $result = $this->runCreateScript_nonInteractive($options);
        $this->assertStringContainsString('Error: --priority must be an integer.', $result['output']);
        $this->assertCount(0, $result['files']);
    }


    /**
     * @test
     * Verifies that using scheduling options without a due date is an error.
     */
    public function testCreateFailsWithRescheduleButNoDueDate_NonInteractive()
    {
        $options = ['--name' => 'Bad Schedule', '--reschedule-interval' => '1 week'];
        $result = $this->runCreateScript_nonInteractive($options);

        $this->assertStringContainsString('Error: --due is required when using any other scheduling options', $result['output']);
        $this->assertCount(0, $result['files']);
    }
}
