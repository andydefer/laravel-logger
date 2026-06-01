<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Records\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\Records\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Records\Tests\Fixtures\Enums\TestUserStatus;

/**
 * Filters record for TestUser repository operations.
 *
 * Used for findBy, count, exists, deleteBulk operations.
 */
final class TestUserFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?TestUserStatus $status = null,
        public readonly ?TestUserRole $role = null,
        public readonly ?TestUserGrade $grade = null,
    ) {}
}
