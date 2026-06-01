<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit\Services;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Records\LogStatsRecord;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\Logger\ValueObjects\LoggerConfig;

/**
 * Test suite for LogCleanerService.
 *
 * Validates log file cleanup, statistics generation, file counting,
 * and directory cleanup functionality.
 *
 * @author Andy Defer
 */
final class LogCleanerServiceTest extends UnitTestCase
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

        // Arrange: Use fixed date for deterministic tests
        $this->currentDate = '2024-01-01';
        $this->testLogPath = sys_get_temp_dir() . '/cleaner_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 7);
        $this->pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;
        $this->writeTask = new WriteLogTask($this->pathService, $this->serializer);
        $this->cleaner = new LogCleanerService($this->pathService);
    }

    protected function tearDown(): void
    {
        // Clean up temporary test files
        if (is_dir($this->testLogPath)) {
            $this->deleteDirectory($this->testLogPath);
        }
        parent::tearDown();
    }

    /**
     * Write a test log file for a specific date and hour.
     */
    private function writeTestLog(string $date, string $hour): void
    {
        $timestamp = $date . 'T' . $hour . ':00:00Z';
        $time = new IsoZuluTime($timestamp);

        $payload = new StrictDataObject([
            'key1' => 'test',
            'key2' => 'data',
            'key3' => 'with',
            'key4' => 'multiple',
            'key5' => 'items',
            'number' => 123,
            'active' => true,
            'optional' => null,
        ]);

        $logData = new LogDataRecord(type: 'test', payload: $payload);
        $record = new LogRecord(time: $time, level: LogLevel::INFO, data: $logData);

        $this->writeTask->execute($record);

        // Force filesystem synchronization
        $filePath = $this->pathService->getHourlyFilePath($time);
        if (file_exists($filePath)) {
            clearstatcache(true, $filePath);
        }
    }

    // ==================== STATISTICS TESTS ====================

    public function test_get_stats_returns_correct_statistics(): void
    {
        // Arrange
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        // Act
        $stats = $this->cleaner->getStats();

        // Assert
        $this->assertInstanceOf(LogStatsRecord::class, $stats);
        $this->assertSame(2, $stats->totalFiles);
        $this->assertSame(1, $stats->totalDays);
        $this->assertGreaterThanOrEqual(0, $stats->totalSizeBytes);
        $this->assertGreaterThanOrEqual(0.0, $stats->totalSizeMb);
        $this->assertGreaterThanOrEqual(0, $stats->totalLines);
        $this->assertSame($this->currentDate, $stats->oldestDate);
        $this->assertSame($this->currentDate, $stats->newestDate);
        $this->assertGreaterThan(0, $stats->totalFiles);
    }

    public function test_get_stats_returns_empty_record_when_no_files(): void
    {
        // Act
        $stats = $this->cleaner->getStats();

        // Assert
        $this->assertSame(0, $stats->totalFiles);
        $this->assertSame(0, $stats->totalDays);
        $this->assertSame(0, $stats->totalSizeBytes);
        $this->assertSame(0.0, $stats->totalSizeMb);
        $this->assertSame(0, $stats->totalLines);
        $this->assertNull($stats->oldestDate);
        $this->assertNull($stats->newestDate);
    }

    // ==================== COUNTING TESTS ====================

    public function test_count_files_to_delete_returns_correct_count(): void
    {
        // Arrange
        $oldDate = '2020-01-01';
        $this->writeTestLog($oldDate, '10');
        $this->writeTestLog($oldDate, '11');
        $this->writeTestLog($this->currentDate, '10');

        $cutoff = new IsoZuluTime('2020-12-31T23:59:59Z');

        // Act
        $count = $this->cleaner->countFilesToDelete($cutoff);

        // Assert
        $this->assertSame(2, $count);
    }

    public function test_count_files_to_delete_returns_zero_when_no_old_files(): void
    {
        // Arrange
        $this->writeTestLog($this->currentDate, '10');

        $cutoff = new IsoZuluTime('2020-12-31T23:59:59Z');

        // Act
        $count = $this->cleaner->countFilesToDelete($cutoff);

        // Assert
        $this->assertSame(0, $count);
    }

    // ==================== DELETION TESTS ====================

    public function test_clean_with_cutoff_deletes_old_files(): void
    {
        // Arrange
        $oldDate = '2020-01-01';
        $currentDate = $this->currentDate;

        $this->writeTestLog($oldDate, '10');
        $this->writeTestLog($currentDate, '10');

        $cutoff = new IsoZuluTime('2020-12-31T23:59:59Z');

        // Act
        $deleted = $this->cleaner->cleanWithCutoff($cutoff);

        // Assert
        $this->assertSame(1, $deleted);

        $files = $this->pathService->getDayFiles($oldDate);
        $this->assertEmpty($files->toArray());

        $files = $this->pathService->getDayFiles($currentDate);
        $this->assertNotEmpty($files->toArray());
    }

    public function test_clean_with_cutoff_returns_zero_when_no_old_files(): void
    {
        // Arrange
        $this->writeTestLog($this->currentDate, '10');

        $cutoff = new IsoZuluTime('2020-12-31T23:59:59Z');

        // Act
        $deleted = $this->cleaner->cleanWithCutoff($cutoff);

        // Assert
        $this->assertSame(0, $deleted);
    }

    public function test_clean_uses_retention_days_from_config(): void
    {
        // Arrange
        $this->writeTestLog($this->currentDate, '10');

        // Act
        $deleted = $this->cleaner->clean();

        // Assert
        $this->assertIsInt($deleted);
    }

    // ==================== FILE RETRIEVAL TESTS ====================

    public function test_get_files_by_date_returns_files_for_specific_date(): void
    {
        // Arrange
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        // Act
        $files = $this->cleaner->getFilesByDate($this->currentDate);

        // Assert
        $this->assertSame(2, $files->count());
    }

    public function test_get_files_by_date_returns_empty_for_date_with_no_files(): void
    {
        // Act
        $files = $this->cleaner->getFilesByDate('2020-01-01');

        // Assert
        $this->assertEmpty($files->toArray());
    }

    // ==================== SIZE CALCULATION TESTS ====================

    public function test_get_total_size_returns_total_size_of_all_files(): void
    {
        // Arrange
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        // Act
        $totalSize = $this->cleaner->getTotalSize();

        // Assert
        $this->assertGreaterThanOrEqual(0, $totalSize);
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
