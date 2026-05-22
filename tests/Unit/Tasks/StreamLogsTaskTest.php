<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Tasks;

use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;

final class StreamLogsTaskTest extends TestCase
{
    private StreamLogsTask $streamTask;

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
        $this->streamTask = new StreamLogsTask($pathService, $this->serializer);
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

    public function test_execute_returns_all_logs_for_specific_date(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [1],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [2],
        ));

        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $this->writeTask->execute($this->createLogRecord(
            time: $futureDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [3],
        ));

        $results = $this->streamTask->execute($this->currentDate);

        $this->assertSame(2, $results->count());
    }

    public function test_execute_returns_all_logs_for_current_date_when_date_null(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [1],
        ));

        $results = $this->streamTask->execute();

        $this->assertSame(1, $results->count());
    }

    public function test_execute_returns_empty_collection_when_no_logs_for_date(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 year'));
        $results = $this->streamTask->execute($futureDate);

        $this->assertSame(0, $results->count());
        $this->assertTrue($results->isEmpty());
    }

    public function test_execute_handles_multiple_hour_files_for_same_date(): void
    {
        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [10],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T11:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [11],
        ));

        $this->writeTask->execute($this->createLogRecord(
            time: $this->currentDate . 'T23:26:00Z',
            level: LogLevel::INFO,
            type: 'test',
            payloadData: [23],
        ));

        $results = $this->streamTask->execute($this->currentDate);

        $this->assertSame(3, $results->count());
    }

    public function test_execute_handles_missing_directory_gracefully(): void
    {
        $config = new LoggerConfig('/nonexistent/path/' . uniqid(), 30);
        $pathService = new LogPathService($config);
        $task = new StreamLogsTask($pathService, $this->serializer);

        $results = $task->execute('2026-04-05');

        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_invalid_json_line_gracefully(): void
    {
        // Créer un fichier avec une ligne JSON invalide
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';
        file_put_contents($testFile, "invalid json line\n");

        $results = $this->streamTask->execute($testDate);

        // La ligne invalide doit être ignorée silencieusement
        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_missing_fields_in_json(): void
    {
        // Créer un fichier avec un JSON manquant les champs requis
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';
        file_put_contents($testFile, json_encode(['time' => '2026-04-05T10:26:00Z']) . "\n");

        $results = $this->streamTask->execute($testDate);

        // Le log manquant des champs doit être ignoré
        $this->assertSame(0, $results->count());
    }

    public function test_execute_handles_mixed_valid_and_invalid_lines(): void
    {
        $testDate = $this->currentDate;
        $testDir = $this->testLogPath . '/' . $testDate;
        mkdir($testDir, 0755, true);

        $testFile = $testDir . '/10-11.jsonl';

        // Écrire une ligne invalide
        file_put_contents($testFile, "invalid json line\n");

        // Écrire une ligne valide
        $validRecord = $this->createLogRecord(
            time: $this->currentDate . 'T10:26:00Z',
            level: LogLevel::INFO,
            type: 'valid_log',
            payloadData: [42],
        );
        file_put_contents($testFile, $this->serializer->serialize($validRecord), FILE_APPEND);

        // Écrire une autre ligne invalide
        file_put_contents($testFile, "another invalid line\n", FILE_APPEND);

        $results = $this->streamTask->execute($testDate);

        // Seule la ligne valide doit être retournée
        $this->assertSame(1, $results->count());
        $this->assertSame('valid_log', $results->firstItem()->data->type);
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
