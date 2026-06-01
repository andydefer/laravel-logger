<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class LogStatsRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $totalFiles,
        public readonly int $totalDays,
        public readonly int $totalSizeBytes,
        public readonly float $totalSizeMb,
        public readonly int $totalLines,
        public readonly ?string $oldestDate,
        public readonly ?string $newestDate,
    ) {}
}
