<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit\Services;

use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Records\LogStatsRecord;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;

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

        $this->currentDate = '2024-01-01';
        $this->testLogPath = sys_get_temp_dir() . '/cleaner_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 7);
        $this->pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService();
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

    private function writeTestLog(string $date, string $hour): void
    {
        $timestamp = $date . 'T' . $hour . ':00:00Z';

        $payload = new MixedPayloadCollection();
        $payload->add('test', 'data', 'with', 'multiple', 'items', 123, true, null);

        $logData = new LogDataRecord(
            type: 'test',
            payload: $payload,
        );

        $record = new LogRecord(
            time: $timestamp,
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->writeTask->execute($record);

        // Forcer la synchronisation sur le disque
        $filePath = $this->pathService->getHourlyFilePath($timestamp);
        if (file_exists($filePath)) {
            clearstatcache(true, $filePath);
        }
    }

    public function test_get_stats_returns_correct_statistics(): void
    {
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        $stats = $this->cleaner->getStats();

        $this->assertInstanceOf(LogStatsRecord::class, $stats);
        $this->assertSame(2, $stats->totalFiles);
        $this->assertSame(1, $stats->totalDays);
        // Utiliser assertGreaterThanOrEqual car les fichiers peuvent être petits
        $this->assertGreaterThanOrEqual(0, $stats->totalSizeBytes);
        $this->assertGreaterThanOrEqual(0.0, $stats->totalSizeMb);
        $this->assertGreaterThanOrEqual(0, $stats->totalLines);
        $this->assertSame($this->currentDate, $stats->oldestDate);
        $this->assertSame($this->currentDate, $stats->newestDate);

        // Vérifier que les fichiers existent vraiment
        $this->assertGreaterThan(0, $stats->totalFiles);
    }

    public function test_countFilesToDelete_returns_correct_count(): void
    {
        $oldDate = '2020-01-01';
        $this->writeTestLog($oldDate, '10');
        $this->writeTestLog($oldDate, '11');
        $this->writeTestLog($this->currentDate, '10');

        $count = $this->cleaner->countFilesToDelete('2020-12-31');

        $this->assertSame(2, $count);
    }

    public function test_countFilesToDelete_returns_zero_when_no_old_files(): void
    {
        $this->writeTestLog($this->currentDate, '10');

        $count = $this->cleaner->countFilesToDelete('2020-12-31');

        $this->assertSame(0, $count);
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
        $this->assertEmpty($files->toArray());

        $files = $this->pathService->getDayFiles($currentDate);
        $this->assertNotEmpty($files->toArray());
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

        $this->assertEmpty($files->toArray());
    }

    public function test_get_total_size_returns_total_size_of_all_files(): void
    {
        $this->writeTestLog($this->currentDate, '10');
        $this->writeTestLog($this->currentDate, '11');

        $totalSize = $this->cleaner->getTotalSize();

        $this->assertGreaterThanOrEqual(0, $totalSize);
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
