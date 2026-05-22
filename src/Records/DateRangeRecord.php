<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\TypedCollection;

final class DateRangeRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly TypedCollection $dates,
    ) {}
}
