<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;

class LoggerCleanDirective extends AbstractDirective
{
    public function __construct(
        DirectiveInteractionService $interaction,
        private readonly LogCleanerService $cleaner,
        private readonly LogPathService $pathService,
        ?LaravelBootstrapper $laravelBootstrapper = null,
    ) {
        parent::__construct($interaction, $laravelBootstrapper);
    }

    public function getSignature(): string
    {
        return 'logger-clean {--days=30 : Number of days to keep} {--dry-run : Simulate without deleting} {--verbose : Display detailed information}';
    }

    public function getDescription(): string
    {
        return 'Remove old log files that exceed the retention period';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('log-clean', 'clean-logs');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        $days = (int) ($this->option('days') ?? 30);
        $dryRun = $this->hasOption('dry-run');
        $verbose = $this->hasOption('verbose');
        $cutoffDate = $this->calculateCutoffDate($days);

        $this->displayCurrentStatistics();

        if ($verbose) {
            $this->displayFilesToDelete($cutoffDate);
        }

        if ($dryRun) {
            return $this->handleDryRun($cutoffDate);
        }

        return $this->handleDeletion($cutoffDate);
    }

    private function calculateCutoffDate(int $days): string
    {
        return date('Y-m-d', strtotime("-$days days"));
    }

    private function displayCurrentStatistics(): void
    {
        $stats = $this->cleaner->getStats();

        $this->info('Current statistics:');
        $this->line("  Files: {$stats->totalFiles}");
        $this->line("  Size: {$stats->totalSizeMb} MB");
        $this->line("  Lines: {$stats->totalLines}");
        $this->line("  Range: {$stats->oldestDate} to {$stats->newestDate}");
        $this->line("  Path: {$this->pathService->getConfig()->basePath}");
    }

    private function displayFilesToDelete(string $cutoffDate): void
    {
        $this->newLine();
        $this->line('Files to delete:');

        $files = $this->cleaner->getFilesByDate($cutoffDate);
        foreach ($files as $file) {
            $this->line("  - {$file->date}/{$file->hour} ({$file->size} bytes)");
        }

        if ($files->isEmpty()) {
            $this->line('  (none)');
        }
    }

    private function handleDryRun(string $cutoffDate): ExitCode
    {
        $this->warn('Dry run mode - no files will be deleted');
        $this->info("Would delete files older than {$cutoffDate}");

        $count = $this->cleaner->countFilesToDelete($cutoffDate);
        if ($count > 0) {
            $this->info("Would delete {$count} file(s)");
        }

        return ExitCode::SUCCESS;
    }

    private function handleDeletion(string $cutoffDate): ExitCode
    {
        $count = $this->cleaner->countFilesToDelete($cutoffDate);

        if ($count === 0) {
            $this->info('No files to delete.');

            return ExitCode::SUCCESS;
        }

        if (! $this->confirm("Delete {$count} log(s) older than {$cutoffDate}?")) {
            $this->info('Aborted.');

            return ExitCode::SUCCESS;
        }

        $deletedCount = $this->cleaner->cleanWithCutoff($cutoffDate);
        $this->info("✓ Deleted {$deletedCount} file(s)");

        $this->displayNewStatistics();

        return ExitCode::SUCCESS;
    }

    private function displayNewStatistics(): void
    {
        $newStats = $this->cleaner->getStats();

        $this->newLine();
        $this->info('New statistics:');
        $this->line("  Files: {$newStats->totalFiles}");
        $this->line("  Size: {$newStats->totalSizeMb} MB");
    }

    private function newLine(): void
    {
        $this->line('');
    }
}
