<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Records\LogJsonlRecord;
use AndyDefer\LaravelJsonl\Records\TemporalLogQueryRecord;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\LoggerService;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Logger\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class LoggerServiceUnitTest extends UnitTestCase
{
    private JsonlService&MockObject $jsonlService;
    private HydrationService $hydrationService;
    private LoggerService $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonlService = $this->createMock(JsonlService::class);
        $this->hydrationService = new HydrationService();
        $this->logger = new LoggerService($this->jsonlService, $this->hydrationService);
    }

    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        return new LogDataRecord(
            type: $type,
            payload: new StrictDataObject($payloadData),
        );
    }

    // ==================== Tests d'écriture ====================

    public function test_info_writes_log_with_correct_level(): void
    {
        $data = $this->createLogDataRecord('test_event', ['value' => 1]);

        $this->jsonlService->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($record) {
                return $record instanceof LogJsonlRecord
                    && $record->level === 'info';
            }));

        $this->logger->info($data);
    }

    public function test_warning_writes_log_with_correct_level(): void
    {
        $data = $this->createLogDataRecord('test_event', ['value' => 1]);

        $this->jsonlService->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($record) {
                return $record instanceof LogJsonlRecord
                    && $record->level === 'warning';
            }));

        $this->logger->warning($data);
    }

    public function test_error_writes_log_with_correct_level(): void
    {
        $data = $this->createLogDataRecord('test_event', ['value' => 1]);

        $this->jsonlService->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($record) {
                return $record instanceof LogJsonlRecord
                    && $record->level === 'error';
            }));

        $this->logger->error($data);
    }

    public function test_debug_writes_log_with_correct_level(): void
    {
        $data = $this->createLogDataRecord('test_event', ['value' => 1]);

        $this->jsonlService->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($record) {
                return $record instanceof LogJsonlRecord
                    && $record->level === 'debug';
            }));

        $this->logger->debug($data);
    }

    public function test_log_writes_from_log_record(): void
    {
        $logData = $this->createLogDataRecord('test_event', ['value' => 1]);
        $time = new IsoZuluTime('2026-01-15T10:00:00Z');

        $record = new LogRecord(
            time: $time,
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->jsonlService->expects($this->once())
            ->method('write')
            ->with($this->callback(function ($jsonlRecord) use ($record) {
                return $jsonlRecord instanceof LogJsonlRecord
                    && $jsonlRecord->level === $record->level->value;
            }));

        $this->logger->log($record);
    }

    // ==================== Tests de buffer ====================

    public function test_enable_buffer_calls_jsonl_service(): void
    {
        $this->jsonlService->expects($this->once())
            ->method('enableBuffer')
            ->with(50);

        $this->logger->enableBuffer(50);
    }

    public function test_disable_buffer_calls_jsonl_service(): void
    {
        $this->jsonlService->expects($this->once())
            ->method('disableBuffer');

        $this->logger->disableBuffer();
    }

    public function test_flush_calls_jsonl_service(): void
    {
        $this->jsonlService->expects($this->once())
            ->method('flushBuffer');

        $this->logger->flush();
    }

    public function test_is_buffer_enabled_delegates_to_jsonl_service(): void
    {
        $this->jsonlService->expects($this->once())
            ->method('isBufferEnabled')
            ->willReturn(true);

        $result = $this->logger->isBufferEnabled();

        $this->assertTrue($result);
    }

    public function test_get_buffer_size_delegates_to_jsonl_service(): void
    {
        $this->jsonlService->expects($this->once())
            ->method('getBufferSize')
            ->willReturn(100);

        $result = $this->logger->getBufferSize();

        $this->assertSame(100, $result);
    }

    // ==================== Tests de requêtage ====================

    public function test_query_returns_empty_collection_when_no_files(): void
    {
        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: null,
            level: null,
        );

        $this->jsonlService->expects($this->once())
            ->method('getFilesToScan')
            ->with($this->callback(function ($jsonlQuery) {
                return $jsonlQuery instanceof TemporalLogQueryRecord
                    && $jsonlQuery->from->getValue() === '2026-01-15T00:00:00+00:00'
                    && $jsonlQuery->to->getValue() === '2026-01-15T23:59:59+00:00';
            }))
            ->willReturn([]);

        $result = $this->logger->query($query);

        $this->assertSame(0, $result->count());
    }

    public function test_query_skips_nonexistent_files(): void
    {
        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: null,
            level: null,
        );

        $this->jsonlService->expects($this->once())
            ->method('getFilesToScan')
            ->willReturn(['/path/file1.jsonl', '/path/file2.jsonl']);

        $this->jsonlService->expects($this->exactly(2))
            ->method('fileExists')
            ->willReturn(false);

        $this->jsonlService->expects($this->never())
            ->method('search');

        $result = $this->logger->query($query);

        $this->assertSame(0, $result->count());
    }

    public function test_query_filters_by_type(): void
    {
        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: 'user_login',
            level: null,
        );

        $filePath = '/path/file.jsonl';
        $lineData = [
            'time' => '2026-01-15T10:00:00Z',
            'level' => 'info',
            'type' => 'user_login',
            'payload' => ['user_id' => 1]
        ];

        $this->jsonlService->expects($this->once())
            ->method('getFilesToScan')
            ->willReturn([$filePath]);

        $this->jsonlService->expects($this->once())
            ->method('fileExists')
            ->with($filePath)
            ->willReturn(true);

        $this->jsonlService->expects($this->once())
            ->method('search')
            ->with($filePath, $this->callback(function ($filter) {
                return is_callable($filter);
            }))
            ->willReturn([$lineData]);

        $result = $this->logger->query($query);

        $this->assertSame(1, $result->count());

        $first = $result->first();
        $this->assertInstanceOf(LogRecord::class, $first);
        $this->assertSame('user_login', $first->data->type);
    }

    public function test_query_filters_by_level(): void
    {
        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: null,
            level: LogLevel::ERROR,
        );

        $filePath = '/path/file.jsonl';
        $lineData = [
            'time' => '2026-01-15T10:00:00Z',
            'level' => 'error',
            'type' => 'payment_failed',
            'payload' => ['payment_id' => 123]
        ];

        $this->jsonlService->expects($this->once())
            ->method('getFilesToScan')
            ->willReturn([$filePath]);

        $this->jsonlService->expects($this->once())
            ->method('fileExists')
            ->willReturn(true);

        $this->jsonlService->expects($this->once())
            ->method('search')
            ->willReturn([$lineData]);

        $result = $this->logger->query($query);

        $this->assertSame(1, $result->count());

        $first = $result->first();
        $this->assertInstanceOf(LogRecord::class, $first);
        $this->assertSame(LogLevel::ERROR, $first->level);
    }

    // ==================== Tests de streaming ====================

    public function test_stream_with_null_date_uses_current_date(): void
    {
        $this->jsonlService->expects($this->once())
            ->method('getBaseDirectory')
            ->willReturn('/logs');

        $this->jsonlService->expects($this->exactly(24))
            ->method('fileExists')
            ->willReturn(false);

        $result = $this->logger->stream();

        $this->assertSame(0, $result->count());
    }

    public function test_stream_with_specific_date(): void
    {
        $date = '2026-01-15';

        $this->jsonlService->expects($this->once())
            ->method('getBaseDirectory')
            ->willReturn('/logs');

        $this->jsonlService->expects($this->exactly(24))
            ->method('fileExists')
            ->willReturn(false);

        $result = $this->logger->stream($date);

        $this->assertSame(0, $result->count());
    }

    public function test_stream_reads_existing_files(): void
    {
        $date = '2026-01-15';

        $this->jsonlService->expects($this->once())
            ->method('getBaseDirectory')
            ->willReturn('/logs');

        $this->jsonlService->expects($this->exactly(24))
            ->method('fileExists')
            ->willReturnCallback(function ($filePath) {
                return str_contains($filePath, '10.jsonl');
            });

        $this->jsonlService->expects($this->once())
            ->method('readAll')
            ->willReturn([['time' => '2026-01-15T10:00:00Z', 'level' => 'info', 'type' => 'test', 'payload' => []]]);

        $result = $this->logger->stream($date);

        $this->assertSame(1, $result->count());

        $first = $result->first();
        $this->assertInstanceOf(LogRecord::class, $first);
        $this->assertSame('test', $first->data->type);
    }

    // ==================== Tests de correspondance de requête ====================

    public function test_matches_query_with_type_filter(): void
    {
        $line = ['type' => 'user_login', 'level' => 'info'];

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: 'user_login',
            level: null,
        );

        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('matchesQuery');

        $result = $method->invoke($this->logger, $line, $query);

        $this->assertTrue($result);
    }

    public function test_matches_query_with_level_filter(): void
    {
        $line = ['type' => 'user_login', 'level' => 'error'];

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: null,
            level: LogLevel::ERROR,
        );

        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('matchesQuery');

        $result = $method->invoke($this->logger, $line, $query);

        $this->assertTrue($result);
    }

    public function test_matches_query_with_type_and_level_filters(): void
    {
        $line = ['type' => 'user_login', 'level' => 'info'];

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: 'user_login',
            level: LogLevel::INFO,
        );

        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('matchesQuery');

        $result = $method->invoke($this->logger, $line, $query);

        $this->assertTrue($result);
    }

    public function test_matches_query_returns_false_when_type_mismatch(): void
    {
        $line = ['type' => 'payment_failed', 'level' => 'info'];

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: 'user_login',
            level: null,
        );

        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('matchesQuery');

        $result = $method->invoke($this->logger, $line, $query);

        $this->assertFalse($result);
    }

    public function test_matches_query_returns_false_when_level_mismatch(): void
    {
        $line = ['type' => 'user_login', 'level' => 'debug'];

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: null,
            level: LogLevel::ERROR,
        );

        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('matchesQuery');

        $result = $method->invoke($this->logger, $line, $query);

        $this->assertFalse($result);
    }

    public function test_matches_query_handles_missing_type_field(): void
    {
        $line = ['level' => 'info'];

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: 'user_login',
            level: null,
        );

        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('matchesQuery');

        $result = $method->invoke($this->logger, $line, $query);

        $this->assertFalse($result);
    }

    public function test_matches_query_handles_missing_level_field(): void
    {
        $line = ['type' => 'user_login'];

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2026-01-15T00:00:00Z'),
            to: new IsoZuluTime('2026-01-15T23:59:59Z'),
            type: null,
            level: LogLevel::INFO,
        );

        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('matchesQuery');

        $result = $method->invoke($this->logger, $line, $query);

        $this->assertFalse($result);
    }
}
