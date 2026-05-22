<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Records\AbstractRecord;

final class LogDataRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $type,
        public readonly MixedPayloadCollection $payload,
    ) {}
}
