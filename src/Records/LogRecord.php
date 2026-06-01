<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * Log record representing a structured log entry.
 *
 * @author Andy Defer
 */
final class LogRecord extends AbstractRecord
{
    public function __construct(
        public readonly IsoZuluTime $time,
        public readonly LogLevel $level,
        public readonly LogDataRecord $data,
    ) {}
}
