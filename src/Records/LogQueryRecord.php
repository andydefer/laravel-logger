<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * Query parameters for searching log records.
 *
 * Allows filtering by date range, event type, and log level.
 *
 * @author Andy Defer
 */
final class LogQueryRecord extends AbstractRecord
{
    /**
     * @param  IsoZuluTime  $from  Start date (inclusive)
     * @param  IsoZuluTime  $to  End date (inclusive)
     * @param  string|null  $type  Event type (e.g., 'user_login')
     * @param  LogLevel|null  $level  Log severity level
     */
    public function __construct(
        public readonly IsoZuluTime $from,
        public readonly IsoZuluTime $to,
        public readonly ?string $type = null,
        public readonly ?LogLevel $level = null,
    ) {}
}
