<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Tasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\ValueObjects\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;

final class QueryLogsTaskTest extends UnitTestCase
{
    private QueryLogsTask $queryTask;

    private WriteLogTask $writeTask;

    private string $testLogPath;

    private string $currentDate;

    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentDate = date('Y-m-d');
        $this->testLogPath = sys_get_temp_dir() . '/test_logs_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;

        $this->writeTask = new WriteLogTask($pathService, $this->serializer);
        $this->queryTask = new QueryLogsTask($pathService, $this->serializer);
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
        $payload = new StrictDataObject($payloadData);

        $logData = new LogDataRecord(type: $type, payload: $payload);

        return new LogRecord(
            time: $time,
            level: $level,
            data: $logData,
        );
    }

    private function getFullDayRange(): array
    {
        return [
            'from' => $this->currentDate . 'T00:00:00Z',
            'to' => $this->currentDate . 'T23:59:59Z',
        ];
    }

    public function test_execute_returns_all_logs_when_no_filters(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: null,
            level: null,
        );
        $results = $this->queryTask->execute($query);

        $this->assertSame(2, $results->count());
    }

    public function test_execute_filters_by_type(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 2],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T12:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'user_login',
            level: null,
        );
        $results = $this->queryTask->execute($query);

        $this->assertSame(2, $results->count());

        foreach ($results as $result) {
            $this->assertSame('user_login', $result->data->type);
        }
    }

    public function test_execute_filters_by_level(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: [],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T12:26:00Z',
            level: LogLevel::WARNING,
            type: 'system_warning',
            payloadData: ['reason' => 'high_memory'],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: null,
            level: LogLevel::ERROR,
        );
        $results = $this->queryTask->execute($query);

        $this->assertSame(1, $results->count());
        $this->assertSame('payment_failed', $results->first()->data->type);
    }

    public function test_execute_filters_by_date_range(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T09:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [],
        ));

        $query = new LogQueryRecord(
            from: $this->currentDate . 'T10:00:00Z',
            to: $this->currentDate . 'T10:59:59Z',
            type: null,
            level: null,
        );

        $results = $this->queryTask->execute($query);

        $this->assertSame(1, $results->count());
        $this->assertStringContainsString('10:26', $results->first()->time);
    }

    public function test_execute_combines_multiple_filters(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 2],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::ERROR,
            type: 'payment_failed',
            payloadData: ['payment_id' => 123],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'user_login',
            level: LogLevel::INFO,
        );
        $results = $this->queryTask->execute($query);

        $this->assertSame(2, $results->count());
    }

    public function test_execute_returns_empty_collection_when_no_matches(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: ['user_id' => 1],
        ));

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'nonexistent_type',
            level: null,
        );
        $results = $this->queryTask->execute($query);

        $this->assertSame(0, $results->count());
        $this->assertTrue($results->isEmpty());
    }

    public function test_execute_handles_logs_without_type_field(): void
    {
        // Créer un log sans type (ancien format simulé)
        $payload = new StrictDataObject(['message' => 'Simple log without type']);

        $logData = new LogDataRecord(type: 'unknown', payload: $payload);

        $record = new LogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->writeTask->execute($record);

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: 'user_login',
            level: null,
        );
        $results = $this->queryTask->execute($query);

        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_corrupted_json_lines_gracefully(): void
    {
        // Créer un fichier avec une ligne JSON corrompue
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';
        file_put_contents($testFile, "corrupted json line\n");
        file_put_contents($testFile, $this->serializer->serialize($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'valid_log',
            payloadData: ['value' => 42],
        )), FILE_APPEND);

        $range = $this->getFullDayRange();
        $query = new LogQueryRecord(
            from: $range['from'],
            to: $range['to'],
            type: null,
            level: null,
        );
        $results = $this->queryTask->execute($query);

        // La ligne corrompue doit être ignorée, seule la ligne valide est lue
        $this->assertSame(1, $results->count());
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
