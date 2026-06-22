<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Integration;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Strategies\TemporalPathStrategy;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\LoggerService;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Tests\IntegrationTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;

final class LoggerServiceIntegrationTest extends IntegrationTestCase
{
    private LoggerService $logger;

    private FileSystemService $fileSystem;

    private string $tempDir;

    private TemporalPathStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new FileSystemService;
        $this->tempDir = sys_get_temp_dir().'/logger_test_'.uniqid();
        $this->fileSystem->makeDirectory($this->tempDir, PermissionMode::DIRECTORY, true);

        $this->strategy = new TemporalPathStrategy($this->tempDir);

        // ✅ Correction : Ajouter JsonlContext (5 paramètres)
        $jsonlService = new JsonlService(
            pathStrategy: $this->strategy,
            fileSystem: $this->fileSystem,
            context: new JsonlContext,  // ← AJOUTÉ
            defaultBufferSize: null,
            directoryPermission: PermissionMode::DIRECTORY,
        );

        $hydrationService = new HydrationService;

        $this->logger = new LoggerService($jsonlService, $hydrationService);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function getFileContent(string $filePath): string
    {
        return $this->fileSystem->get($filePath);
    }

    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        return new LogDataRecord(
            type: $type,
            payload: new StrictDataObject($payloadData),
        );
    }

    // ==================== Tests d'écriture ====================

    public function test_info_writes_log_file_with_correct_content(): void
    {
        $data = $this->createLogDataRecord('user_login', ['user_id' => 123, 'ip' => '192.168.1.100']);

        $this->logger->info($data);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $this->assertFileExists($expectedPath);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(1, $lines);

        $jsonData = json_decode($lines[0], true);
        $this->assertSame('info', $jsonData['level']);
        $this->assertSame('user_login', $jsonData['type']);
        $this->assertSame(123, $jsonData['payload']['user_id']);
        $this->assertSame('192.168.1.100', $jsonData['payload']['ip']);
    }

    public function test_warning_writes_log_file_with_correct_level(): void
    {
        $data = $this->createLogDataRecord('payment_failed', ['order_id' => 456, 'reason' => 'insufficient_funds']);

        $this->logger->warning($data);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $this->assertFileExists($expectedPath);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(1, $lines);

        $jsonData = json_decode($lines[0], true);
        $this->assertSame('warning', $jsonData['level']);
        $this->assertSame('payment_failed', $jsonData['type']);
        $this->assertSame(456, $jsonData['payload']['order_id']);
    }

    public function test_error_writes_log_file_with_correct_level(): void
    {
        $data = $this->createLogDataRecord('system_error', ['code' => 500, 'message' => 'Internal Server Error']);

        $this->logger->error($data);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $this->assertFileExists($expectedPath);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(1, $lines);

        $jsonData = json_decode($lines[0], true);
        $this->assertSame('error', $jsonData['level']);
        $this->assertSame('system_error', $jsonData['type']);
        $this->assertSame(500, $jsonData['payload']['code']);
    }

    public function test_debug_writes_log_file_with_correct_level(): void
    {
        $data = $this->createLogDataRecord('cache_hit', ['key' => 'user_123', 'ttl' => 3600]);

        $this->logger->debug($data);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $this->assertFileExists($expectedPath);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(1, $lines);

        $jsonData = json_decode($lines[0], true);
        $this->assertSame('debug', $jsonData['level']);
        $this->assertSame('cache_hit', $jsonData['type']);
        $this->assertSame('user_123', $jsonData['payload']['key']);
    }

    public function test_log_writes_from_log_record(): void
    {
        $logData = $this->createLogDataRecord('custom_event', ['value' => 42]);

        // ✅ Correction : IsoZuluTime nécessite une valeur
        $time = new IsoZuluTime(date('Y-m-d\TH:i:s\Z'));

        $record = new LogRecord(
            time: $time,
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->logger->log($record);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $this->assertFileExists($expectedPath);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(1, $lines);

        $jsonData = json_decode($lines[0], true);
        $this->assertSame('info', $jsonData['level']);
        $this->assertSame('custom_event', $jsonData['type']);
        $this->assertSame(42, $jsonData['payload']['value']);
    }

    public function test_write_multiple_logs_appends_to_same_file(): void
    {
        $data1 = $this->createLogDataRecord('event1', ['id' => 1]);
        $data2 = $this->createLogDataRecord('event2', ['id' => 2]);

        $this->logger->info($data1);
        $this->logger->info($data2);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(2, $lines);

        $jsonData1 = json_decode($lines[0], true);
        $jsonData2 = json_decode($lines[1], true);

        $this->assertSame('event1', $jsonData1['type']);
        $this->assertSame('event2', $jsonData2['type']);
    }

    // ==================== Tests de requêtage ====================

    public function test_query_returns_matching_logs(): void
    {
        $loginData = $this->createLogDataRecord('user_login', ['user_id' => 123]);
        $paymentData = $this->createLogDataRecord('payment_success', ['amount' => 99.99]);

        $this->logger->info($loginData);
        $this->logger->info($paymentData);

        $query = new LogQueryRecord(
            from: new IsoZuluTime(date('Y-m-d').'T00:00:00Z'),
            to: new IsoZuluTime(date('Y-m-d').'T23:59:59Z'),
            type: 'user_login',
            level: null,
        );

        $results = $this->logger->query($query);

        $this->assertSame(1, $results->count());

        $first = $results->first();
        $this->assertSame('user_login', $first->data->type);
        $this->assertSame(123, $first->data->payload->user_id);
    }

    public function test_query_filters_by_level(): void
    {
        $infoData = $this->createLogDataRecord('test_event', ['value' => 1]);
        $errorData = $this->createLogDataRecord('test_event', ['value' => 2]);

        $this->logger->info($infoData);
        $this->logger->error($errorData);

        $query = new LogQueryRecord(
            from: new IsoZuluTime(date('Y-m-d').'T00:00:00Z'),
            to: new IsoZuluTime(date('Y-m-d').'T23:59:59Z'),
            type: null,
            level: LogLevel::ERROR,
        );

        $results = $this->logger->query($query);

        $this->assertSame(1, $results->count());

        $first = $results->first();
        $this->assertSame(LogLevel::ERROR, $first->level);
        $this->assertSame(2, $first->data->payload->value);
    }

    public function test_query_filters_by_type_and_level(): void
    {
        $loginInfo = $this->createLogDataRecord('user_login', ['user_id' => 123]);
        $loginError = $this->createLogDataRecord('user_login', ['user_id' => 456, 'error' => 'invalid_password']);
        $paymentInfo = $this->createLogDataRecord('payment', ['amount' => 50]);

        $this->logger->info($loginInfo);
        $this->logger->error($loginError);
        $this->logger->info($paymentInfo);

        $query = new LogQueryRecord(
            from: new IsoZuluTime(date('Y-m-d').'T00:00:00Z'),
            to: new IsoZuluTime(date('Y-m-d').'T23:59:59Z'),
            type: 'user_login',
            level: LogLevel::ERROR,
        );

        $results = $this->logger->query($query);

        $this->assertSame(1, $results->count());

        $first = $results->first();
        $this->assertSame('user_login', $first->data->type);
        $this->assertSame(LogLevel::ERROR, $first->level);
        $this->assertSame(456, $first->data->payload->user_id);
    }

    public function test_query_returns_empty_collection_when_no_match(): void
    {
        $loginData = $this->createLogDataRecord('user_login', ['user_id' => 123]);
        $this->logger->info($loginData);

        $query = new LogQueryRecord(
            from: new IsoZuluTime(date('Y-m-d').'T00:00:00Z'),
            to: new IsoZuluTime(date('Y-m-d').'T23:59:59Z'),
            type: 'nonexistent_type',
            level: null,
        );

        $results = $this->logger->query($query);

        $this->assertSame(0, $results->count());
    }

    public function test_query_respects_date_range(): void
    {
        $data = $this->createLogDataRecord('test_event', ['value' => 1]);
        $this->logger->info($data);

        $query = new LogQueryRecord(
            from: new IsoZuluTime('2000-01-01T00:00:00Z'),
            to: new IsoZuluTime('2000-01-01T23:59:59Z'),
            type: null,
            level: null,
        );

        $results = $this->logger->query($query);

        $this->assertSame(0, $results->count());
    }

    // ==================== Tests de streaming ====================

    public function test_stream_returns_all_logs_for_today(): void
    {
        $data1 = $this->createLogDataRecord('event1', ['id' => 1]);
        $data2 = $this->createLogDataRecord('event2', ['id' => 2]);

        $this->logger->info($data1);
        $this->logger->info($data2);

        $results = $this->logger->stream();

        $this->assertSame(2, $results->count());

        $first = $results->first();
        $last = $results->last();

        $this->assertSame('event1', $first->data->type);
        $this->assertSame('event2', $last->data->type);
    }

    public function test_stream_returns_logs_for_specific_date(): void
    {
        $data = $this->createLogDataRecord('test_event', ['value' => 1]);
        $this->logger->info($data);

        $currentDate = date('Y-m-d');

        $results = $this->logger->stream($currentDate);

        $this->assertSame(1, $results->count());

        $first = $results->first();
        $this->assertSame('test_event', $first->data->type);
    }

    public function test_stream_returns_empty_collection_for_date_without_logs(): void
    {
        $results = $this->logger->stream('2000-01-01');

        $this->assertSame(0, $results->count());
    }

    // ==================== Tests de buffer ====================

    public function test_buffer_writes_after_reaching_size(): void
    {
        $this->logger->enableBuffer(3);

        $data1 = $this->createLogDataRecord('event1', ['id' => 1]);
        $data2 = $this->createLogDataRecord('event2', ['id' => 2]);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        // Écrire 2 logs (buffer pas encore flush)
        $this->logger->info($data1);
        $this->logger->info($data2);

        // Vérifier que le fichier n'existe pas encore
        $this->assertFileDoesNotExist($expectedPath);

        // 3ème log déclenche le flush
        $data3 = $this->createLogDataRecord('event3', ['id' => 3]);
        $this->logger->info($data3);

        $this->assertFileExists($expectedPath);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(3, $lines);
    }

    public function test_flush_buffer_writes_pending_records(): void
    {
        $this->logger->enableBuffer(10);

        $data1 = $this->createLogDataRecord('event1', ['id' => 1]);
        $data2 = $this->createLogDataRecord('event2', ['id' => 2]);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $this->logger->info($data1);
        $this->logger->info($data2);

        // Vérifier que le fichier n'existe pas encore
        $this->assertFileDoesNotExist($expectedPath);

        // Flush manuel
        $this->logger->flush();

        $this->assertFileExists($expectedPath);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $this->assertCount(2, $lines);
    }

    public function test_disable_buffer_flushes_and_disables(): void
    {
        $this->logger->enableBuffer(10);

        $data = $this->createLogDataRecord('event', ['id' => 1]);
        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $this->logger->info($data);

        // Désactiver le buffer (doit flush)
        $this->logger->disableBuffer();

        $this->assertFileExists($expectedPath);
        $this->assertFalse($this->logger->isBufferEnabled());
    }

    public function test_is_buffer_enabled_returns_correct_state(): void
    {
        $this->assertFalse($this->logger->isBufferEnabled());

        $this->logger->enableBuffer(50);
        $this->assertTrue($this->logger->isBufferEnabled());

        $this->logger->disableBuffer();
        $this->assertFalse($this->logger->isBufferEnabled());
    }

    public function test_get_buffer_size_returns_correct_size(): void
    {
        $this->assertSame(0, $this->logger->getBufferSize());

        $this->logger->enableBuffer(50);
        $this->assertSame(50, $this->logger->getBufferSize());

        $this->logger->disableBuffer();
        $this->assertSame(0, $this->logger->getBufferSize());
    }

    // ==================== Tests de structure des données ====================

    public function test_log_record_contains_timestamp(): void
    {
        $data = $this->createLogDataRecord('test', ['value' => 1]);

        $this->logger->info($data);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $jsonData = json_decode($lines[0], true);

        $this->assertArrayHasKey('time', $jsonData);

        $timestamp = $jsonData['time'];
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00/', $timestamp);
    }

    public function test_log_data_record_has_type_and_payload(): void
    {
        $data = $this->createLogDataRecord('api_call', ['endpoint' => '/users', 'method' => 'GET']);

        $this->logger->info($data);

        $expectedPath = implode(DIRECTORY_SEPARATOR, [
            $this->tempDir,
            date('Y-m-d'),
            date('H').'.jsonl',
        ]);

        $content = $this->getFileContent($expectedPath);
        $lines = explode("\n", trim($content));
        $jsonData = json_decode($lines[0], true);

        $this->assertArrayHasKey('type', $jsonData);
        $this->assertArrayHasKey('payload', $jsonData);
        $this->assertSame('api_call', $jsonData['type']);
        $this->assertSame('/users', $jsonData['payload']['endpoint']);
        $this->assertSame('GET', $jsonData['payload']['method']);
    }
}
