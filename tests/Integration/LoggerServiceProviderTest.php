<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Integration;

use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Directives\LoggerCleanDirective;
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tests\IntegrationTestCase;

final class LoggerServiceProviderTest extends IntegrationTestCase
{
    public function test_config_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LoggerConfig::class);
        $second = $this->app->make(LoggerConfig::class);

        $this->assertSame($first, $second);
    }

    public function test_config_uses_values_from_environment(): void
    {
        $config = $this->app->make(LoggerConfig::class);

        $this->assertSame('/tmp/logger_tests', $config->basePath);
        $this->assertSame(7, $config->retentionDays);
    }

    public function test_log_path_service_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LogPathService::class);
        $second = $this->app->make(LogPathService::class);

        $this->assertSame($first, $second);
    }

    public function test_log_serializer_service_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LogSerializerService::class);
        $second = $this->app->make(LogSerializerService::class);

        $this->assertSame($first, $second);
    }

    public function test_log_cleaner_service_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LogCleanerService::class);
        $second = $this->app->make(LogCleanerService::class);

        $this->assertSame($first, $second);
    }

    public function test_write_log_task_is_registered_as_singleton(): void
    {
        $first = $this->app->make(WriteLogTask::class);
        $second = $this->app->make(WriteLogTask::class);

        $this->assertSame($first, $second);
    }

    public function test_query_logs_task_is_registered_as_singleton(): void
    {
        $first = $this->app->make(QueryLogsTask::class);
        $second = $this->app->make(QueryLogsTask::class);

        $this->assertSame($first, $second);
    }

    public function test_stream_logs_task_is_registered_as_singleton(): void
    {
        $first = $this->app->make(StreamLogsTask::class);
        $second = $this->app->make(StreamLogsTask::class);

        $this->assertSame($first, $second);
    }

    public function test_logger_interface_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LoggerInterface::class);
        $second = $this->app->make(LoggerInterface::class);

        $this->assertSame($first, $second);
        $this->assertInstanceOf(Logger::class, $first);
    }

    public function test_logger_clean_directive_is_registered_as_singleton(): void
    {
        $first = $this->app->make(LoggerCleanDirective::class);
        $second = $this->app->make(LoggerCleanDirective::class);

        $this->assertSame($first, $second);
        $this->assertInstanceOf(LoggerCleanDirective::class, $first);
    }

    public function test_logger_clean_directive_has_correct_configuration(): void
    {
        $directive = $this->app->make(LoggerCleanDirective::class);

        // Vérifier la signature
        $this->assertStringContainsString('logger-clean', $directive->getSignature());
        $this->assertStringContainsString('--days', $directive->getSignature());
        $this->assertStringContainsString('--dry-run', $directive->getSignature());
        $this->assertStringContainsString('--verbose', $directive->getSignature());

        // Vérifier la description
        $this->assertStringContainsString('Remove old log files', $directive->getDescription());

        // Vérifier les alias
        $aliases = $directive->getAliases();
        $this->assertTrue($aliases->contains('log-clean'));
        $this->assertTrue($aliases->contains('clean-logs'));

        // Vérifier qu'elle demande Laravel
        $this->assertTrue($directive->shouldBootLaravel());
    }

    public function test_can_override_config_values(): void
    {
        // Modifier la configuration
        $this->app['config']->set('logger.path', '/custom/path');
        $this->app['config']->set('logger.retention_days', 99);

        // Recréer le service
        $this->app->forgetInstance(LoggerConfig::class);
        $config = $this->app->make(LoggerConfig::class);

        $this->assertSame('/custom/path', $config->basePath);
        $this->assertSame(99, $config->retentionDays);
    }
}
