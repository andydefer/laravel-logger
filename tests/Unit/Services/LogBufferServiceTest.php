<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Services;

use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogBufferService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;

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

        $this->currentDate = date('Y-m-d');
        $this->testLogPath = sys_get_temp_dir() . '/buffer_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;
        $this->writeTask = new WriteLogTask($pathService, $this->serializer);
        $this->buffer = new LogBufferService($this->writeTask, 3);
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

    public function test_push_adds_record_to_buffer(): void
    {
        $record = $this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [1],
        );

        $this->buffer->push($record);

        $this->assertSame(1, $this->buffer->size());
        $this->assertTrue($this->buffer->isDirty());
    }

    public function test_buffer_auto_flushes_when_size_reached(): void
    {
        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', [1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', [2]);
        $record3 = $this->createLogRecord($this->currentDate . 'T10:28:00Z', LogLevel::INFO, 'test', [3]);

        $this->buffer->push($record1);
        $this->buffer->push($record2);
        $this->assertSame(2, $this->buffer->size());

        $this->buffer->push($record3);

        // Après 3 pushes (taille buffer = 3), auto-flush doit avoir eu lieu
        $this->assertSame(0, $this->buffer->size());
        $this->assertFalse($this->buffer->isDirty());

        // Vérifier que les fichiers ont été écrits
        $filePath = $this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);
    }

    public function test_flush_manually_writes_all_records(): void
    {
        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', [1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', [2]);

        $this->buffer->push($record1);
        $this->buffer->push($record2);

        $this->assertSame(2, $this->buffer->size());

        $this->buffer->flush();

        $this->assertSame(0, $this->buffer->size());

        $filePath = $this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);
    }

    public function test_on_flush_callback_is_called(): void
    {
        $callbackCalled = false;
        $callbackCount = 0;

        $this->buffer->onFlush(function ($count) use (&$callbackCalled, &$callbackCount) {
            $callbackCalled = true;
            $callbackCount = $count;
        });

        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', [1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', [2]);
        $record3 = $this->createLogRecord($this->currentDate . 'T10:28:00Z', LogLevel::INFO, 'test', [3]);

        $this->buffer->push($record1);
        $this->buffer->push($record2);
        $this->buffer->push($record3);

        $this->assertTrue($callbackCalled);
        $this->assertSame(3, $callbackCount);
    }

    public function test_set_buffer_size_changes_buffer_capacity(): void
    {
        $this->assertSame(3, $this->buffer->getBufferSize());

        $this->buffer->setBufferSize(5);

        $this->assertSame(5, $this->buffer->getBufferSize());
    }

    public function test_flush_on_empty_buffer_does_nothing(): void
    {
        $this->buffer->flush();

        $this->assertSame(0, $this->buffer->size());
    }

    public function test_destructor_flushes_buffer(): void
    {
        $record = $this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [1],
        );

        $this->buffer->push($record);

        // Buffer destructeur va flush
        unset($this->buffer);

        $filePath = $this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl';
        $this->assertFileExists($filePath);
    }

    public function test_buffer_groups_records_by_file(): void
    {
        $record1 = $this->createLogRecord($this->currentDate . 'T10:26:00Z', LogLevel::INFO, 'test', [1]);
        $record2 = $this->createLogRecord($this->currentDate . 'T11:26:00Z', LogLevel::INFO, 'test', [2]);
        $record3 = $this->createLogRecord($this->currentDate . 'T10:27:00Z', LogLevel::INFO, 'test', [3]);

        $buffer = new LogBufferService($this->writeTask, 10);
        $buffer->push($record1);
        $buffer->push($record2);
        $buffer->push($record3);
        $buffer->flush();

        $this->assertFileExists($this->testLogPath . '/' . $this->currentDate . '/10-11.jsonl');
        $this->assertFileExists($this->testLogPath . '/' . $this->currentDate . '/11-12.jsonl');
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
