<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Enums;

use AndyDefer\Records\Traits\Enumable;

enum TestUserStatus
{
    use Enumable;

    case ACTIVE;
    case INACTIVE;
    case SUSPENDED;
}
