<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Services;

use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use AndyDefer\Logger\Records\LogRecord;

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
    /**
     * Serializes a LogRecord to a JSON line.
     *
     * @param  LogRecord  $record  The log record to serialize
     * @return string JSON representation of the log record with newline
     */
    public function serialize(LogRecord $record): string
    {
        $normalized = NormalizerChain::get()->normalize($record);

        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Deserializes a JSON line into a LogRecord.
     *
     * @param  string  $line  The JSON line to deserialize
     * @return LogRecord|null The deserialized log record, or null if invalid
     */
    public function deserialize(string $line): ?LogRecord
    {
        try {
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            return LogRecord::from($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validates that a JSON line is a valid log entry.
     *
     * @param  string  $line  The JSON line to validate
     * @return bool True if the line is a valid log entry, false otherwise
     */
    public function isValidLogLine(string $line): bool
    {
        return $this->deserialize($line) !== null;
    }
}
