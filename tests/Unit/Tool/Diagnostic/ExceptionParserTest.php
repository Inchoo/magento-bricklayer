<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Diagnostic;

use Inchoo\MagentoBricklayer\Mcp\Tool\Diagnostic\ExceptionParser;
use PHPUnit\Framework\TestCase;

class ExceptionParserTest extends TestCase
{
    private ExceptionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ExceptionParser();
    }

    public function testSingleLineCriticalWithInlineJsonContext(): void
    {
        $lines = [
            '[2026-02-11T10:23:45.123456+00:00] main.CRITICAL: Exception message here {"exception":"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0): Exception message here at /var/www/html/vendor/magento/framework/View/Layout.php:345)"} []',
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $this->assertEquals('Exception message here', $result[0]['message']);
        $this->assertEquals('Magento\\\\Framework\\\\Exception\\\\LocalizedException', $result[0]['class']);
        $this->assertEquals(0, $result[0]['code']);
        $this->assertEquals('/var/www/html/vendor/magento/framework/View/Layout.php', $result[0]['file']);
        $this->assertEquals(345, $result[0]['line']);
        $this->assertEquals('CRITICAL', $result[0]['level']);
        $this->assertEquals('main', $result[0]['channel']);
    }

    public function testMultiLineExceptionSpanningFiveLines(): void
    {
        $lines = [
            '[2026-02-11T10:23:45.123456+00:00] main.CRITICAL: Exception message here',
            '{"exception":"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0):',
            'Exception message here at /var/www/html/vendor/magento/framework/View/Layout.php:345)',
            '#0 /var/www/html/vendor/magento/framework/App/Http.php(116): Magento\\Framework\\View\\Layout->render()',
            '#1 /var/www/html/pub/index.php(7): Magento\\Framework\\App\\Bootstrap->run()',
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];

        $this->assertEquals('Exception message here', $entry['message']);
        $this->assertEquals('Magento\\\\Framework\\\\Exception\\\\LocalizedException', $entry['class']);
        $this->assertEquals(345, $entry['line']);

        // Verify stack trace was parsed
        $this->assertCount(2, $entry['stack_trace']);
        $this->assertEquals('/var/www/html/vendor/magento/framework/App/Http.php', $entry['stack_trace'][0]['file']);
        $this->assertEquals(116, $entry['stack_trace'][0]['line']);
        $this->assertEquals('/var/www/html/pub/index.php', $entry['stack_trace'][1]['file']);
        $this->assertEquals(7, $entry['stack_trace'][1]['line']);
    }

    public function testChainedExceptions(): void
    {
        $lines = [
            '[2026-02-11T10:23:45.123456+00:00] main.CRITICAL: Outer exception {"exception":"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0): Outer exception at /var/www/html/vendor/magento/framework/View/Layout.php:345, Magento\\\\Framework\\\\Exception\\\\RuntimeException(code: 0): Inner cause at /var/www/html/app/code/Vendor/Module/Block/Custom.php:28)"} []',
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];

        // Primary exception
        $this->assertEquals('Magento\\\\Framework\\\\Exception\\\\LocalizedException', $entry['class']);
        $this->assertEquals('Outer exception', $entry['message']);

        // Previous/chained exception
        $this->assertNotNull($entry['previous']);
        $this->assertEquals('Magento\\\\Framework\\\\Exception\\\\RuntimeException', $entry['previous']['class']);
        $this->assertEquals('Inner cause', $entry['previous']['message']);
        $this->assertEquals('/var/www/html/app/code/Vendor/Module/Block/Custom.php', $entry['previous']['file']);
        $this->assertEquals(28, $entry['previous']['line']);
    }

    public function testTimeFilterExcludesOldEntries(): void
    {
        $oldTimestamp = date('Y-m-d\TH:i:s.u+00:00', strtotime('-48 hours'));
        $recentTimestamp = date('Y-m-d\TH:i:s.u+00:00', strtotime('-30 minutes'));

        $lines = [
            "[$oldTimestamp] main.CRITICAL: Old error {\"exception\":\"[object] (RuntimeError(code: 0): Old error at /var/www/html/test.php:1)\"} []",
            "[$recentTimestamp] main.CRITICAL: Recent error {\"exception\":\"[object] (RuntimeError(code: 0): Recent error at /var/www/html/test.php:2)\"} []",
        ];

        $result = $this->parser->parse($lines, '1h');

        $this->assertCount(1, $result);
        $this->assertEquals('Recent error', $result[0]['message']);
    }

    public function testPatternFilterMatchesSubstring(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.u+00:00');

        $lines = [
            "[$timestamp] main.CRITICAL: Invalid block type error {\"exception\":\"[object] (RuntimeError(code: 0): Invalid block type error at /var/www/html/test.php:1)\"} []",
            "[$timestamp] main.CRITICAL: Database connection failed {\"exception\":\"[object] (RuntimeError(code: 0): Database connection failed at /var/www/html/test.php:2)\"} []",
        ];

        $result = $this->parser->parse($lines, '1h', 'block');

        $this->assertCount(1, $result);
        $this->assertStringContainsString('block', $result[0]['message']);
    }

    public function testIndexSelectionFromMultipleParsedEntries(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.u+00:00');

        $lines = [
            "[$timestamp] main.CRITICAL: Error one {\"exception\":\"[object] (RuntimeError(code: 0): Error one at /var/www/html/test.php:1)\"} []",
            "[$timestamp] main.CRITICAL: Error two {\"exception\":\"[object] (RuntimeError(code: 0): Error two at /var/www/html/test.php:2)\"} []",
            "[$timestamp] main.CRITICAL: Error three {\"exception\":\"[object] (RuntimeError(code: 0): Error three at /var/www/html/test.php:3)\"} []",
        ];

        $result = $this->parser->parse($lines, '1h');

        // Results are newest-first (reversed), so index 0 = Error three, index 1 = Error two
        $this->assertCount(3, $result);
        $this->assertEquals('Error three', $result[0]['message']);
        $this->assertEquals('Error two', $result[1]['message']);
        $this->assertEquals('Error one', $result[2]['message']);
    }

    public function testMalformedLogLineWithNoTimestamp(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.u+00:00');

        $lines = [
            'Random text without any timestamp format',
            'Another line of junk data',
            "[$timestamp] main.CRITICAL: Valid error {\"exception\":\"[object] (RuntimeError(code: 0): Valid error at /var/www/html/test.php:1)\"} []",
        ];

        $result = $this->parser->parse($lines, '1h');

        // Only the valid entry should be returned
        $this->assertCount(1, $result);
        $this->assertEquals('Valid error', $result[0]['message']);
    }

    public function testEmptyInputReturnsEmptyResult(): void
    {
        $result = $this->parser->parse([], '1h');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSimpleMonologEntryWithoutJsonContext(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.u+00:00');

        $lines = [
            "[$timestamp] main.ERROR: RuntimeException: Something went wrong in /var/www/html/test.php:42",
        ];

        $result = $this->parser->parse($lines, '1h');

        $this->assertCount(1, $result);
        $this->assertEquals('RuntimeException', $result[0]['class']);
        $this->assertStringContainsString('Something went wrong', $result[0]['message']);
    }

    public function testNewestFirstOrdering(): void
    {
        $older = date('Y-m-d\TH:i:s.u+00:00', strtotime('-30 minutes'));
        $newer = date('Y-m-d\TH:i:s.u+00:00', strtotime('-5 minutes'));

        $lines = [
            "[$older] main.CRITICAL: First error {\"exception\":\"[object] (RuntimeError(code: 0): First error at /test.php:1)\"} []",
            "[$newer] main.CRITICAL: Second error {\"exception\":\"[object] (RuntimeError(code: 0): Second error at /test.php:2)\"} []",
        ];

        $result = $this->parser->parse($lines, '1h');

        $this->assertCount(2, $result);
        $this->assertEquals('Second error', $result[0]['message']);
        $this->assertEquals('First error', $result[1]['message']);
    }
}
