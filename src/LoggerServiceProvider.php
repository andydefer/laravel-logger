<?php

declare(strict_types=1);

namespace AndyDefer\Logger;

use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Directives\LoggerCleanDirective;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;
use AndyDefer\Logger\ValueObjects\LoggerConfig;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Laravel Logger package.
 *
 * Registers all logger services, tasks, and directives in the container.
 *
 * @author Andy Defer
 */
final class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerServices();
        $this->registerTasks();
        $this->registerLogger();
        $this->registerDirective();
    }

    private function registerConfig(): void
    {
        $this->app->singleton(LoggerConfig::class, function ($app) {
            $configData = [
                'basePath' => storage_path('logs/structured'),
                'retentionDays' => 30,
            ];

            if ($app->has('config') && $app['config']->has('logger')) {
                $appConfig = $app['config']->get('logger', []);

                if (isset($appConfig['path'])) {
                    $configData['basePath'] = $appConfig['path'];
                }
                if (isset($appConfig['retention_days'])) {
                    $configData['retentionDays'] = $appConfig['retention_days'];
                }
            }

            return LoggerConfig::from($configData);
        });
    }

    private function registerServices(): void
    {
        $this->app->singleton(LogPathService::class, function ($app) {
            return new LogPathService($app->make(LoggerConfig::class));
        });

        $this->app->singleton(LogSerializerService::class, function () {
            return new LogSerializerService;
        });

        $this->app->singleton(LogCleanerService::class, function ($app) {
            return new LogCleanerService($app->make(LogPathService::class));
        });
    }

    private function registerTasks(): void
    {
        $this->app->singleton(WriteLogTask::class, function ($app) {
            return new WriteLogTask(
                $app->make(LogPathService::class),
                $app->make(LogSerializerService::class),
            );
        });

        $this->app->singleton(QueryLogsTask::class, function ($app) {
            return new QueryLogsTask(
                $app->make(LogPathService::class),
                $app->make(LogSerializerService::class),
            );
        });

        $this->app->singleton(StreamLogsTask::class, function ($app) {
            return new StreamLogsTask(
                $app->make(LogPathService::class),
                $app->make(LogSerializerService::class),
            );
        });
    }

    private function registerLogger(): void
    {
        $this->app->singleton(Logger::class, function ($app) {
            return new Logger(
                $app->make(WriteLogTask::class),
                $app->make(QueryLogsTask::class),
                $app->make(StreamLogsTask::class),
            );
        });

        $this->app->singleton(LoggerInterface::class, function ($app) {
            return $app->make(Logger::class);
        });
    }

    private function registerDirective(): void
    {
        $this->app->singleton(LoggerCleanDirective::class, function ($app) {
            return new LoggerCleanDirective(
                $app->make(DirectiveContext::class),
                $app->make(DirectiveInteractionService::class),
                $app->make(LogCleanerService::class),
                $app->make(LogPathService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/logger.php' => config_path('logger.php'),
        ], 'logger-config');

        $this->registerAutoCleanup();
    }

    private function registerAutoCleanup(): void
    {
        $this->app->terminating(function () {
            $cleaner = $this->app->make(LogCleanerService::class);
            $config = $this->app->make(LoggerConfig::class);

            // Calculer la date de coupure
            $cutoffDateString = date('Y-m-d', strtotime('-' . $config->retentionDays . ' days'));

            // Convertir la string en IsoZuluTime
            // On ajoute 'T00:00:00Z' pour avoir un timestamp complet valide
            $cutoffDate = new IsoZuluTime($cutoffDateString . 'T00:00:00Z');

            $cleaner->cleanWithCutoff($cutoffDate);
        });
    }
}
