<?php

declare(strict_types=1);

namespace AndyDefer\Logger\Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Logger\Directives\LoggerCleanDirective;
use AndyDefer\Logger\Records\LogStatsRecord;
use AndyDefer\Logger\Services\LogCleanerService;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Tests\UnitTestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class LoggerCleanDirectiveTest extends UnitTestCase
{
    private LogCleanerService&MockObject $cleaner;
    private LogPathService&MockObject $pathService;
    private DirectiveInteractionService&MockObject $interaction;
    private LaravelBootstrapper&MockObject $bootstrapper;
    private LoggerCleanDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleaner = $this->createMock(LogCleanerService::class);
        $this->pathService = $this->createMock(LogPathService::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->bootstrapper = $this->createMock(LaravelBootstrapper::class);

        $this->directive = new LoggerCleanDirective(
            $this->interaction,
            $this->cleaner,
            $this->pathService,
            $this->bootstrapper,
        );
    }

    public function test_getSignature_returns_correct_signature(): void
    {
        $signature = $this->directive->getSignature();

        $this->assertStringContainsString('logger-clean', $signature);
        $this->assertStringContainsString('--days=', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('--verbose', $signature);
    }

    public function test_getDescription_returns_correct_description(): void
    {
        $description = $this->directive->getDescription();

        $this->assertStringContainsString('Remove old log files', $description);
    }

    public function test_getAliases_returns_correct_aliases(): void
    {
        $aliases = $this->directive->getAliases();

        $this->assertInstanceOf(StringTypedCollection::class, $aliases);
        $this->assertTrue($aliases->contains('log-clean'));
        $this->assertTrue($aliases->contains('clean-logs'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_shouldBootLaravel_returns_true(): void
    {
        $this->assertTrue($this->directive->shouldBootLaravel());
    }

    public function test_execute_without_options_cleans_logs_and_returns_success(): void
    {
        $stats = $this->createMockStatsRecord(10, 5.2, 1500, '2024-01-01', '2024-01-31');

        $this->pathService->expects($this->once())
            ->method('getBasePath')
            ->willReturn('/test/logs');

        $this->cleaner->expects($this->exactly(2))
            ->method('getStats')
            ->willReturn($stats);

        $this->cleaner->expects($this->once())
            ->method('countFilesToDelete')
            ->willReturn(5);

        $this->interaction->expects($this->atLeastOnce())
            ->method('confirm')
            ->willReturn(true);

        $this->cleaner->expects($this->once())
            ->method('cleanWithCutoff')
            ->willReturn(5);

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_with_dry_run_does_not_delete_files(): void
    {
        $stats = $this->createMockStatsRecord(10, 5.2, 1500, '2024-01-01', '2024-01-31');

        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'dry-run', value: true));
        $this->directive->setOptions($options);

        $this->pathService->expects($this->once())
            ->method('getBasePath')
            ->willReturn('/test/logs');

        $this->cleaner->expects($this->once())
            ->method('getStats')
            ->willReturn($stats);

        $this->cleaner->expects($this->once())
            ->method('countFilesToDelete')
            ->willReturn(5);

        $this->cleaner->expects($this->never())
            ->method('cleanWithCutoff');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_with_verbose_displays_files_to_delete(): void
    {
        $stats = $this->createMockStatsRecord(10, 5.2, 1500, '2024-01-01', '2024-01-31');
        $filesCollection = $this->createMockFileCollection(3);

        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'verbose', value: true));
        $this->directive->setOptions($options);

        $this->pathService->expects($this->once())
            ->method('getBasePath')
            ->willReturn('/test/logs');

        $this->cleaner->expects($this->once())
            ->method('getStats')
            ->willReturn($stats);

        $this->cleaner->expects($this->once())
            ->method('getFilesByDate')
            ->willReturn($filesCollection);

        $this->cleaner->expects($this->once())
            ->method('countFilesToDelete')
            ->willReturn(5);

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_with_custom_days_passes_correct_cutoff_date(): void
    {
        $stats = $this->createMockStatsRecord(10, 5.2, 1500, '2024-01-01', '2024-01-31');

        $options = new ParameterCollection();
        $options->add(new ParameterRecord(name: 'days', value: '60'));
        $this->directive->setOptions($options);

        $this->pathService->expects($this->once())
            ->method('getBasePath')
            ->willReturn('/test/logs');

        // ⚠️ getStats est appelé 2 fois (displayCurrentStatistics + displayNewStatistics)
        $this->cleaner->expects($this->exactly(2))
            ->method('getStats')
            ->willReturn($stats);

        $this->cleaner->expects($this->once())
            ->method('countFilesToDelete')
            ->willReturn(5);

        $this->interaction->expects($this->atLeastOnce())
            ->method('confirm')
            ->willReturn(true);

        $this->cleaner->expects($this->once())
            ->method('cleanWithCutoff')
            ->willReturn(5);

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_when_no_files_to_delete_skips_confirmation(): void
    {
        $stats = $this->createMockStatsRecord(0, 0, 0, null, null);

        $this->pathService->expects($this->once())
            ->method('getBasePath')
            ->willReturn('/test/logs');

        $this->cleaner->expects($this->once())
            ->method('getStats')
            ->willReturn($stats);

        $this->cleaner->expects($this->once())
            ->method('countFilesToDelete')
            ->willReturn(0);

        $this->interaction->expects($this->never())
            ->method('confirm');

        $this->cleaner->expects($this->never())
            ->method('cleanWithCutoff');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_when_user_cancels_does_not_delete(): void
    {
        $stats = $this->createMockStatsRecord(10, 5.2, 1500, '2024-01-01', '2024-01-31');

        $this->pathService->expects($this->once())
            ->method('getBasePath')
            ->willReturn('/test/logs');

        $this->cleaner->expects($this->once())
            ->method('getStats')
            ->willReturn($stats);

        $this->cleaner->expects($this->once())
            ->method('countFilesToDelete')
            ->willReturn(5);

        $this->interaction->expects($this->atLeastOnce())
            ->method('confirm')
            ->willReturn(false);

        $this->cleaner->expects($this->never())
            ->method('cleanWithCutoff');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    private function createMockStatsRecord(int $totalFiles, float $totalSizeMb, int $totalLines, ?string $oldestDate, ?string $newestDate): LogStatsRecord
    {
        return new LogStatsRecord(
            totalFiles: $totalFiles,
            totalDays: $totalFiles > 0 ? 1 : 0,
            totalSizeBytes: (int) ($totalSizeMb * 1024 * 1024),
            totalSizeMb: $totalSizeMb,
            totalLines: $totalLines,
            oldestDate: $oldestDate,
            newestDate: $newestDate,
        );
    }

    private function createMockFileCollection(int $count): TypedCollection
    {
        $collection = new TypedCollection(\stdClass::class);

        for ($i = 1; $i <= $count; $i++) {
            $file = new \stdClass();
            $file->date = '2024-01-0' . $i;
            $file->hour = sprintf('%02d', $i);
            $file->size = $i * 1024;
            $collection->add($file);
        }

        return $collection;
    }
}
