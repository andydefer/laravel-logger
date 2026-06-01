<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Records\Tests\Fixtures\Enums\TestBackedStringEnum;

final class TestUserCriteriaRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?TestBackedStringEnum $status = null,
    ) {}
}
