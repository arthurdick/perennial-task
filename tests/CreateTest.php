<?php

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
        include $this->script_path;
        $output = ob_get_clean();

        unset($GLOBALS['__MOCK_PROMPT_USER_FUNC']);
        fclose($input_stream);

        return ['output' => $output, 'files' => glob(TASKS_DIR . '/*.xml')];
    }

    public function testCreateNormalTask()
    {
        $inputs = ['Test Normal Task', 'n'];
        $result = $this->runCreateScript($inputs);
        $this->assertStringContainsString('Success! Task file created', $result['output']);
        $xml = simplexml_load_file($result['files'][0]);
        $this->assertEquals('Test Normal Task', (string)$xml->name);
        $this->assertFalse(isset($xml->due));
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
            '5'                    // preview days
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
}
