<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record containing the structured components of an ISO Zulu timestamp.
 *
 * @author Andy Defer
 */
final class IsoZuluTimeRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $value,
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
        public readonly int $hour,
        public readonly int $minute,
        public readonly int $second,
    ) {}
}
