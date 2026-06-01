<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Services;

use AndyDefer\DomainStructures\Enums\PhpType;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tests\UnitTestCase;

final class LogSerializerServiceTest extends UnitTestCase
{
    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new LogSerializerService;
    }

    private function createSimpleLogRecord(string $time, LogLevel $level, string $type, array $payloadData): LogRecord
    {
        $payload = new StrictDataObject($payloadData);

        $logData = new LogDataRecord(type: $type, payload: $payload);

        return new LogRecord(
            time: $time,
            level: $level,
            data: $logData,
        );
    }

    public function test_serialize_returns_valid_json_line(): void
    {
        $record = $this->createSimpleLogRecord(
            time: '2026-04-05T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: [
                'user_id' => 1,
                'ip' => '127.0.0.1',
            ],
        );

        $jsonLine = $this->serializer->serialize($record);

        $this->assertStringEndsWith("\n", $jsonLine);

        // Décoder en tableau, pas en objet LogRecord
        $decoded = json_decode($jsonLine, true);

        $this->assertIsArray($decoded);
        $this->assertSame('2026-04-05T10:26:00Z', $decoded['time']);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('user_login', $decoded['data']['type']);
        $this->assertSame(1, $decoded['data']['payload']['user_id']);
        $this->assertSame('127.0.0.1', $decoded['data']['payload']['ip']);
    }

    public function test_serialize_with_multiple_elements_at_once(): void
    {
        $payload = new StrictDataObject([
            'value1' => 1,
            'value2' => 2,
            'value3' => 3,
            'message' => 'hello',
            'active' => true,
            'optional' => null,
        ]);

        $logData = new LogDataRecord(type: 'multi_test', payload: $payload);
        $record = new LogRecord(time: '2026-04-05T10:26:00Z', level: LogLevel::INFO, data: $logData);

        $jsonLine = $this->serializer->serialize($record);
        $decoded = json_decode($jsonLine, true);

        $this->assertSame('multi_test', $decoded['data']['type']);
        $this->assertIsArray($decoded['data']['payload']);
        $this->assertSame(1, $decoded['data']['payload']['value1']);
        $this->assertSame(2, $decoded['data']['payload']['value2']);
        $this->assertSame(3, $decoded['data']['payload']['value3']);
        $this->assertSame('hello', $decoded['data']['payload']['message']);
        $this->assertTrue($decoded['data']['payload']['active']);
        $this->assertNull($decoded['data']['payload']['optional']);
    }

    public function test_deserialize_returns_log_record_for_valid_json(): void
    {
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'info',
            'data' => [
                'type' => 'user_login',
                'payload' => [
                    'user_id' => 1,
                    'ip' => '127.0.0.1',
                    'action' => 'login',
                ],
            ],
        ];

        $jsonLine = json_encode($data) . "\n";

        PhpType::class;

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNotNull($record);
        $this->assertSame('2026-04-05T10:26:00Z', $record->time);
        $this->assertSame(LogLevel::INFO, $record->level);
        $this->assertSame('user_login', $record->data->type);
        $this->assertSame(1, $record->data->payload->user_id);
        $this->assertSame('127.0.0.1', $record->data->payload->ip);
        $this->assertSame('login', $record->data->payload->action);
    }

    public function test_deserialize_returns_null_for_invalid_json(): void
    {
        $record = $this->serializer->deserialize('invalid json');

        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_missing_fields(): void
    {
        $data = ['time' => '2026-04-05T10:26:00Z'];
        $jsonLine = json_encode($data) . "\n";

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_invalid_log_level(): void
    {
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'invalid',
            'data' => [
                'type' => 'test',
                'payload' => [],
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_legacy_format(): void
    {
        // Ancien format où payload était un tableau indexé (non supporté)
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'info',
            'data' => [
                'type' => 'user_login',
                'user_id' => 1, // ancien format, pas de payload
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNull($record);
    }

    public function test_is_valid_log_line_returns_true_for_valid_log(): void
    {
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'info',
            'data' => [
                'type' => 'test',
                'payload' => ['key' => 'value'],
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        $isValid = $this->serializer->isValidLogLine($jsonLine);

        $this->assertTrue($isValid);
    }

    public function test_is_valid_log_line_returns_false_for_invalid_log(): void
    {
        $isValid = $this->serializer->isValidLogLine('invalid json');

        $this->assertFalse($isValid);
    }

    public function test_is_valid_log_line_returns_false_for_legacy_format(): void
    {
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'info',
            'data' => [
                'type' => 'user_login',
                'user_id' => 1, // ancien format, pas de payload
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        $isValid = $this->serializer->isValidLogLine($jsonLine);

        $this->assertFalse($isValid);
    }
}
