<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Records\Collections\TypedCollection;

final class LogPathServiceTest extends TestCase
{
    private LogPathService $service;

    private Logger $logger;

    private string $testBasePath;

    private string $currentDate;

    protected function setUp(): void
    {

        parent::setUp();
        $this->currentDate = date('Y-m-d');
        $this->logger = app(Logger::class);
        $this->testBasePath = '/tmp/test_logs_' . uniqid();
        $config = new LoggerConfig($this->testBasePath, 30);
        $this->service = new LogPathService($config);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testBasePath)) {
            $this->deleteDirectory($this->testBasePath);
        }
        parent::tearDown();
    }

    public function test_get_hourly_file_path_returns_correct_path_for_midnight(): void
    {
        $timestamp = $this->currentDate . 'T00:26:00Z';
        $path = $this->service->getHourlyFilePath($timestamp);

        $expected = $this->testBasePath . '/' . $this->currentDate . '/00-01.jsonl';
        $this->assertSame($expected, $path);
    }

    public function test_get_hourly_file_path_returns_correct_path_for_afternoon(): void
    {
        $timestamp = $this->currentDate . 'T13:26:00Z';
        $path = $this->service->getHourlyFilePath($timestamp);

        $expected = $this->testBasePath . '/' . $this->currentDate . '/13-14.jsonl';
        $this->assertSame($expected, $path);
    }

    public function test_get_hourly_file_path_returns_correct_path_for_end_of_day(): void
    {
        $timestamp = $this->currentDate . 'T23:26:00Z';
        $path = $this->service->getHourlyFilePath($timestamp);

        $expected = $this->testBasePath . '/' . $this->currentDate . '/23-00.jsonl';
        $this->assertSame($expected, $path);
    }

    public function test_get_hourly_file_path_handles_hour_23_correctly(): void
    {
        $timestamp = $this->currentDate . 'T23:59:59Z';
        $path = $this->service->getHourlyFilePath($timestamp);

        $expected = $this->testBasePath . '/' . $this->currentDate . '/23-00.jsonl';
        $this->assertSame($expected, $path);
    }

    public function test_get_date_range_returns_single_day_when_from_and_to_are_same(): void
    {
        $dates = $this->service->getDateRange($this->currentDate . 'T00:00:00Z', $this->currentDate . 'T23:59:59Z');

        $this->assertInstanceOf(TypedCollection::class, $dates);
        // toArray() retourne un array, pas all()
        $this->assertSame([$this->currentDate], $dates->toArray());
    }

    public function test_get_date_range_returns_multiple_days_when_range_spans_several_days(): void
    {
        $startDate = date('Y-m-d', strtotime($this->currentDate . ' -2 days'));
        $endDate = $this->currentDate;

        $dates = $this->service->getDateRange($startDate . 'T00:00:00Z', $endDate . 'T23:59:59Z');

        $this->assertInstanceOf(TypedCollection::class, $dates);
        $this->assertCount(3, $dates->toArray());
        $this->assertSame($endDate, $dates->lastItem());
    }

    public function test_get_date_range_uses_retention_days_when_from_is_null(): void
    {
        // Utiliser une date de fin qui est dans le futur par rapport à la date de début calculée
        $futureDate = date('Y-m-d', strtotime('+60 days'));
        $dates = $this->service->getDateRange(null, $futureDate . 'T23:59:59Z');

        $this->assertInstanceOf(TypedCollection::class, $dates);
        $this->assertNotEmpty($dates->toArray());

        // Vérifier que la première date correspond à aujourd'hui - 30 jours
        $expectedStartDate = date('Y-m-d', strtotime('-30 days'));
        $this->assertSame($expectedStartDate, $dates->firstItem());

        // Vérifier que la dernière date est la date de fin
        $this->assertSame($futureDate, $dates->lastItem());
    }

    public function test_get_date_range_uses_today_when_to_is_null(): void
    {
        $today = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));

        $dates = $this->service->getDateRange($startDate . 'T00:00:00Z', null);

        $this->assertInstanceOf(TypedCollection::class, $dates);
        $this->assertNotEmpty($dates->toArray());
        $this->assertSame($today, $dates->lastItem());
    }

    public function test_get_date_range_returns_empty_collection_when_start_after_end(): void
    {
        // Utiliser une date de fin antérieure à la date de début calculée
        $pastDate = date('Y-m-d', strtotime('-60 days'));

        $dates = $this->service->getDateRange(null, $pastDate . 'T23:59:59Z');

        $this->assertInstanceOf(TypedCollection::class, $dates);
        $this->assertEmpty($dates->toArray());
    }

    public function test_get_day_files_returns_empty_typed_records_when_directory_does_not_exist(): void
    {
        $files = $this->service->getDayFiles($this->currentDate);

        $this->assertInstanceOf(TypedCollection::class, $files);
        $this->assertEmpty($files->toArray());
    }

    public function test_get_config_returns_config_instance(): void
    {
        $config = $this->service->getConfig();

        $this->assertInstanceOf(LoggerConfig::class, $config);
        $this->assertSame($this->testBasePath, $config->basePath);
    }

    public function test_get_date_range_with_fixed_future_date(): void
    {
        // Utiliser une date future pour tester sans dépendre de la date actuelle
        $dates = $this->service->getDateRange('2026-04-01T00:00:00Z', '2026-04-05T23:59:59Z');

        $this->assertInstanceOf(TypedCollection::class, $dates);
        // toArray() retourne un array
        $this->assertSame(['2026-04-01', '2026-04-02', '2026-04-03', '2026-04-04', '2026-04-05'], $dates->toArray());
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
