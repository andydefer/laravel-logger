<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

/**
 * CLI directive for cleaning old log files.
 *
 * Removes log files that exceed the configured retention period.
 * Supports dry-run mode for previewing deletions and verbose output
 * for detailed information about which files would be affected.
 *
 * @author Andy Defer
 */
final class LoggerCleanDirective extends AbstractDirective
{
    private const DEFAULT_RETENTION_DAYS = 30;

    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
        private readonly LogCleanerService $cleaner,
        private readonly LogPathService $pathService,
    ) {
        parent::__construct($context, $interaction);
    }

    /**
     * {@inheritdoc}
     */
    public function getSignature(): string
    {
        return 'logger-clean {--days=30 : Number of days to keep} {--dry-run : Simulate without deleting} {--verbose : Display detailed information}';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Remove old log files that exceed the retention period';
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('log-clean');
        $aliases->add('clean-logs');

        return $aliases;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldBootLaravel(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): ExitCode
    {
        $days = $this->getRetentionDays();
        $dryRun = $this->hasOption('dry-run');
        $verbose = $this->hasOption('verbose');
        $cutoffDate = $this->calculateCutoffDate($days);

        $this->displayCurrentStatistics();

        if ($verbose) {
            $this->displayFilesToDelete($cutoffDate->getDate());
        }

        if ($dryRun) {
            return $this->handleDryRun($cutoffDate);
        }

        return $this->handleDeletion($cutoffDate);
    }

    /**
     * Get the retention days from options or use default.
     */
    private function getRetentionDays(): int
    {
        return (int) ($this->option('days') ?? self::DEFAULT_RETENTION_DAYS);
    }

    /**
     * Calculate the cutoff date based on retention days.
     */
    private function calculateCutoffDate(int $days): IsoZuluTime
    {
        $dateString = date('Y-m-d', strtotime("-$days days"));
        return new IsoZuluTime($dateString . 'T23:59:59Z');
    }

    /**
     * Display current log statistics to the user.
     */
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

    /**
     * Display the list of files that would be deleted.
     */
    private function displayFilesToDelete(string $date): void
    {
        $this->newLine();
        $this->line('Files to delete:');

        $files = $this->cleaner->getFilesByDate($date);

        foreach ($files as $file) {
            $this->line("  - {$file->date}/{$file->hour} ({$file->size} bytes)");
        }

        if ($files->isEmpty()) {
            $this->line('  (none)');
        }
    }

    /**
     * Handle dry-run mode (preview only, no actual deletion).
     */
    private function handleDryRun(IsoZuluTime $cutoffDate): ExitCode
    {
        $this->warn('Dry run mode - no files will be deleted');
        $this->info("Would delete files older than {$cutoffDate->getDate()}");

        $count = $this->cleaner->countFilesToDelete($cutoffDate);

        if ($count > 0) {
            $this->info("Would delete {$count} file(s)");
        }

        return ExitCode::SUCCESS;
    }

    /**
     * Handle actual deletion after user confirmation.
     */
    private function handleDeletion(IsoZuluTime $cutoffDate): ExitCode
    {
        $count = $this->cleaner->countFilesToDelete($cutoffDate);

        if ($count === 0) {
            $this->info('No files to delete.');
            return ExitCode::SUCCESS;
        }

        if (!$this->confirm("Delete {$count} log(s) older than {$cutoffDate->getDate()}?")) {
            $this->info('Aborted.');
            return ExitCode::SUCCESS;
        }

        $deletedCount = $this->cleaner->cleanWithCutoff($cutoffDate);
        $this->info("✓ Deleted {$deletedCount} file(s)");

        $this->displayNewStatistics();

        return ExitCode::SUCCESS;
    }

    /**
     * Display updated statistics after deletion.
     */
    private function displayNewStatistics(): void
    {
        $newStats = $this->cleaner->getStats();

        $this->newLine();
        $this->info('New statistics:');
        $this->line("  Files: {$newStats->totalFiles}");
        $this->line("  Size: {$newStats->totalSizeMb} MB");
    }
}
