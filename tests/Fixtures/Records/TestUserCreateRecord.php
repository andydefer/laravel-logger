<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Records\Tests\Fixtures\Enums\TestBackedStringEnum;

final class TestUserCreateRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly TestBackedStringEnum $status = TestBackedStringEnum::ACTIVE,
    ) {}
}
