<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Integration;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Strategies\TemporalPathStrategy;
use AndyDefer\Logger\Contracts\LoggerConfigInterface;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\LoggerService;
use AndyDefer\Logger\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Support\Facades\Config;

final class LoggerServiceProviderTest extends IntegrationTestCase
{
    public function test_config_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LoggerConfigInterface::class);
        $second = $this->app->make(LoggerConfigInterface::class);

        $this->assertSame($first, $second);
    }

    public function test_config_uses_values_from_environment(): void
    {
        Config::set('logger.path', '/tmp/logger_tests');
        Config::set('logger.retention_days', 7);
        Config::set('logger.buffer_size', null);

        $config = $this->app->make(LoggerConfigInterface::class);

        $this->assertSame('/tmp/logger_tests', $config->basePath());
        $this->assertSame(7, $config->retentionDays());
    }

    public function test_jsonl_context_is_registered_as_singleton(): void
    {
        $first = $this->app->make(JsonlContext::class);
        $second = $this->app->make(JsonlContext::class);

        $this->assertSame($first, $second);
    }

    public function test_file_system_service_is_registered_as_singleton(): void
    {
        $first = $this->app->make(FileSystemService::class);
        $second = $this->app->make(FileSystemService::class);

        $this->assertSame($first, $second);
    }

    public function test_temporal_path_strategy_is_registered_as_singleton(): void
    {
        $first = $this->app->make(TemporalPathStrategy::class);
        $second = $this->app->make(TemporalPathStrategy::class);

        $this->assertSame($first, $second);
    }

    public function test_jsonl_service_is_registered_as_singleton(): void
    {
        $first = $this->app->make(JsonlService::class);
        $second = $this->app->make(JsonlService::class);

        $this->assertSame($first, $second);
    }

    public function test_hydration_service_is_registered_as_singleton(): void
    {
        $first = $this->app->make(HydrationService::class);
        $second = $this->app->make(HydrationService::class);

        $this->assertSame($first, $second);
    }

    public function test_logger_interface_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LoggerInterface::class);
        $second = $this->app->make(LoggerInterface::class);

        $this->assertSame($first, $second);
        $this->assertInstanceOf(LoggerService::class, $first);
    }

    public function test_logger_service_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LoggerService::class);
        $second = $this->app->make(LoggerService::class);

        $this->assertSame($first, $second);
    }

    public function test_can_override_config_values(): void
    {
        Config::set('logger.path', '/custom/path');
        Config::set('logger.retention_days', 99);
        Config::set('logger.buffer_size', 50);

        $this->app->forgetInstance(LoggerConfigInterface::class);
        $config = $this->app->make(LoggerConfigInterface::class);

        $this->assertSame('/custom/path', $config->basePath());
        $this->assertSame(99, $config->retentionDays());
        $this->assertSame(50, $config->bufferSize());
        $this->assertTrue($config->isBufferEnabled());
    }

    public function test_logger_service_dependencies_are_correctly_injected(): void
    {
        $logger = $this->app->make(LoggerService::class);

        $this->assertInstanceOf(LoggerService::class, $logger);
    }
}
