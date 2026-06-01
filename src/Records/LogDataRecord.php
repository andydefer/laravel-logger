<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class LogDataRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $type,
        public readonly StrictDataObject $payload,
    ) {}
}
