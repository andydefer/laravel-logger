<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Collections;

use AndyDefer\Directive\Collections\AbstractItemCollection;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Logger\Records\LogRecord;

/**
 * Collection for LogRecord instances.
 *
 * Provides type-safe storage and retrieval of log records with
 * first/last item access.
 *
 * @extends AbstractTypedCollection<LogRecord>
 */
final class LogRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(LogRecord::class);
    }

    /**
     * Gets all records as an array of arrays (normalized).
     *
     * @return array<array<string, mixed>>
     */
    public function toNormalizedArray(): array
    {
        return array_map(
            fn(LogRecord $record) => $record->toArrayWithoutNulls(),
            $this->toArray()
        );
    }

    /**
     * Gets all records as a JSON string.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode(
            $this->toNormalizedArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
