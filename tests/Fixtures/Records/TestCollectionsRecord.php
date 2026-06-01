<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;

/**
 * Fixture record for testing collections of collections.
 *
 * Used to test that AbstractRecord can handle properties of type
 * TypedCollection where the collection itself contains collections.
 */
final class TestCollectionsRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedCollection $stringCollections = new TypedCollection(TypedCollection::class),
        public readonly TypedCollection $intCollections = new TypedCollection(TypedCollection::class),
    ) {}
}
