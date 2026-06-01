<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\Records\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\Records\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Records\Tests\Fixtures\Enums\TestUserStatus;

/**
 * Test full user record for unit tests.
 */
final class TestFullUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly TestUserStatus $status = TestUserStatus::ACTIVE,
        public readonly TestUserRole $role = TestUserRole::USER,
        public readonly TestUserGrade $grade = TestUserGrade::BRONZE,
        public readonly ?string $emailVerifiedAt = null,
        public readonly TypedCollection $tags = new TypedCollection('string'),
        public readonly TypedCollection $products = new TypedCollection(TestProductRecord::class),
        public readonly ?TestProductRecord $featuredProduct = null,
    ) {}
}
