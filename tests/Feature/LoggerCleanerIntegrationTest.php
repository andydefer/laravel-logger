<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Feature;

use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\Logger\ValueObjects\LoggerConfig;

/**
 * Integration test for Logger with LogCleanerService.
 *
 * Validates the complete workflow of writing logs and cleaning them
 * based on retention policies and cutoff dates.
 *
 * @author Andy Defer
 */
final class LoggerCleanerIntegrationTest extends UnitTestCase
{
    private Logger $logger;
    private LogCleanerService $cleaner;
    private string $testLogPath;
    private string $currentDate;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create isolated test environment
        $this->currentDate = date('Y-m-d');
        $this->testLogPath = sys_get_temp_dir() . '/logger_cleaner_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 7);
        $pathService = new LogPathService($config);
        $serializer = new LogSerializerService;

        $writeTask = new WriteLogTask($pathService, $serializer);
        $queryTask = new QueryLogsTask($pathService, $serializer);
        $streamTask = new StreamLogsTask($pathService, $serializer);

        $this->logger = new Logger($writeTask, $queryTask, $streamTask);
        $this->cleaner = new LogCleanerService($pathService);
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
     * Create a log data record with the given payload.
     */
    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        $payload = new StrictDataObject($payloadData);
        return new LogDataRecord(type: $type, payload: $payload);
    }

    /**
     * Write a test log for a specific date and hour.
     */
    private function writeLogForDate(string $date, int $hour): void
    {
        $timestamp = $date . 'T' . sprintf('%02d', $hour) . ':00:00Z';
        $time = new IsoZuluTime($timestamp);

        $record = new LogRecord(
            time: $time,
            level: LogLevel::INFO,
            data: $this->createLogDataRecord('test', ['hour' => $hour]),
        );

        $this->logger->log($record);
    }

    // ==================== CLEANUP TESTS ====================

    public function test_cleaner_removes_old_logs(): void
    {
        // Arrange
        $oldDate = '2020-01-01';
        $this->writeLogForDate($oldDate, 10);
        $this->writeLogForDate($this->currentDate, 10);

        $cutoff = new IsoZuluTime('2020-12-31T23:59:59Z');

        // Act
        $deleted = $this->cleaner->cleanWithCutoff($cutoff);

        // Assert
        $this->assertSame(1, $deleted);
        $oldPathExists = file_exists($this->testLogPath . '/' . $oldDate);
        $this->assertFalse($oldPathExists);
    }

    public function test_cleaner_removes_empty_directories(): void
    {
        // Arrange
        $testDate = '2020-01-01';
        $this->writeLogForDate($testDate, 10);

        $cutoff = new IsoZuluTime('2020-12-31T23:59:59Z');

        // Act
        $this->cleaner->cleanWithCutoff($cutoff);

        // Assert
        $dirPath = $this->testLogPath . '/' . $testDate;
        $this->assertDirectoryDoesNotExist($dirPath);
    }

    public function test_clean_uses_config_retention_days(): void
    {
        // Arrange
        $this->writeLogForDate($this->currentDate, 10);

        // Act
        $deleted = $this->cleaner->clean();

        // Assert: Retention days = 7, current logs should NOT be deleted
        $this->assertSame(0, $deleted);
        $fileExists = file_exists($this->testLogPath . '/' . $this->currentDate);
        $this->assertTrue($fileExists);
    }

    // ==================== STATISTICS TESTS ====================

    public function test_cleaner_stats_are_accurate(): void
    {
        // Arrange
        $this->writeLogForDate($this->currentDate, 10);
        $this->writeLogForDate($this->currentDate, 11);

        // Act
        $stats = $this->cleaner->getStats();

        // Assert
        $this->assertSame(2, $stats->totalFiles);
        $this->assertSame(1, $stats->totalDays);
        $this->assertGreaterThan(0, $stats->totalSizeBytes);
        $this->assertGreaterThan(0, $stats->totalLines);
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
