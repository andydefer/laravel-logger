<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Tasks;

use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\WriteLogTask;
use ErrorException;
use RuntimeException;

final class WriteLogTaskTest extends TestCase
{
    private WriteLogTask $task;

    private string $testLogPath;

    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testLogPath = sys_get_temp_dir() . '/test_logs_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;
        $this->task = new WriteLogTask($pathService, $this->serializer);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testLogPath)) {
            $this->deleteDirectory($this->testLogPath);
        }
        parent::tearDown();
    }

    private function createLogRecord(string $time, LogLevel $level, string $type, array $payloadData): LogRecord
    {
        $payload = new MixedPayloadCollection;
        foreach ($payloadData as $item) {
            $payload->add($item);
        }

        $logData = new LogDataRecord(type: $type, payload: $payload);

        return new LogRecord(
            time: $time,
            level: $level,
            data: $logData,
        );
    }

    public function test_execute_creates_directory_and_writes_log_entry(): void
    {
        $currentDate = date('Y-m-d');

        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: [1, '127.0.0.1'],
        );

        $this->task->execute($record);

        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('"level":"info"', $content);
        $this->assertStringContainsString('"type":"user_login"', $content);
    }

    public function test_execute_appends_multiple_entries_to_same_hour_file(): void
    {
        $currentDate = date('Y-m-d');

        $record1 = $this->createLogRecord(
            time: $currentDate . 'T10:15:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [1],
        );

        $record2 = $this->createLogRecord(
            time: $currentDate . 'T10:45:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [2],
        );

        $this->task->execute($record1);
        $this->task->execute($record2);

        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $content = file_get_contents($filePath);

        $lines = explode("\n", trim($content));
        $this->assertCount(2, $lines);
    }

    public function test_execute_creates_different_files_for_different_hours(): void
    {
        $currentDate = date('Y-m-d');

        $record1 = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [1],
        );

        $record2 = $this->createLogRecord(
            time: $currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [2],
        );

        $this->task->execute($record1);
        $this->task->execute($record2);

        $this->assertFileExists($this->testLogPath . '/' . $currentDate . '/10-11.jsonl');
        $this->assertFileExists($this->testLogPath . '/' . $currentDate . '/11-12.jsonl');
    }

    public function test_execute_handles_complex_data_structure(): void
    {
        $currentDate = date('Y-m-d');

        $payload = new MixedPayloadCollection;
        $payload->add('order_created');
        $payload->add(12345);
        $payload->add(79.98);
        $payload->add(true);

        $logData = new LogDataRecord(type: 'order_created', payload: $payload);

        $record = new LogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->task->execute($record);

        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);

        $this->assertSame('order_created', $decoded['data']['type']);
        $this->assertContains(12345, $decoded['data']['payload']);
        $this->assertContains(79.98, $decoded['data']['payload']);
    }

    public function test_execute_throws_exception_when_write_fails(): void
    {
        $invalidPath = '/root/invalid/path/' . uniqid() . '/logs';
        $config = new LoggerConfig($invalidPath, 30);
        $pathService = new LogPathService($config);
        $task = new WriteLogTask($pathService, $this->serializer);

        $currentDate = date('Y-m-d');
        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['data'],
        );

        $thrown = false;
        try {
            $task->execute($record);
        } catch (RuntimeException | ErrorException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Expected RuntimeException or ErrorException was not thrown');
    }

    public function test_execute_throws_exception_when_file_not_writable(): void
    {
        // Créer un dossier avec permission en lecture seule
        $readOnlyPath = sys_get_temp_dir() . '/readonly_' . uniqid();
        mkdir($readOnlyPath, 0555); // Lecture seule (sans écriture)

        $config = new LoggerConfig($readOnlyPath, 30);
        $pathService = new LogPathService($config);
        $task = new WriteLogTask($pathService, $this->serializer);

        $currentDate = date('Y-m-d');
        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['data'],
        );

        $thrown = false;
        try {
            $task->execute($record);
        } catch (RuntimeException | ErrorException $e) {
            $thrown = true;
        } finally {
            // Restaurer la permission pour nettoyer
            chmod($readOnlyPath, 0755);
            rmdir($readOnlyPath);
        }

        $this->assertTrue($thrown, 'Expected RuntimeException or ErrorException was not thrown');
    }

    public function test_execute_serializes_log_correctly(): void
    {
        $currentDate = date('Y-m-d');

        $record = $this->createLogRecord(
            time: $currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test_event',
            payloadData: [42, 'string_value', true],
        );

        $this->task->execute($record);

        $filePath = $this->testLogPath . '/' . $currentDate . '/10-11.jsonl';
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);

        $this->assertSame('test_event', $decoded['data']['type']);
        $this->assertContains(42, $decoded['data']['payload']);
        $this->assertContains('string_value', $decoded['data']['payload']);
        $this->assertContains(true, $decoded['data']['payload']);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
