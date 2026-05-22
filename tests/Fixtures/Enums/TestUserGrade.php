<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Fixtures\Enums;

use AndyDefer\Records\Traits\Enumable;

enum TestUserGrade: int
{
    use Enumable;

    case BRONZE = 1;
    case SILVER = 2;
    case GOLD = 3;
    case PLATINUM = 4;
}
