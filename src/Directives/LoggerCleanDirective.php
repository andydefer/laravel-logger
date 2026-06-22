<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\Logger\Contracts\LoggerConfigInterface;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

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
        $aliases->add('log-clean');
        $aliases->add('clean-logs');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        $laravel = $this->getLaravel();
        $jsonlService = $laravel->make(JsonlService::class);
        $config = $laravel->make(LoggerConfigInterface::class);
        $basePath = $config->basePath();

        $days = (int) ($this->option('days') ?? 30);
        $dryRun = $this->hasOption('dry-run');
        $verbose = $this->hasOption('verbose');

        // Afficher les statistiques actuelles
        if ($verbose) {
            $this->displayCurrentStatistics($jsonlService, $basePath);
        }

        // Mode dry-run : simuler sans supprimer
        if ($dryRun) {
            return $this->handleDryRun($jsonlService, $basePath, $days);
        }

        // Mode réel avec confirmation
        return $this->handleDeletion($jsonlService, $basePath, $days);
    }

    private function displayCurrentStatistics(JsonlService $jsonlService, string $basePath): void
    {
        $filesToDelete = $jsonlService->dryRun($basePath, fn ($file) => true);

        $totalFiles = count($filesToDelete);
        $totalSize = 0;
        $dates = [];

        foreach ($filesToDelete as $file) {
            $totalSize += filesize($file);
            $pathParts = explode(DIRECTORY_SEPARATOR, $file);
            $date = $pathParts[count($pathParts) - 2] ?? '';
            $dates[] = $date;
        }

        $totalSizeMb = round($totalSize / 1024 / 1024, 2);
        $oldestDate = ! empty($dates) ? min($dates) : 'N/A';
        $newestDate = ! empty($dates) ? max($dates) : 'N/A';

        $this->info('Current statistics:');
        $this->line("  Files: {$totalFiles}");
        $this->line("  Size: {$totalSizeMb} MB");
        $this->line("  Range: {$oldestDate} to {$newestDate}");
        $this->line("  Path: {$basePath}");
    }

    private function handleDryRun(JsonlService $jsonlService, string $basePath, int $days): ExitCode
    {
        $cutoffDateTime = new DateTimeVO(date('Y-m-d', strtotime("-$days days")).'T00:00:00Z');
        $filesToDelete = $jsonlService->dryRun($basePath, function ($file) use ($cutoffDateTime) {
            $fileModifiedTime = filemtime($file);

            return $fileModifiedTime < $cutoffDateTime->toTimestamp();
        });

        $this->warn('Dry run mode - no files will be deleted');
        $this->info("Would delete files older than {$cutoffDateTime->toDateString()}");

        if ($this->hasOption('verbose')) {
            $this->newLine();
            $this->line('Files to delete:');

            foreach ($filesToDelete as $file) {
                $sizeKb = round(filesize($file) / 1024, 2);
                $fileName = basename($file);
                $this->line("  - {$fileName} ({$sizeKb} KB)");
            }
        }

        $count = count($filesToDelete);
        if ($count > 0) {
            $this->info("Would delete {$count} file(s)");
        } else {
            $this->info('No files to delete.');
        }

        return ExitCode::SUCCESS;
    }

    private function handleDeletion(JsonlService $jsonlService, string $basePath, int $days): ExitCode
    {
        $cutoffDateTime = new DateTimeVO(date('Y-m-d', strtotime("-$days days")).'T00:00:00Z');
        $filesToDelete = $jsonlService->dryRun($basePath, function ($file) use ($cutoffDateTime) {
            $fileModifiedTime = filemtime($file);

            return $fileModifiedTime < $cutoffDateTime->toTimestamp();
        });

        $count = count($filesToDelete);

        if ($count === 0) {
            $this->info('No files to delete.');

            return ExitCode::SUCCESS;
        }

        if (! $this->confirm("Delete {$count} log(s) older than {$cutoffDateTime->toDateString()}?")) {
            $this->info('Aborted.');

            return ExitCode::SUCCESS;
        }

        $deletedCount = $jsonlService->cleanOlderThan($days, $basePath);
        $this->info("✓ Deleted {$deletedCount} file(s)");

        return ExitCode::SUCCESS;
    }
}
