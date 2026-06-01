<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Logger\Collections\LogDateCollection;

final class DateRangeRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly LogDateCollection $dates,
    ) {}
}
