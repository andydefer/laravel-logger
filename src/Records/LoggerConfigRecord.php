<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record containing the logger configuration data.
 *
 * This is a simple immutable data holder. For business logic and factory methods,
 * use the LoggerConfig Value Object instead.
 *
 * @author Andy Defer
 */
final class LoggerConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $basePath,
        public readonly int $retentionDays = 30,
    ) {}
}
