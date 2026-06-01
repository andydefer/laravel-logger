<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Logger\Tests\Fixtures\Enums\TestUserStatus;

final class TestUserUpdateRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?TestUserStatus $status = null,
    ) {}
}
