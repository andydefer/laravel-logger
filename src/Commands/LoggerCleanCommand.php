<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Commands;

use AndyDefer\Logger\Services\LogCleanerService;
use Illuminate\Console\Command;

/**
 * Artisan command to clean old log files based on retention policy.
 *
 * This command removes log files older than a specified number of days.
 * It supports dry-run mode for previewing deletions and verbose output
 * for detailed information about which files would be affected.
 */
final class LoggerCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logger:clean 
                            {--days=30 : Number of days to keep (older files are deleted)} 
                            {--dry-run : Simulate the operation without actually deleting any files}
                            {--verbose : Display detailed information about files to be deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old log files that exceed the retention period';

    /**
     * Execute the console command.
     *
     * @param LogCleanerService $cleaner Service responsible for log file management
     *
     * @return int Command exit code (0 for success)
     */
    public function handle(LogCleanerService $cleaner): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $verbose = (bool) $this->option('verbose');
        $cutoffDate = $this->calculateCutoffDate($days);

        $this->displayCurrentStatistics($cleaner);

        if ($verbose) {
            $this->displayFilesToDelete($cleaner, $cutoffDate);
        }

        if ($dryRun) {
            return $this->handleDryRun($cutoffDate);
        }

        return $this->handleDeletion($cleaner, $cutoffDate);
    }

    /**
     * Calculate the cutoff date based on retention days.
     *
     * @param int $days Number of days to keep
     *
     * @return string Cutoff date in Y-m-d format
     */
    private function calculateCutoffDate(int $days): string
    {
        return date('Y-m-d', strtotime("-$days days"));
    }

    /**
     * Display current log statistics to the user.
     *
     * @param LogCleanerService $cleaner Service providing log statistics
     */
    private function displayCurrentStatistics(LogCleanerService $cleaner): void
    {
        $stats = $cleaner->getStats();

        $this->info('Current statistics:');
        $this->line("  Files: {$stats['total_files']}");
        $this->line("  Size: {$stats['total_size_mb']} MB");
        $this->line("  Lines: {$stats['total_lines']}");
        $this->line("  Range: {$stats['oldest_date']} to {$stats['newest_date']}");
    }

    /**
     * Display detailed list of files that would be deleted.
     *
     * @param LogCleanerService $cleaner    Service providing file information
     * @param string            $cutoffDate Cutoff date for deletion
     */
    private function displayFilesToDelete(LogCleanerService $cleaner, string $cutoffDate): void
    {
        $this->newLine();
        $this->line('Files to delete:');

        $files = $cleaner->getFilesByDate($cutoffDate);
        foreach ($files as $file) {
            $this->line("  - {$file->date}/{$file->hour} ({$file->size} bytes)");
        }
    }

    /**
     * Handle dry-run mode: preview what would be deleted.
     *
     * @param string $cutoffDate Cutoff date for deletion
     *
     * @return int Command exit code (0 for success)
     */
    private function handleDryRun(string $cutoffDate): int
    {
        $this->warn('Dry run mode - no files will be deleted');
        $this->info("Would delete files older than {$cutoffDate}");

        return self::SUCCESS;
    }

    /**
     * Handle actual deletion after user confirmation.
     *
     * @param LogCleanerService $cleaner    Service responsible for deletion
     * @param string            $cutoffDate Cutoff date for deletion
     *
     * @return int Command exit code (0 for success)
     */
    private function handleDeletion(LogCleanerService $cleaner, string $cutoffDate): int
    {
        if (!$this->confirm("Delete logs older than {$cutoffDate}?")) {
            return self::SUCCESS;
        }

        $deletedCount = $cleaner->cleanWithCutoff($cutoffDate);
        $this->info("Deleted {$deletedCount} files");

        $this->displayNewStatistics($cleaner);

        return self::SUCCESS;
    }

    /**
     * Display updated statistics after deletion.
     *
     * @param LogCleanerService $cleaner Service providing updated statistics
     */
    private function displayNewStatistics(LogCleanerService $cleaner): void
    {
        $newStats = $cleaner->getStats();

        $this->newLine();
        $this->info('New statistics:');
        $this->line("  Files: {$newStats['total_files']}");
        $this->line("  Size: {$newStats['total_size_mb']} MB");
    }
}
