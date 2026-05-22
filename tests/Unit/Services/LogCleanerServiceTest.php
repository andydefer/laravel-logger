<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Services;

use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\WriteLogTask;

final class LogCleanerServiceTest extends TestCase
{
    private LogCleanerService $cleaner;

    private LogPathService $pathService;

    private WriteLogTask $writeTask;

    private LogSerializerService $serializer;

    private string $testLogPath;

    private string $currentDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentDate = '2024-01-01'; // Date figée pour les tests
        $this->testLogPath = sys_get_temp_dir() . '/cleaner_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 7);
        $this->pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;
        $this->writeTask = new WriteLogTask($this->pathService, $this->serializer);
        $this->cleaner = new LogCleanerService($this->pathService);
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

    private function writeTestLog(string $date, string $hour): void
    {
        $timestamp = $date . 'T' . $hour . ':00:00Z';
        $record = $this->createLogRecord(
            time: $timestamp,
            level: LogLevel::INFO,
            type: 'test',
            payloadData: ['test'],
        );
        $this->writeTask->execute($record);
    }

    public function test_get_stats_returns_correct_statistics(): void
    {
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        $stats = $this->cleaner->getStats();

        $this->assertArrayHasKey('total_files', $stats);
        $this->assertArrayHasKey('total_days', $stats);
        $this->assertArrayHasKey('total_size_bytes', $stats);
        $this->assertArrayHasKey('total_size_mb', $stats);
        $this->assertArrayHasKey('total_lines', $stats);
        $this->assertArrayHasKey('oldest_date', $stats);
        $this->assertArrayHasKey('newest_date', $stats);

        $this->assertSame(2, $stats['total_files']);
        $this->assertSame(1, $stats['total_days']);
    }

    public function test_clean_with_cutoff_deletes_old_files(): void
    {
        $oldDate = '2020-01-01';
        $currentDate = $this->currentDate;

        $this->writeTestLog($oldDate, '10');
        $this->writeTestLog($currentDate, '10');

        $deleted = $this->cleaner->cleanWithCutoff('2020-12-31');

        $this->assertSame(1, $deleted);

        $files = $this->pathService->getDayFiles($oldDate);
        $this->assertEmpty($files->all());

        $files = $this->pathService->getDayFiles($currentDate);
        $this->assertNotEmpty($files->all());
    }

    public function test_clean_with_cutoff_returns_zero_when_no_old_files(): void
    {
        $this->writeTestLog($this->currentDate, '10');

        $deleted = $this->cleaner->cleanWithCutoff('2020-12-31');

        $this->assertSame(0, $deleted);
    }

    public function test_clean_uses_retention_days_from_config(): void
    {
        $this->writeTestLog($this->currentDate, '10');

        $deleted = $this->cleaner->clean();

        $this->assertIsInt($deleted);
    }

    public function test_get_files_by_date_returns_files_for_specific_date(): void
    {
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        $files = $this->cleaner->getFilesByDate($this->currentDate);

        $this->assertSame(2, $files->count());
    }

    public function test_get_files_by_date_returns_empty_for_date_with_no_files(): void
    {
        $files = $this->cleaner->getFilesByDate('2020-01-01');

        $this->assertEmpty($files->all());
    }

    public function test_get_total_size_returns_total_size_of_all_files(): void
    {
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        $totalSize = $this->cleaner->getTotalSize();

        $this->assertGreaterThan(0, $totalSize);
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
