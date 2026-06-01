<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Feature;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\DataObject;
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
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\LoggerConfig;

final class LoggerIntegrationTest extends UnitTestCase
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
        $payload = new DataObject($payloadData);

        return new LogDataRecord(type: $type, payload: $payload);
    }

    // ==================== TESTS ====================

    public function test_complete_logging_workflow(): void
    {
        $this->logger->info($this->createLogDataRecord('user_login', [
            'user_id' => 1,
            'ip' => '127.0.0.1',
        ]));
        $this->logger->info($this->createLogDataRecord('user_login', [
            'user_id' => 2,
            'ip' => '127.0.0.1',
        ]));
        $this->logger->error($this->createLogDataRecord('payment_failed', [
            'payment_id' => 123,
            'amount' => 99.99,
        ]));

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
        $this->logger->info($this->createLogDataRecord('test', ['message' => 'persisted']));

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
        $payload = new DataObject([
            'event' => 'order_created',
            'order_id' => 12345,
            'amount' => 79.98,
            'paid' => true,
        ]);

        $logData = new LogDataRecord(type: 'order_created', payload: $payload);

        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->first();
        $this->assertSame('order_created', $log->data->type);
        $this->assertSame(12345, $log->data->payload->order_id);
        $this->assertSame(79.98, $log->data->payload->amount);
        $this->assertTrue($log->data->payload->paid);
        $this->assertSame('order_created', $log->data->payload->event);
    }

    public function test_multiple_log_levels_are_correctly_stored(): void
    {
        $this->logger->debug($this->createLogDataRecord('debug_msg', ['value' => 1]));
        $this->logger->info($this->createLogDataRecord('info_msg', ['value' => 2]));
        $this->logger->warning($this->createLogDataRecord('warning_msg', ['value' => 3]));
        $this->logger->error($this->createLogDataRecord('error_msg', ['value' => 4]));

        $dateRange = $this->getDateRange();

        $debugResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::DEBUG,
        ));
        $this->assertSame(1, $debugResults->count());
        if ($debugResults->isNotEmpty()) {
            $this->assertSame('debug_msg', $debugResults->first()->data->type);
        }

        $infoResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::INFO,
        ));
        $this->assertSame(1, $infoResults->count());
        if ($infoResults->isNotEmpty()) {
            $this->assertSame('info_msg', $infoResults->first()->data->type);
        }

        $warningResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::WARNING,
        ));
        $this->assertSame(1, $warningResults->count());
        if ($warningResults->isNotEmpty()) {
            $this->assertSame('warning_msg', $warningResults->first()->data->type);
        }

        $errorResults = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            level: LogLevel::ERROR,
        ));
        $this->assertSame(1, $errorResults->count());
        if ($errorResults->isNotEmpty()) {
            $this->assertSame('error_msg', $errorResults->first()->data->type);
        }
    }

    public function test_large_payload_logging(): void
    {
        $payloadData = [];
        for ($i = 0; $i < 100; $i++) {
            $payloadData["item_{$i}"] = $i;
        }
        $payload = new DataObject($payloadData);

        $logData = new LogDataRecord(type: 'large_payload', payload: $payload);

        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            type: 'large_payload',
        ));

        $this->assertSame(1, $results->count());
        $this->assertCount(100, (array) $results->first()->data->payload->toArray());
    }

    public function test_query_by_date_range_boundaries(): void
    {
        $this->logger->info($this->createLogDataRecord('boundary_test', ['value' => 1]));

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

        $payload = new DataObject([
            'action' => 'user_created',
            'user' => $userRecord,
            'success' => true,
        ]);

        $logData = new LogDataRecord(type: 'user', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
            type: 'user',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->first();
        $this->assertSame('user', $log->data->type);
        $this->assertSame('user_created', $log->data->payload->action);

        $serializedRecord = $log->data->payload->user;
        $this->assertIsObject($serializedRecord);
        $this->assertEquals('John Doe', $serializedRecord->name);
        $this->assertEquals('john@example.com', $serializedRecord->email);
        $this->assertEquals('ACTIVE', $serializedRecord->status);
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

        $payload = new DataObject([
            'action' => 'users_list',
            'users' => [$userRecord1, $userRecord2],
        ]);

        $logData = new LogDataRecord(type: 'users', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->first();
        $this->assertSame('users_list', $log->data->payload->action);

        $users = $log->data->payload->users;
        $this->assertIsArray($users);
        $this->assertCount(2, $users);

        $this->assertEquals('John Doe', $users[0]->name);
        $this->assertEquals('admin', $users[0]->role);
        $this->assertEquals('Jane Smith', $users[1]->name);
        $this->assertEquals('user', $users[1]->role);
    }

    public function test_log_with_test_user_record_and_tags(): void
    {
        // Créer un TypedCollection pour les tags
        $tags = new StringTypedCollection;
        $tags->add('premium', 'vip', 'active');

        $userRecord = TestUserRecord::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'tags' => $tags,
        ]);

        $payload = new DataObject([
            'action' => 'user_with_tags',
            'user' => $userRecord,
            'metadata' => 'additional_info',
        ]);

        $logData = new LogDataRecord(type: 'user_tags', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->first();
        $this->assertSame('user_with_tags', $log->data->payload->action);

        $user = $log->data->payload->user;
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(['premium', 'vip', 'active'], $user->tags->toArray());

        $this->assertSame('additional_info', $log->data->payload->metadata);
    }

    public function test_log_with_chained_payload_and_fixtures(): void
    {
        $userRecord = new TestUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
        );

        $payload = new DataObject([
            'action' => 'user_event',
            'user' => $userRecord,
            'code' => 42,
            'success' => true,
        ]);

        $logData = new LogDataRecord(type: 'user_event', payload: $payload);
        $this->logger->info($logData);

        $dateRange = $this->getDateRange();
        $results = $this->logger->query(new LogQueryRecord(
            from: $dateRange['from'],
            to: $dateRange['to'],
        ));

        $this->assertSame(1, $results->count());

        $log = $results->first();
        $this->assertSame('user_event', $log->data->type);
        $this->assertSame('user_event', $log->data->payload->action);
        $this->assertEquals('John Doe', $log->data->payload->user->name);
        $this->assertSame(42, $log->data->payload->code);
        $this->assertTrue($log->data->payload->success);
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
