<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Services;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogBufferService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\Logger\ValueObjects\LoggerConfig;

/**
 * Test suite for LogBufferService.
 *
 * Validates buffering behavior, auto-flush on capacity, manual flush,
 * record grouping by file, and the on-flush callback mechanism.
 *
 * @author Andy Defer
 */
final class LogBufferServiceTest extends UnitTestCase
{
    private LogBufferService $buffer;
    private WriteLogTask $writeTask;
    private string $testLogPath;
    private string $currentDate;
    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create isolated test environment
        $this->currentDate = date('Y-m-d');
        $this->testLogPath = sys_get_temp_dir() . '/buffer_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;
        $this->writeTask = new WriteLogTask($pathService, $this->serializer);

        // Arrange: Create buffer with capacity of 3 records
        $this->buffer = new LogBufferService($this->writeTask, 3);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (is_dir($this->testLogPath)) {
            $this->deleteDirectory($this->testLogPath);
        }
        parent::tearDown();
    }

    /**
     * Create a test log record with the given parameters.
     */
    private function createLogRecord(string $time, LogLevel $level, string $type, array $payloadData): LogRecord
    {
        $payload = new StrictDataObject($payloadData);
        $logData = new LogDataRecord(type: $type, payload: $payload);
        $isoTime = new IsoZuluTime($time);

        return new LogRecord(
            time: $isoTime,
            level: $level,
            data: $logData,
        );
    }

    // ==================== BUFFER BEHAVIOR TESTS ====================

    public function test_push_adds_record_to_buffer(): void
    {
        // Arrange
        $record = $this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 1],
        );

        // Act
        $this->buffer->push($record);

        // Assert
        $this->assertSame(1, $this->buffer->size());
        $this->assertTrue($this->buffer->isDirty());
    }

    public function test_buffer_auto_flushes_when_size_reached(): void
    {
        // Arrange
        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', ['value' => 1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', ['value' => 2]);
        $record3 = $this->createLogRecord($this->currentDate . 'T10:28:00Z', LogLevel::INFO, 'test', ['value' => 3]);

        // Act
        $this->buffer->push($record1);
        $this->buffer->push($record2);
        $this->buffer->push($record3);

        // Assert: Buffer should be empty after auto-flush
        $this->assertSame(0, $this->buffer->size());
        $this->assertFalse($this->buffer->isDirty());

        // Assert: File should exist
        $filePath = $this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);
    }

    public function test_flush_manually_writes_all_records(): void
    {
        // Arrange
        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', ['value' => 1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', ['value' => 2]);

        $this->buffer->push($record1);
        $this->buffer->push($record2);

        // Act
        $this->buffer->flush();

        // Assert
        $this->assertSame(0, $this->buffer->size());
        $filePath = $this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);
    }

    public function test_flush_on_empty_buffer_does_nothing(): void
    {
        // Act
        $this->buffer->flush();

        // Assert
        $this->assertSame(0, $this->buffer->size());
    }

    // ==================== CALLBACK TESTS ====================

    public function test_on_flush_callback_is_called(): void
    {
        // Arrange
        $callbackCalled = false;
        $callbackCount = 0;

        $this->buffer->onFlush(function ($count) use (&$callbackCalled, &$callbackCount) {
            $callbackCalled = true;
            $callbackCount = $count;
        });

        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', ['value' => 1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', ['value' => 2]);
        $record3 = $this->createLogRecord($this->currentDate . 'T10:28:00Z', LogLevel::INFO, 'test', ['value' => 3]);

        // Act
        $this->buffer->push($record1);
        $this->buffer->push($record2);
        $this->buffer->push($record3);

        // Assert
        $this->assertTrue($callbackCalled);
        $this->assertSame(3, $callbackCount);
    }

    // ==================== CAPACITY TESTS ====================

    public function test_set_buffer_size_changes_buffer_capacity(): void
    {
        // Act
        $this->buffer->setBufferSize(5);

        // Assert
        $this->assertSame(5, $this->buffer->getBufferSize());
    }

    // ==================== DESTRUCTOR TESTS ====================

    public function test_destructor_flushes_buffer(): void
    {
        // Arrange
        $record = $this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['value' => 1],
        );

        $this->buffer->push($record);

        // Act
        unset($this->buffer);

        // Assert: File should exist after destructor runs
        $filePath = $this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);
    }

    // ==================== RECORD GROUPING TESTS ====================

    public function test_buffer_groups_records_by_file(): void
    {
        // Arrange
        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', ['value' => 1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T11:26:00Z', LogLevel::INFO, 'test', ['value' => 2]);
        $record3 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', ['value' => 3]);

        $buffer = new LogBufferService($this->writeTask, 10);

        // Act
        $buffer->push($record1);
        $buffer->push($record2);
        $buffer->push($record3);
        $buffer->flush();

        // Assert: Different hour files should be created
        $this->assertFileExists($this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl');
        $this->assertFileExists($this->testLogPath . '/' . $this->currentDate . '/11-12.jsonl');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Recursively delete a directory and all its contents.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
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
