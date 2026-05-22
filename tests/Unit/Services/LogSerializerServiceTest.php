<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Services;

use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogSerializerService;

final class LogSerializerServiceTest extends TestCase
{
    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new LogSerializerService;
    }

    private function createSimpleLogRecord(string $time, LogLevel $level, string $type, array $payloadData): LogRecord
    {
        $payload = new MixedPayloadCollection;
        $payload->add(...$payloadData);

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
            payloadData: [1, '127.0.0.1'],
        );

        $jsonLine = $this->serializer->serialize($record);

        $this->assertStringEndsWith("\n", $jsonLine);

        $decoded = json_decode($jsonLine, true);
        $this->assertIsArray($decoded);
        $this->assertSame('2026-04-05T10:26:00Z', $decoded['time']);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('user_login', $decoded['data']['type']);
        $this->assertContains(1, $decoded['data']['payload']);
        $this->assertContains('127.0.0.1', $decoded['data']['payload']);
    }

    public function test_serialize_with_multiple_elements_at_once(): void
    {
        $payload = new MixedPayloadCollection;
        $payload->add(1, 2, 3, 'hello', true, null);

        $logData = new LogDataRecord(type: 'multi_test', payload: $payload);
        $record = new LogRecord(time: '2026-04-05T10:26:00Z', level: LogLevel::INFO, data: $logData);

        $jsonLine = $this->serializer->serialize($record);
        $decoded = json_decode($jsonLine, true);

        $this->assertSame('multi_test', $decoded['data']['type']);
        $this->assertCount(6, $decoded['data']['payload']);
    }

    public function test_deserialize_returns_log_record_for_valid_json(): void
    {
        $jsonLine = '{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"user_login","payload":[1,"127.0.0.1"]}}' . "\n";

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNotNull($record);
        $this->assertSame('2026-04-05T10:26:00Z', $record->time);
        $this->assertSame(LogLevel::INFO, $record->level);
        $this->assertSame('user_login', $record->data->type);
    }

    public function test_deserialize_returns_null_for_invalid_json(): void
    {
        $record = $this->serializer->deserialize('invalid json');

        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_missing_fields(): void
    {
        $jsonLine = '{"time":"2026-04-05T10:26:00Z"}' . "\n";

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_invalid_log_level(): void
    {
        $jsonLine = '{"time":"2026-04-05T10:26:00Z","level":"invalid","data":{"type":"test","payload":[]}}' . "\n";

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_legacy_format(): void
    {
        // Ancien format non supporté
        $jsonLine = '{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"user_login","user_id":1}}' . "\n";

        $record = $this->serializer->deserialize($jsonLine);

        $this->assertNull($record);
    }

    public function test_is_valid_log_line_returns_true_for_valid_log(): void
    {
        $jsonLine = '{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"test","payload":[]}}' . "\n";

        $isValid = $this->serializer->isValidLogLine($jsonLine);

        $this->assertTrue($isValid);
    }

    public function test_is_valid_log_line_returns_false_for_invalid_log(): void
    {
        $jsonLine = 'invalid json';

        $isValid = $this->serializer->isValidLogLine($jsonLine);

        $this->assertFalse($isValid);
    }

    public function test_is_valid_log_line_returns_false_for_legacy_format(): void
    {
        $jsonLine = '{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"user_login","user_id":1}}' . "\n";

        $isValid = $this->serializer->isValidLogLine($jsonLine);

        $this->assertFalse($isValid);
    }
}
