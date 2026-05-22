<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Feature;

use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Logger\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\Logger\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class LoggerIntegrationTest extends TestCase
{
    private Logger $logger;

    private string $testLogPath;

    private string $fixedDate;

    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        // Utiliser la date figée par Carbon::setTestNow() dans TestCase
        $this->fixedDate = '2024-01-01';
        $this->testLogPath = sys_get_temp_dir() . '/logger_test_' . uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;

        $writeTask = new WriteLogTask($pathService, $this->serializer);
        $queryTask = new QueryLogsTask($pathService, $this->serializer);
        $streamTask = new StreamLogsTask($pathService, $this->serializer);

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
        $payload = new MixedPayloadCollection;
        foreach ($payloadData as $item) {
            $payload->add($item);
        }

        return new LogDataRecord(type: $type, payload: $payload);
    }

    private function getDateRange(): array
    {
        return [
            'from' => $this->fixedDate . 'T00:00:00Z',
            'to' => $this->fixedDate . 'T23:59:59Z',
        ];
    }

    // ==================== TESTS ====================

    public function test_complete_logging_workflow(): void
    {
        $this->logger->info($this->createLogDataRecord('user_login', [1, '127.0.0.1']));
        $this->logger->info($this->createLogDataRecord('user_login', [2, '127.0.0.1']));
        $this->logger->error($this->createLogDataRecord('payment_failed', [123, 99.99]));

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            type: 'user_login',
        ));

        $this->assertSame(2, $results->count());

        $streamResults = $this->logger->stream($this->fixedDate);
        $this->assertSame(3, $streamResults->count());
    }

    public function test_logs_are_persisted_between_instances(): void
    {
        $this->logger->info($this->createLogDataRecord('test', ['persisted']));

        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $serializer = new LogSerializerService;

        $writeTask = new WriteLogTask($pathService, $serializer);
        $queryTask = new QueryLogsTask($pathService, $serializer);
        $streamTask = new StreamLogsTask($pathService, $serializer);

        $newLogger = new Logger($writeTask, $queryTask, $streamTask);

        $dateRange = $this->getDateRange();
        $results = $newLogger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());
    }

    public function test_can_log_and_query_complex_data(): void
    {
        $payload = new MixedPayloadCollection;
        $payload->add('order_created');
        $payload->add(12345);
        $payload->add(79.98);
        $payload->add(true);

        $logData = new LogDataRecord(type: 'order_created', payload: $payload);

        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $this->assertSame('order_created', $log->data->type);
        $this->assertContains(12345, $log->data->payload->all());
        $this->assertContains(79.98, $log->data->payload->all());
        $this->assertContains(true, $log->data->payload->all());
    }

    public function test_multiple_log_levels_are_correctly_stored(): void
    {
        $this->logger->debug($this->createLogDataRecord('debug_msg', []));
        $this->logger->info($this->createLogDataRecord('info_msg', []));
        $this->logger->warning($this->createLogDataRecord('warning_msg', []));
        $this->logger->error($this->createLogDataRecord('error_msg', []));

        $dateRange = $this->getDateRange();

        $debugResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::DEBUG,
        ));
        $this->assertSame(1, $debugResults->count());
        if ($debugResults->isNotEmpty()) {
            $this->assertSame('debug_msg', $debugResults->firstItem()->data->type);
        }

        $infoResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::INFO,
        ));
        $this->assertSame(1, $infoResults->count());
        if ($infoResults->isNotEmpty()) {
            $this->assertSame('info_msg', $infoResults->firstItem()->data->type);
        }

        $warningResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::WARNING,
        ));
        $this->assertSame(1, $warningResults->count());
        if ($warningResults->isNotEmpty()) {
            $this->assertSame('warning_msg', $warningResults->firstItem()->data->type);
        }

        $errorResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::ERROR,
        ));
        $this->assertSame(1, $errorResults->count());
        if ($errorResults->isNotEmpty()) {
            $this->assertSame('error_msg', $errorResults->firstItem()->data->type);
        }
    }

    public function test_large_payload_logging(): void
    {
        $largePayload = new MixedPayloadCollection;
        for ($i = 0; $i < 100; $i++) {
            $largePayload->add($i);
        }

        $logData = new LogDataRecord(type: 'large_payload', payload: $largePayload);

        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            type: 'large_payload',
        ));

        $this->assertSame(1, $results->count());
        $this->assertCount(100, $results->firstItem()->data->payload->all());
    }

    public function test_query_by_date_range_boundaries(): void
    {
        $this->logger->info($this->createLogDataRecord('boundary_test', []));

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());
    }

    // ==================== TESTS AVEC Fixture TestUserRecord ====================

    public function test_log_with_test_user_record_in_payload(): void
    {
        // Créer un TestUserRecord avec des données
        $userRecord = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
        );

        $payload = new MixedPayloadCollection;
        $payload->add('user_created');
        $payload->add($userRecord);
        $payload->add(true);

        $logData = new LogDataRecord(type: 'user', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            type: 'user',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $this->assertSame('user', $log->data->type);
        $this->assertSame('user_created', $log->data->payload->firstItem());

        $serializedRecord = $log->data->payload->toArray()[1];
        $this->assertIsObject($serializedRecord);
        $this->assertEquals('John Doe', $serializedRecord->name);
        $this->assertEquals('john@example.com', $serializedRecord->email);
        // Pour les pure enums, on compare avec le nom de l'enum (MAJUSCULES)
        $this->assertEquals('ACTIVE', $serializedRecord->status);
        // Pour TestUserRole qui est un backed enum (string), on compare avec sa valeur
        $this->assertEquals('admin', $serializedRecord->role);
    }

    public function test_log_with_multiple_test_user_records_in_payload(): void
    {
        $userRecord1 = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            role: TestUserRole::ADMIN,
        );

        $userRecord2 = new TestUserRecord(
            name: 'Jane Smith',
            email: 'jane@example.com',
            role: TestUserRole::USER,
        );

        $payload = new MixedPayloadCollection;
        $payload->add('users_list', $userRecord1, $userRecord2);

        $logData = new LogDataRecord(type: 'users', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $payloadArray = $log->data->payload->toArray();

        $this->assertSame('users_list', $payloadArray[0]);

        $this->assertIsObject($payloadArray[1]);
        $this->assertEquals('John Doe', $payloadArray[1]->name);
        $this->assertEquals('admin', $payloadArray[1]->role);

        $this->assertIsObject($payloadArray[2]);
        $this->assertEquals('Jane Smith', $payloadArray[2]->name);
        $this->assertEquals('user', $payloadArray[2]->role);
    }

    public function test_log_with_test_user_record_and_tags(): void
    {
        // Créer un TypedRecords pour les tags
        $tags = new StringTypedCollection();
        $tags->add('premium', 'vip', 'active');

        $userRecord = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            tags: $tags,
        );

        $payload = new MixedPayloadCollection;
        $payload->add('user_with_tags');
        $payload->add($userRecord);
        $payload->add('metadata');

        $logData = new LogDataRecord(type: 'user_tags', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $payloadArray = $log->data->payload->toArray();

        $this->assertSame('user_with_tags', $payloadArray[0]);

        $this->assertIsObject($payloadArray[1]);
        $this->assertEquals('John Doe', $payloadArray[1]->name);
        $this->assertEquals(['premium', 'vip', 'active'], $payloadArray[1]->tags);

        $this->assertSame('metadata', $payloadArray[2]);
    }

    public function test_log_with_chained_payload_and_fixtures(): void
    {
        $userRecord = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
        );

        $payload = new MixedPayloadCollection;
        $payload->add('user_event')->add($userRecord)->add(42)->add(true);

        $logData = new LogDataRecord(type: 'user_event', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $payloadArray = $log->data->payload->toArray();

        $this->assertSame('user_event', $payloadArray[0]);
        $this->assertIsObject($payloadArray[1]);
        $this->assertEquals('John Doe', $payloadArray[1]->name);
        $this->assertEquals(42, $payloadArray[2]);
        $this->assertEquals(true, $payloadArray[3]);
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
