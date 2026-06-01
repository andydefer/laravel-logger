<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use AndyDefer\Logger\Records\LogRecord;
use JsonException;

/**
 * Service for serializing and deserializing log records to/from JSON.
 *
 * Handles conversion of LogRecord objects to JSON lines and back,
 * using the built-in hydration and normalization capabilities.
 *
 * @author Andy Defer
 */
final class LogSerializerService
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const JSON_DECODE_DEPTH = 512;

    /**
     * Serializes a LogRecord to a JSON line.
     *
     * @param LogRecord $record The log record to serialize
     * @return string JSON representation of the log record with newline
     */
    public function serialize(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);
        return $this->encodeToJsonLine($normalized);
    }

    /**
     * Deserializes a JSON line into a LogRecord.
     *
     * @param string $line The JSON line to deserialize
     * @return LogRecord|null The deserialized log record, or null if invalid
     */
    public function deserialize(string $line): ?LogRecord
    {
        $data = $this->decodeJsonLine($line);

        if ($data === null) {
            return null;
        }

        return $this->hydrateLogRecord($data);
    }

    /**
     * Validates that a JSON line is a valid log entry.
     *
     * @param string $line The JSON line to validate
     * @return bool True if the line is a valid log entry, false otherwise
     */
    public function isValidLogLine(string $line): bool
    {
        return $this->deserialize($line) !== null;
    }

    /**
     * Normalize a log record to an array representation.
     *
     * @param LogRecord $record The record to normalize
     * @return array<string, mixed> Normalized array representation
     */
    private function normalizeRecord(LogRecord $record): array
    {
        return NormalizerChain::get()->normalize($record);
    }

    /**
     * Encode a normalized array to a JSON line.
     *
     * @param array<string, mixed> $data Normalized data
     * @return string JSON line with newline
     */
    private function encodeToJsonLine(array $data): string
    {
        return json_encode($data, self::JSON_FLAGS) . "\n";
    }

    /**
     * Decode a JSON line to an associative array.
     *
     * @param string $line JSON line to decode
     * @return array<string, mixed>|null Decoded data or null on error
     */
    private function decodeJsonLine(string $line): ?array
    {
        try {
            $decoded = json_decode($line, true, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                return null;
            }

            return $decoded;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Hydrate a LogRecord from decoded JSON data.
     *
     * @param array<string, mixed> $data Decoded JSON data
     * @return LogRecord|null Hydrated record or null on failure
     */
    private function hydrateLogRecord(array $data): ?LogRecord
    {
        try {
            return LogRecord::from($data);
        } catch (\Exception) {
            return null;
        }
    }
}
