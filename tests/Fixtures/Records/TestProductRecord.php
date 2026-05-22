<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Records;

use AndyDefer\Records\AbstractRecord;

final class TestProductRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?int $price = null,
        /**
         * @param  array<string, mixed>|null  $metadata  JSON metadata (key-value pairs)
         */
        public readonly ?array $metadata = null,
        public readonly ?bool $isFeatured = null,
        public readonly ?int $productableId = null,
        public readonly ?string $productableType = null,
    ) {}
}
