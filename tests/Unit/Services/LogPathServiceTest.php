<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit\Services;

use AndyDefer\Logger\Collections\LogDateCollection;
use AndyDefer\Logger\Collections\LogFileInfoCollection;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\Logger\ValueObjects\LoggerConfig;

/**
 * Test suite for LogPathService.
 *
 * Validates file path generation, date range calculations, directory scanning,
 * and file information retrieval.
 *
 * @author Andy Defer
 */
final class LogPathServiceTest extends UnitTestCase
{
    private LogPathService $service;
    private string $testBasePath;
    private string $currentDate;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create isolated test environment
        $this->currentDate = date('Y-m-d');
        $this->testBasePath = '/tmp/test_logs_' . uniqid();
        $config = new LoggerConfig($this->testBasePath, 30);
        $this->service = new LogPathService($config);
    }

    protected function tearDown(): void
    {
        // Clean up temporary test files
        if (is_dir($this->testBasePath)) {
            $this->deleteDirectory($this->testBasePath);
        }
        parent::tearDown();
    }

    // ==================== CONFIGURATION TESTS ====================

    public function test_get_config_returns_configured_path(): void
    {
        // Act
        $config = $this->service->getConfig();

        // Assert
        $this->assertInstanceOf(LoggerConfig::class, $config);
        $this->assertSame($this->testBasePath, $config->basePath);
    }

    // ==================== PATH GENERATION TESTS ====================

    public function test_get_hourly_file_path_returns_correct_path_for_midnight(): void
    {
        // Arrange
        $timestamp = new IsoZuluTime($this->currentDate . 'T00:26:00Z');

        // Act
        $path = $this->service->getHourlyFilePath($timestamp);

        // Assert
        $expected = $this->testBasePath . '/' . $this->currentDate . '/00-01.jsonl';
        $this->assertSame($expected, $path);
    }

    public function test_get_hourly_file_path_returns_correct_path_for_afternoon(): void
    {
        // Arrange
        $timestamp = new IsoZuluTime($this->currentDate . 'T13:26:00Z');

        // Act
        $path = $this->service->getHourlyFilePath($timestamp);

        // Assert
        $expected = $this->testBasePath . '/' . $this->currentDate . '/13-14.jsonl';
        $this->assertSame($expected, $path);
    }

    public function test_get_hourly_file_path_returns_correct_path_for_end_of_day(): void
    {
        // Arrange
        $timestamp = new IsoZuluTime($this->currentDate . 'T23:26:00Z');

        // Act
        $path = $this->service->getHourlyFilePath($timestamp);

        // Assert
        $expected = $this->testBasePath . '/' . $this->currentDate . '/23-00.jsonl';
        $this->assertSame($expected, $path);
    }

    public function test_get_hourly_file_path_handles_hour_23_correctly(): void
    {
        // Arrange
        $timestamp = new IsoZuluTime($this->currentDate . 'T23:59:59Z');

        // Act
        $path = $this->service->getHourlyFilePath($timestamp);

        // Assert
        $expected = $this->testBasePath . '/' . $this->currentDate . '/23-00.jsonl';
        $this->assertSame($expected, $path);
    }

    // ==================== DATE RANGE TESTS ====================

    public function test_get_date_range_returns_single_day_when_from_and_to_are_same(): void
    {
        // Arrange
        $from = new IsoZuluTime($this->currentDate . 'T00:00:00Z');
        $to = new IsoZuluTime($this->currentDate . 'T23:59:59Z');

        // Act
        $dates = $this->service->getDateRange($from, $to);

        // Assert
        $this->assertInstanceOf(LogDateCollection::class, $dates);
        $this->assertCount(1, $dates);
        $this->assertSame($this->currentDate, $dates->first()?->getValue());
    }

    public function test_get_date_range_returns_multiple_days_when_range_spans_several_days(): void
    {
        // Arrange
        $startDate = date('Y-m-d', strtotime($this->currentDate . ' -2 days'));
        $endDate = $this->currentDate;
        $from = new IsoZuluTime($startDate . 'T00:00:00Z');
        $to = new IsoZuluTime($endDate . 'T23:59:59Z');

        // Act
        $dates = $this->service->getDateRange($from, $to);

        // Assert
        $this->assertInstanceOf(LogDateCollection::class, $dates);
        $this->assertCount(3, $dates);
        $this->assertSame($endDate, $dates->last()?->getValue());
    }

    public function test_get_date_range_uses_retention_days_when_from_is_null(): void
    {
        // Arrange
        $futureDate = date('Y-m-d', strtotime('+60 days'));
        $to = new IsoZuluTime($futureDate . 'T23:59:59Z');

        // Act
        $dates = $this->service->getDateRange(null, $to);

        // Assert
        $this->assertInstanceOf(LogDateCollection::class, $dates);
        $this->assertNotEmpty($dates->toArray());

        $expectedStartDate = date('Y-m-d', strtotime('-30 days'));
        $this->assertSame($expectedStartDate, $dates->first()?->getValue());
        $this->assertSame($futureDate, $dates->last()?->getValue());
    }

    public function test_get_date_range_uses_today_when_to_is_null(): void
    {
        // Arrange
        $today = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $from = new IsoZuluTime($startDate . 'T00:00:00Z');

        // Act
        $dates = $this->service->getDateRange($from, null);

        // Assert
        $this->assertInstanceOf(LogDateCollection::class, $dates);
        $this->assertNotEmpty($dates->toArray());
        $this->assertSame($today, $dates->last()?->getValue());
    }

    public function test_get_date_range_returns_empty_collection_when_start_after_end(): void
    {
        // Arrange
        $pastDate = date('Y-m-d', strtotime('-60 days'));
        $to = new IsoZuluTime($pastDate . 'T23:59:59Z');

        // Act
        $dates = $this->service->getDateRange(null, $to);

        // Assert
        $this->assertInstanceOf(LogDateCollection::class, $dates);
        $this->assertEmpty($dates->toArray());
    }

    public function test_get_date_range_with_fixed_future_date(): void
    {
        // Arrange
        $from = new IsoZuluTime('2026-04-01T00:00:00Z');
        $to = new IsoZuluTime('2026-04-05T23:59:59Z');

        // Act
        $dates = $this->service->getDateRange($from, $to);

        // Assert
        $this->assertInstanceOf(LogDateCollection::class, $dates);

        $expectedDates = ['2026-04-01', '2026-04-02', '2026-04-03', '2026-04-04', '2026-04-05'];
        $actualDates = array_map(fn($date) => $date->getValue(), $dates->toArray());

        $this->assertSame($expectedDates, $actualDates);
    }

    // ==================== FILE OPERATION TESTS ====================

    public function test_get_day_files_returns_empty_collection_when_directory_does_not_exist(): void
    {
        // Act
        $files = $this->service->getDayFiles($this->currentDate);

        // Assert
        $this->assertInstanceOf(LogFileInfoCollection::class, $files);
        $this->assertEmpty($files->toArray());
    }

    public function test_list_all_log_files_returns_empty_when_directory_does_not_exist(): void
    {
        // Act
        $files = $this->service->listAllLogFiles();

        // Assert
        $this->assertInstanceOf(LogFileInfoCollection::class, $files);
        $this->assertEmpty($files->toArray());
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
