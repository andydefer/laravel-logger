<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Enums;

use AndyDefer\Logger\Tests\TestCase;
use AndyDefer\Logger\Enums\LogLevel;

final class LogLevelTest extends TestCase
{
    public function test_values_returns_all_level_values(): void
    {
        $values = LogLevel::values();

        $this->assertSame(['debug', 'info', 'warning', 'error'], $values);
    }

    public function test_get_label_returns_correct_label_for_each_level(): void
    {
        $this->assertSame('Debug', LogLevel::DEBUG->getLabel());
        $this->assertSame('Info', LogLevel::INFO->getLabel());
        $this->assertSame('Warning', LogLevel::WARNING->getLabel());
        $this->assertSame('Error', LogLevel::ERROR->getLabel());
    }

    public function test_is_debug_returns_true_only_for_debug_level(): void
    {
        $this->assertTrue(LogLevel::DEBUG->isDebug());
        $this->assertFalse(LogLevel::INFO->isDebug());
        $this->assertFalse(LogLevel::WARNING->isDebug());
        $this->assertFalse(LogLevel::ERROR->isDebug());
    }

    public function test_is_info_returns_true_only_for_info_level(): void
    {
        $this->assertFalse(LogLevel::DEBUG->isInfo());
        $this->assertTrue(LogLevel::INFO->isInfo());
        $this->assertFalse(LogLevel::WARNING->isInfo());
        $this->assertFalse(LogLevel::ERROR->isInfo());
    }

    public function test_is_warning_returns_true_only_for_warning_level(): void
    {
        $this->assertFalse(LogLevel::DEBUG->isWarning());
        $this->assertFalse(LogLevel::INFO->isWarning());
        $this->assertTrue(LogLevel::WARNING->isWarning());
        $this->assertFalse(LogLevel::ERROR->isWarning());
    }

    public function test_is_error_returns_true_only_for_error_level(): void
    {
        $this->assertFalse(LogLevel::DEBUG->isError());
        $this->assertFalse(LogLevel::INFO->isError());
        $this->assertFalse(LogLevel::WARNING->isError());
        $this->assertTrue(LogLevel::ERROR->isError());
    }

    public function test_from_value_returns_correct_enum_for_valid_value(): void
    {
        $this->assertSame(LogLevel::DEBUG, LogLevel::fromValue('debug'));
        $this->assertSame(LogLevel::INFO, LogLevel::fromValue('info'));
        $this->assertSame(LogLevel::WARNING, LogLevel::fromValue('warning'));
        $this->assertSame(LogLevel::ERROR, LogLevel::fromValue('error'));
    }

    public function test_from_value_returns_null_for_invalid_value(): void
    {
        $this->assertNull(LogLevel::fromValue('invalid'));
    }

    public function test_is_valid_returns_true_for_valid_values(): void
    {
        $this->assertTrue(LogLevel::isValid('debug'));
        $this->assertTrue(LogLevel::isValid('info'));
        $this->assertTrue(LogLevel::isValid('warning'));
        $this->assertTrue(LogLevel::isValid('error'));
    }

    public function test_is_valid_returns_false_for_invalid_values(): void
    {
        $this->assertFalse(LogLevel::isValid('invalid'));
        $this->assertFalse(LogLevel::isValid('INFO'));
    }
}
