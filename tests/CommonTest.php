<?php

use PHPUnit\Framework\TestCase;

class CommonTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean the tasks directory before each test
        clean_temp_dir();
        if (!is_dir(TASKS_DIR)) {
            mkdir(TASKS_DIR, 0777, true);
        }
    }

    public function testValidateDate()
    {
        $this->assertTrue(validate_date('2025-07-09'));
        $this->assertFalse(validate_date('2025-13-01'));
        $this->assertFalse(validate_date('not-a-date'));
        $this->assertFalse(validate_date('2025-02-30'));
    }

    public function testPluralizeDays()
    {
        $this->assertEquals('day', pluralize_days(1));
        $this->assertEquals('day', pluralize_days(-1));
        $this->assertEquals('days', pluralize_days(2));
        $this->assertEquals('days', pluralize_days(0));
        $this->assertEquals('days', pluralize_days(-5));
    }

    public function testGetTaskType()
    {
        // Normal Task
        $xml_normal = new SimpleXMLElement('<task><name>Normal Task</name></task>');
        $this->assertEquals('normal', get_task_type($xml_normal));

        // Due Task
        $xml_due = new SimpleXMLElement('<task><name>Due Task</name><due>2025-12-31</due></task>');
        $this->assertEquals('due', get_task_type($xml_due));

        // Recurring Task
        $xml_recurring = new SimpleXMLElement('<task><name>Recurring Task</name><recurring><completed>2025-07-01</completed><duration>7</duration></recurring></task>');
        $this->assertEquals('recurring', get_task_type($xml_recurring));
    }

    public function testValidateTaskFile()
    {
        // Create a valid task file
        $valid_xml_content = '<?xml version="1.0" encoding="UTF-8"?><task><name>Valid Task</name></task>';
        $valid_filepath = TASKS_DIR . '/valid_task.xml';
        file_put_contents($valid_filepath, $valid_xml_content);

        $this->assertTrue(validate_task_file($valid_filepath));

        // Create an invalid (malformed) task file
        $invalid_xml_content = '<task><name>Invalid Task</nme></task>';
        $invalid_filepath = TASKS_DIR . '/invalid_task.xml';
        file_put_contents($invalid_filepath, $invalid_xml_content);

        $this->assertFalse(validate_task_file($invalid_filepath));

        // Create a file that doesn't conform to the schema
        $non_conforming_xml = '<?xml version="1.0" encoding="UTF-8"?><badtask></badtask>';
        $non_conforming_filepath = TASKS_DIR . '/non_conforming.xml';
        file_put_contents($non_conforming_filepath, $non_conforming_xml);

        $this->assertFalse(validate_task_file($non_conforming_filepath));

        // Test with a non-existent file
        $this->assertFalse(validate_task_file(TASKS_DIR . '/non_existent.xml'));
    }
    
    public function testSaveXmlFile()
    {
        $xml = new SimpleXMLElement('<task><name>Test Save</name></task>');
        $filepath = TASKS_DIR . '/test_save.xml';

        $this->assertTrue(save_xml_file($filepath, $xml));
        $this->assertFileExists($filepath);

        // Verify the content and schema location attribute
        $saved_content = file_get_contents($filepath);
        $this->assertStringContainsString('xsi:noNamespaceSchemaLocation="' . XSD_PATH . '"', $saved_content);
        $this->assertStringContainsString('<name>Test Save</name>', $saved_content);
        
        // Test validation of the saved file
        $this->assertTrue(validate_task_file($filepath));
    }
}
