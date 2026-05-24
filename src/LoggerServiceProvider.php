<?php

declare(strict_types=1);

namespace AndyDefer\Logger;

use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Logger\Commands\LoggerCleanCommand;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Directives\LoggerCleanDirective;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Services\LogSerializerService;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Support\ServiceProvider;

class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config
        $this->app->singleton(LoggerConfig::class, function ($app) {
            $config = LoggerConfig::default();

            if ($app->has('config') && $app->config->has('logger')) {
                $appConfig = $app->config->get('logger', []);

                if (isset($appConfig['path'])) {
                    $config = $config->withBasePath($appConfig['path']);
                }
                if (isset($appConfig['retention_days'])) {
                    $config = $config->withRetentionDays($appConfig['retention_days']);
                }
            }

            return $config;
        });

        // Services
        $this->app->singleton(LogPathService::class, function ($app) {
            return new LogPathService($app->make(LoggerConfig::class));
        });

        $this->app->singleton(LogSerializerService::class, function ($app) {
            return new LogSerializerService;
        });

        $this->app->singleton(LogCleanerService::class, function ($app) {
            return new LogCleanerService($app->make(LogPathService::class));
        });

        // Tasks
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

        // Logger singleton
        $this->app->singleton(LoggerInterface::class, function ($app) {
            return new Logger(
                $app->make(WriteLogTask::class),
                $app->make(QueryLogsTask::class),
                $app->make(StreamLogsTask::class),
            );
        });

        // 🔥 Enregistrer la directive LoggerCleanDirective
        $this->registerDirective();
    }

    private function registerDirective(): void
    {
        // Enregistrer la directive comme singleton
        $this->app->singleton(LoggerCleanDirective::class, function ($app) {
            return new LoggerCleanDirective(
                $app->make(LogCleanerService::class),
                $app->make(LogPathService::class),
            );
        });

        // Enregistrer dans le DirectiveRegistrar si disponible
        $this->app->afterResolving(DirectiveRegistrarInterface::class, function ($registrar) {
            $classes = new StringTypedCollection();
            $classes->add(LoggerCleanDirective::class);
            $registrar->register($classes);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/logger.php' => config_path('logger.php'),
        ], 'logger-config');

        // Nettoyage automatique à la fin de la requête
        $this->app->terminating(function () {
            $cleaner = $this->app->make(LogCleanerService::class);
            $config = $this->app->make(LoggerConfig::class);
            $cutoffDate = date('Y-m-d', strtotime('-' . $config->retentionDays . ' days'));
            $cleaner->cleanWithCutoff($cutoffDate);
        });
    }
}
