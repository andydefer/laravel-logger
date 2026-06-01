<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Feature;

use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\LoggerConfig;

final class LoggerBufferIntegrationTest extends UnitTestCase
{
    private Logger $logger;

    private string $testLogPath;

    private string $currentDate;

    protected function setUp(): void
    {
        parent::setUp();

        // Utiliser la date figée par Carbon::setTestNow() dans TestCase
        $this->currentDate = '2024-01-01';
        $this->testLogPath = sys_get_temp_dir() . '/logger_buffer_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $serializer = new LogSerializerService;

        $writeTask = new WriteLogTask($pathService, $serializer);
        $queryTask = new QueryLogsTask($pathService, $serializer);
        $streamTask = new StreamLogsTask($pathService, $serializer);

        $this->logger = new Logger($writeTask, $queryTask, $streamTask);
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

    public function test_buffer_accumulates_logs_before_writing(): void
    {
        $this->logger->enableBuffer(10);

        for ($i = 0; $i < 5; $i++) {
            $this->logger->info($this->createLogDataRecord('test', ['index' => $i]));
        }

        // Après 5 logs, aucun fichier ne devrait exister car buffer pas plein
        $datePath = $this->testLogPath . '/' . $this->currentDate;
        $this->assertDirectoryDoesNotExist($datePath);

        // Flush manuel
        $this->logger->flush();

        // Maintenant les fichiers doivent exister
        $this->assertDirectoryExists($datePath);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate . 'T00:00:00Z',
            to: $this->currentDate . 'T23:59:59Z',
        ));
        $this->assertSame(5, $results->count());
    }

    public function test_buffer_auto_flushes_when_full(): void
    {
        $this->logger->enableBuffer(3);

        for ($i = 0; $i < 3; $i++) {
            $this->logger->info($this->createLogDataRecord('test', ['index' => $i]));
        }

        // Après 3 logs, auto-flush doit avoir eu lieu
        $datePath = $this->testLogPath . '/' . $this->currentDate;
        $this->assertDirectoryExists($datePath);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate . 'T00:00:00Z',
            to: $this->currentDate . 'T23:59:59Z',
        ));
        $this->assertSame(3, $results->count());
    }

    public function test_query_returns_consistent_results_with_buffer(): void
    {
        $this->logger->enableBuffer(10);

        $this->logger->info($this->createLogDataRecord('user_login', ['user_id' => 1]));
        $this->logger->info($this->createLogDataRecord('user_login', ['user_id' => 2]));
        $this->logger->error($this->createLogDataRecord('payment_failed', ['payment_id' => 123]));

        // Query devrait flush le buffer avant d'exécuter
        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate . 'T00:00:00Z',
            to: $this->currentDate . 'T23:59:59Z',
            type: 'user_login',
        ));

        $this->assertSame(2, $results->count());
    }

    public function test_disable_buffer_flushes_remaining_logs(): void
    {
        $this->logger->enableBuffer(100);

        $this->logger->info($this->createLogDataRecord('test', ['value' => 1]));
        $this->logger->info($this->createLogDataRecord('test', ['value' => 2]));

        $datePath = $this->testLogPath . '/' . $this->currentDate;
        $this->assertDirectoryDoesNotExist($datePath);

        $this->logger->disableBuffer();

        $this->assertDirectoryExists($datePath);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate . 'T00:00:00Z',
            to: $this->currentDate . 'T23:59:59Z',
        ));
        $this->assertSame(2, $results->count());
    }

    public function test_large_buffer_performance(): void
    {
        $this->logger->enableBuffer(1000);

        for ($i = 0; $i < 500; $i++) {
            $this->logger->info($this->createLogDataRecord('perf_test', ['index' => $i]));
        }

        $this->logger->flush();

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate . 'T00:00:00Z',
            to: $this->currentDate . 'T23:59:59Z',
            type: 'perf_test',
        ));
        $this->assertSame(500, $results->count());
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
