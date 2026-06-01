<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Services;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogRecord;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * Test suite for LogSerializerService.
 *
 * Validates JSON serialization and deserialization of log records,
 * including edge cases like invalid JSON, missing fields, and legacy formats.
 *
 * @author Andy Defer
 */
final class LogSerializerServiceTest extends UnitTestCase
{
    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new LogSerializerService;
    }

    /**
     * Create a test log record with the given parameters.
     */
    private function createLogRecord(string $time, LogLevel $level, string $type, array $payloadData): LogRecord
    {
        $payload = new StrictDataObject($payloadData);
        $logData = new LogDataRecord(type: $type, payload: $payload);
        $isoTime = new IsoZuluTime($time);

        return new LogRecord(time: $isoTime, level: $level, data: $logData);
    }

    // ==================== SERIALIZATION TESTS ====================

    public function test_serialize_returns_valid_json_line(): void
    {
        // Arrange
        $record = $this->createLogRecord(
            time: '2026-04-05T10:26:00Z',
            level: LogLevel::INFO,
            type: 'user_login',
            payloadData: [
                'user_id' => 1,
                'ip' => '127.0.0.1',
            ],
        );

        // Act
        $jsonLine = $this->serializer->serialize($record);
        $decoded = json_decode($jsonLine, true);

        // Assert
        $this->assertStringEndsWith("\n", $jsonLine);
        $this->assertIsArray($decoded);
        $this->assertSame('2026-04-05T10:26:00Z', $decoded['time']);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('user_login', $decoded['data']['type']);
        $this->assertSame(1, $decoded['data']['payload']['user_id']);
        $this->assertSame('127.0.0.1', $decoded['data']['payload']['ip']);
    }

    public function test_serialize_with_complex_payload_structure(): void
    {
        // Arrange
        $payload = new StrictDataObject([
            'value1' => 1,
            'value2' => 2,
            'value3' => 3,
            'message' => 'hello',
            'active' => true,
            'optional' => null,
        ]);

        $logData = new LogDataRecord(type: 'multi_test', payload: $payload);
        $record = new LogRecord(
            time: new IsoZuluTime('2026-04-05T10:26:00Z'),
            level: LogLevel::INFO,
            data: $logData,
        );

        // Act
        $jsonLine = $this->serializer->serialize($record);
        $decoded = json_decode($jsonLine, true);

        // Assert
        $this->assertSame('multi_test', $decoded['data']['type']);
        $this->assertIsArray($decoded['data']['payload']);
        $this->assertSame(1, $decoded['data']['payload']['value1']);
        $this->assertSame(2, $decoded['data']['payload']['value2']);
        $this->assertSame(3, $decoded['data']['payload']['value3']);
        $this->assertSame('hello', $decoded['data']['payload']['message']);
        $this->assertTrue($decoded['data']['payload']['active']);
        $this->assertNull($decoded['data']['payload']['optional']);
    }

    // ==================== DESERIALIZATION TESTS ====================

    public function test_deserialize_returns_log_record_for_valid_json(): void
    {
        // Arrange
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

        // Act
        $record = $this->serializer->deserialize($jsonLine);

        // Assert
        $this->assertNotNull($record);
        $this->assertSame('2026-04-05T10:26:00Z', $record->time->getValue());
        $this->assertSame(LogLevel::INFO, $record->level);
        $this->assertSame('user_login', $record->data->type);
        $this->assertSame(1, $record->data->payload->user_id);
        $this->assertSame('127.0.0.1', $record->data->payload->ip);
        $this->assertSame('login', $record->data->payload->action);
    }

    public function test_deserialize_returns_null_for_invalid_json(): void
    {
        // Act
        $record = $this->serializer->deserialize('invalid json');

        // Assert
        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_missing_required_fields(): void
    {
        // Arrange
        $data = ['time' => '2026-04-05T10:26:00Z'];
        $jsonLine = json_encode($data) . "\n";

        // Act
        $record = $this->serializer->deserialize($jsonLine);

        // Assert
        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_invalid_log_level(): void
    {
        // Arrange
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'invalid',
            'data' => [
                'type' => 'test',
                'payload' => [],
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        // Act
        $record = $this->serializer->deserialize($jsonLine);

        // Assert
        $this->assertNull($record);
    }

    public function test_deserialize_returns_null_for_legacy_format_without_payload(): void
    {
        // Arrange - Legacy format where payload was an indexed array
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'info',
            'data' => [
                'type' => 'user_login',
                'user_id' => 1, // Legacy: no payload field
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        // Act
        $record = $this->serializer->deserialize($jsonLine);

        // Assert
        $this->assertNull($record);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_is_valid_log_line_returns_true_for_valid_log(): void
    {
        // Arrange
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'info',
            'data' => [
                'type' => 'test',
                'payload' => ['key' => 'value'],
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        // Act
        $isValid = $this->serializer->isValidLogLine($jsonLine);

        // Assert
        $this->assertTrue($isValid);
    }

    public function test_is_valid_log_line_returns_false_for_invalid_json(): void
    {
        // Act
        $isValid = $this->serializer->isValidLogLine('invalid json');

        // Assert
        $this->assertFalse($isValid);
    }

    public function test_is_valid_log_line_returns_false_for_legacy_format(): void
    {
        // Arrange
        $data = [
            'time' => '2026-04-05T10:26:00Z',
            'level' => 'info',
            'data' => [
                'type' => 'user_login',
                'user_id' => 1, // Legacy: no payload
            ],
        ];
        $jsonLine = json_encode($data) . "\n";

        // Act
        $isValid = $this->serializer->isValidLogLine($jsonLine);

        // Assert
        $this->assertFalse($isValid);
    }

    // ==================== ROUND TRIP TESTS ====================

    public function test_serialize_deserialize_round_trip_preserves_data(): void
    {
        // Arrange
        $original = $this->createLogRecord(
            time: '2026-04-05T10:26:00Z',
            level: LogLevel::ERROR,
            type: 'round_trip_test',
            payloadData: [
                'integer' => 42,
                'string' => 'test',
                'boolean' => true,
                'null' => null,
                'nested' => ['key' => 'value'],
            ],
        );

        // Act
        $jsonLine = $this->serializer->serialize($original);
        $hydrated = $this->serializer->deserialize($jsonLine);

        // Assert
        $this->assertNotNull($hydrated);
        $this->assertSame($original->time->getValue(), $hydrated->time->getValue());
        $this->assertSame($original->level, $hydrated->level);
        $this->assertSame($original->data->type, $hydrated->data->type);
        $this->assertSame(42, $hydrated->data->payload->integer);
        $this->assertSame('test', $hydrated->data->payload->string);
        $this->assertTrue($hydrated->data->payload->boolean);
        $this->assertNull($hydrated->data->payload->null);
        $this->assertSame('value', $hydrated->data->payload->nested['key']);
    }
}
