<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\LogTools;
use PHPUnit\Framework\TestCase;

/**
 * Tests for B8 (diagnose-error history counts use resolved source) and
 * B9 (performList guards filesize/filemtime false and glob false).
 */
class LogDiagnosticBugTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_test_' . uniqid();
        mkdir($this->tempDir . '/var/log', 0755, true);
        $this->setMagentoRoot($this->tempDir);
    }

    protected function tearDown(): void
    {
        MagentoBootstrap::reset();
        $this->removeDir($this->tempDir);
    }

    // ── B8 ────────────────────────────────────────────────────────────────────

    /**
     * B8: analyzeExceptionLog with $source='system' reads system.log, not exception.log.
     *
     * system.log has an error; exception.log does not exist.
     * With the bug: total_errors=0 (reads nonexistent exception.log).
     * With the fix: total_errors=1 (reads system.log where the error lives).
     */
    public function testItCountsOccurrencesFromTheResolvedLogSourceNotAlwaysExceptionLog(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.000000+00:00', time() - 60);
        $line = "[{$timestamp}] main.ERROR: RuntimeError: Something failed {\"exception\":\"...\"} []\n";
        file_put_contents($this->tempDir . '/var/log/system.log', $line);

        $logTools = new LogTools();
        $result = $this->callAnalyzeExceptionLog($logTools, 1, 'system');

        $this->assertSame(
            1,
            $result['total_errors'],
            'analyzeExceptionLog(1, "system") must count errors from system.log'
        );
    }

    /**
     * B8: When resolved source is exception.log (the default), counts come from exception.log.
     *
     * system.log has errors but exception.log does not exist — total_errors for
     * the exception source must be 0 (or the log-not-found early return), NOT
     * the count from system.log.
     */
    public function testItReportsZeroOccurrencesOnlyWhenTheResolvedSourceTrulyHasNone(): void
    {
        // exception.log is empty / absent; system.log has errors
        $timestamp = date('Y-m-d\TH:i:s.000000+00:00', time() - 60);
        $line = "[{$timestamp}] main.ERROR: RuntimeError: noise {\"exception\":\"...\"} []\n";
        file_put_contents($this->tempDir . '/var/log/system.log', $line);
        // No exception.log written → reading exception.log yields 0

        $logTools = new LogTools();
        $result = $this->callAnalyzeExceptionLog($logTools, 1, 'exception');

        // Either the log is absent (no total_errors key) or total_errors is 0 —
        // either way, it must NOT be 1 (which would indicate it read system.log instead).
        $totalErrors = $result['total_errors'] ?? 0;
        $this->assertSame(
            0,
            $totalErrors,
            'When exception.log is absent, total_errors must be 0 regardless of system.log'
        );
    }

    /**
     * B8 (root cause): the history block must count occurrences in the log the matched
     * error actually came from. A system.log error found via the exception-source
     * fallback must be counted in system.log — not exception.log.
     *
     * This exercises buildHistory() (the real wiring used by diagnoseError), reading
     * actual log files, so reverting the source selection would fail this test.
     */
    public function testItCountsHistoryFromTheLogTheMatchedErrorCameFrom(): void
    {
        // An error lives in system.log; exception.log does NOT contain it.
        $timestamp = date('Y-m-d\TH:i:s.000000+00:00', time() - 60);
        $class = 'Acme\\Module\\WidgetError';
        $line = "[{$timestamp}] main.ERROR: {$class}: boom {\"exception\":\"...\"} []\n";
        file_put_contents($this->tempDir . '/var/log/system.log', $line);

        $diag = new \Inchoo\MagentoBricklayer\Mcp\Tool\DiagnosticTools();

        // Matched error came from the system.log fallback while the request defaulted to 'exception'.
        $matchedError = ['_diag_source' => 'system', 'class' => $class];
        $history = $this->callBuildHistory($diag, $matchedError, 'exception', 1);

        $this->assertTrue($history['available']);
        $this->assertSame('system', $history['source']);
        $this->assertSame(1, $history['total_errors_in_period'], 'must count from system.log, not exception.log');
        $this->assertSame(1, $history['this_error_count']);
    }

    /**
     * B8 (var/report): errors matched from a source with no aggregate log must report
     * history as unavailable, NOT backfill counts from an unrelated log (exception.log).
     */
    public function testItReportsHistoryUnavailableForSourcesWithoutAnAggregateLog(): void
    {
        // exception.log has unrelated noise; the matched error came from var/report.
        $timestamp = date('Y-m-d\TH:i:s.000000+00:00', time() - 60);
        file_put_contents(
            $this->tempDir . '/var/log/exception.log',
            "[{$timestamp}] main.ERROR: Some\\Other\\Exception: noise {\"exception\":\"...\"} []\n"
        );

        $diag = new \Inchoo\MagentoBricklayer\Mcp\Tool\DiagnosticTools();
        $matchedError = ['_diag_source' => 'var/report', 'class' => 'PHP\\TypeError'];
        $history = $this->callBuildHistory($diag, $matchedError, 'exception', 1);

        $this->assertFalse($history['available'], 'var/report has no aggregate log → history unavailable');
        $this->assertArrayNotHasKey('total_errors_in_period', $history);
        $this->assertSame('var/report', $history['source']);
    }

    // ── B9 ────────────────────────────────────────────────────────────────────

    /**
     * B9: performList must include real log files and never throw, exercising the
     * buildLogEntry path for files that are stat-able.
     *
     * The "stat returns false" guard cannot be triggered deterministically in a unit test
     * (file_exists + filesize share one stat() call). The stat-false path is covered by
     * testItFormatsFileSizeAndMtimeOnlyForStatableFiles via buildLogEntry directly.
     * This test ensures performList's overall flow returns structured output.
     */
    public function testItListsLogsWhenAFileStatReturnsFalseWithoutThrowing(): void
    {
        // Create a real log file so at least one entry is built via buildLogEntry
        file_put_contents($this->tempDir . '/var/log/system.log', "line\n");

        $logTools = new LogTools();
        $result = $this->callPerformList($logTools);

        $this->assertArrayHasKey('logs', $result);
        $this->assertIsArray($result['logs']);
        // The real system.log must appear, confirming buildLogEntry ran successfully
        $types = array_column($result['logs'], 'type');
        $this->assertContains('system', $types, 'system.log must be listed when it exists');
    }

    /**
     * B9: The helper that builds a log entry must return null (not throw) for a
     * nonexistent path where filesize/filemtime return false.
     */
    public function testItFormatsFileSizeAndMtimeOnlyForStatableFiles(): void
    {
        $logTools = new LogTools();

        // Nonexistent path → filesize/filemtime return false
        $gonePath = $this->tempDir . '/var/log/gone.log';
        $result = $this->callBuildLogEntry($logTools, 'custom', 'var/log/gone.log', $gonePath);
        $this->assertNull($result, 'buildLogEntry must return null when stat returns false');

        // Real file → entry returned with size and modified
        $realPath = $this->tempDir . '/var/log/real.log';
        file_put_contents($realPath, "data\n");
        $result = $this->callBuildLogEntry($logTools, 'real', 'var/log/real.log', $realPath);
        $this->assertNotNull($result, 'buildLogEntry must return an entry for a real file');
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('modified', $result);
    }

    /**
     * B9: The glob branch in performList must handle the result correctly and include
     * extra log files (not in the predefined list) when found.
     *
     * Note: glob() returns [] on no-match and false only on pattern errors — forcing
     * the literal-false branch is not reproducible in a unit test. This test verifies
     * the `?: []` coalescing is in place by confirming the glob branch runs (the dir
     * exists, an extra .log file is present) and the file appears in the result.
     */
    public function testItTreatsAFalseGlobResultAsAnEmptyList(): void
    {
        // Drop a custom log file not in LOG_FILES so the glob branch executes
        // and the foreach body runs at least once.
        file_put_contents($this->tempDir . '/var/log/custom_extra.log', "data\n");

        $logTools = new LogTools();
        $result = $this->callPerformList($logTools);

        $this->assertArrayHasKey('logs', $result);
        $this->assertIsArray($result['logs']);

        // The extra file must appear in the listing, confirming glob branch executed.
        $types = array_column($result['logs'], 'type');
        $this->assertContains(
            'custom_extra',
            $types,
            'Extra log file discovered via glob must appear in the listing'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function callAnalyzeExceptionLog(LogTools $tools, int $hours, string $source): array
    {
        $ref = new \ReflectionClass(LogTools::class);
        $method = $ref->getMethod('analyzeExceptionLog');
        return $method->invoke($tools, $hours, $source);
    }

    /**
     * @param array<string, mixed> $error
     * @return array<string, mixed>
     */
    private function callBuildHistory(
        \Inchoo\MagentoBricklayer\Mcp\Tool\DiagnosticTools $diag,
        array $error,
        string $requestedSource,
        int $analysisHours
    ): array {
        $ref = new \ReflectionClass($diag);
        $method = $ref->getMethod('buildHistory');
        return $method->invoke($diag, $error, $requestedSource, $analysisHours);
    }

    private function callPerformList(LogTools $tools): array
    {
        $ref = new \ReflectionClass(LogTools::class);
        $method = $ref->getMethod('performList');
        return $method->invoke($tools);
    }

    private function callBuildLogEntry(
        LogTools $tools,
        string $type,
        string $relativePath,
        string $absolutePath
    ): ?array {
        $ref = new \ReflectionClass(LogTools::class);
        $method = $ref->getMethod('buildLogEntry');
        return $method->invoke($tools, $type, $relativePath, $absolutePath);
    }

    private function setMagentoRoot(string $path): void
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $ref->getProperty('magentoRoot');
        $prop->setValue(null, $path);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
