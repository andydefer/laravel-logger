<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Feature;

use AndyDefer\DomainStructures\Utils\DataObject;
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
use AndyDefer\Logger\ValueObjects\LoggerConfig;

final class LoggerCleanerIntegrationTest extends UnitTestCase
{
    private Logger $logger;

    private LogCleanerService $cleaner;

    private string $testLogPath;

    private string $currentDate;

    protected function setUp(): void
    {
        parent::setUp();

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
        if (is_dir($this->testLogPath)) {
            $this->deleteDirectory($this->testLogPath);
        }
        parent::tearDown();
    }

    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        $payload = new DataObject($payloadData);

        return new LogDataRecord(type: $type, payload: $payload);
    }

    private function writeLogForDate(string $date, int $hour): void
    {
        $timestamp = $date . 'T' . sprintf('%02d', $hour) . ':00:00Z';
        $this->logger->log(new LogRecord(
            time: $timestamp,
            level: LogLevel::INFO,
            data: $this->createLogDataRecord('test', ['hour' => $hour]),
        ));
    }

    public function test_cleaner_removes_old_logs(): void
    {
        $oldDate = '2020-01-01';
        $this->writeLogForDate($oldDate, 10);
        $this->writeLogForDate($this->currentDate, 10);

        $deleted = $this->cleaner->cleanWithCutoff('2020-12-31');

        $this->assertSame(1, $deleted);

        $oldPathExists = file_exists($this->testLogPath . '/' . $oldDate);
        $this->assertFalse($oldPathExists);
    }

    public function test_cleaner_stats_are_accurate(): void
    {
        $this->writeLogForDate($this->currentDate, 10);
        $this->writeLogForDate($this->currentDate, 11);

        $stats = $this->cleaner->getStats();

        $this->assertSame(2, $stats->totalFiles);
        $this->assertSame(1, $stats->totalDays);
        $this->assertGreaterThan(0, $stats->totalSizeBytes);
        $this->assertGreaterThan(0, $stats->totalLines);
    }

    public function test_cleaner_removes_empty_directories(): void
    {
        $testDate = '2020-01-01';
        $this->writeLogForDate($testDate, 10);

        // Nettoyer le fichier
        $this->cleaner->cleanWithCutoff('2020-12-31');

        // Le dossier doit avoir été supprimé car vide
        $dirPath = $this->testLogPath . '/' . $testDate;
        $this->assertDirectoryDoesNotExist($dirPath);
    }

    public function test_clean_uses_config_retention_days(): void
    {
        $this->writeLogForDate($this->currentDate, 10);

        // Retention days = 7 (configuré dans setup)
        $deleted = $this->cleaner->clean();

        // Les logs d'aujourd'hui ne devraient pas être supprimés
        $this->assertSame(0, $deleted);

        $fileExists = file_exists($this->testLogPath . '/' . $this->currentDate);
        $this->assertTrue($fileExists);
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
