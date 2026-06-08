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
        $timestamp = date('Y-m-d\TH:i:s.u+00:00', strtotime('-10 minutes'));
        $lines = [
            "[$timestamp] main.CRITICAL: Exception message here {\"exception\":\"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0): Exception message here at /var/www/html/vendor/magento/framework/View/Layout.php:345)\"} []",
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $this->assertEquals('Exception message here', $result[0]['message']);
        $this->assertEquals('Magento\\Framework\\Exception\\LocalizedException', $result[0]['class']);
        $this->assertEquals(0, $result[0]['code']);
        $this->assertEquals('/var/www/html/vendor/magento/framework/View/Layout.php', $result[0]['file']);
        $this->assertEquals(345, $result[0]['line']);
        $this->assertEquals('CRITICAL', $result[0]['level']);
        $this->assertEquals('main', $result[0]['channel']);
    }

    public function testMultiLineExceptionSpanningFiveLines(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.u+00:00', strtotime('-10 minutes'));
        $lines = [
            "[$timestamp] main.CRITICAL: Exception message here",
            '{"exception":"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0):',
            'Exception message here at /var/www/html/vendor/magento/framework/View/Layout.php:345)',
            '#0 /var/www/html/vendor/magento/framework/App/Http.php(116): Magento\\Framework\\View\\Layout->render()',
            '#1 /var/www/html/pub/index.php(7): Magento\\Framework\\App\\Bootstrap->run()',
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];

        $this->assertEquals('Exception message here', $entry['message']);
        $this->assertEquals('Magento\\Framework\\Exception\\LocalizedException', $entry['class']);
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
        $timestamp = date('Y-m-d\TH:i:s.u+00:00', strtotime('-10 minutes'));
        $lines = [
            "[$timestamp] main.CRITICAL: Outer exception {\"exception\":\"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0): Outer exception at /var/www/html/vendor/magento/framework/View/Layout.php:345, Magento\\\\Framework\\\\Exception\\\\RuntimeException(code: 0): Inner cause at /var/www/html/app/code/Vendor/Module/Block/Custom.php:28)\"} []",
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];

        // Primary exception
        $this->assertEquals('Magento\\Framework\\Exception\\LocalizedException', $entry['class']);
        $this->assertEquals('Outer exception', $entry['message']);

        // Previous/chained exception
        $this->assertNotNull($entry['previous']);
        $this->assertEquals('Magento\\Framework\\Exception\\RuntimeException', $entry['previous']['class']);
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

    // ── Raw PHP error format tests (extractRawErrorEntry) ─────────────

    public function testRawPhpFatalErrorWithUncaughtException(): void
    {
        $lines = [
            'PHP Fatal error: Uncaught TypeError: Argument 1 passed to Vendor\\Module\\Model\\Service::process() must be of type int, string given in /var/www/html/app/code/Vendor/Module/Model/Service.php:42',
            'Stack trace:',
            '#0 /var/www/html/vendor/magento/framework/Interception/Interceptor.php(58): Vendor\\Module\\Model\\Service->process()',
            '#1 {main}',
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];
        $this->assertSame('TypeError', $entry['class']);
        $this->assertStringContainsString('Argument 1', $entry['message']);
        $this->assertSame('/var/www/html/app/code/Vendor/Module/Model/Service.php', $entry['file']);
        $this->assertSame(42, $entry['line']);
        $this->assertSame('CRITICAL', $entry['level']);
        $this->assertSame('php', $entry['channel']);
        $this->assertCount(1, $entry['stack_trace']);
    }

    public function testRawPhpParseError(): void
    {
        $lines = [
            "PHP Parse error: syntax error, unexpected '}' in /var/www/html/app/code/Vendor/Module/Helper/Data.php on line 55",
        ];

        // Parse error format differs — "on line 55" vs "in file:55", so class extraction may not match.
        // The method should still parse the line as a raw error block.
        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];
        $this->assertStringContainsString('syntax error', $entry['message']);
    }

    public function testBareExceptionClassFormat(): void
    {
        $lines = [
            'ValueError: strlen(): Argument #1 ($string) must be of type string, null given in /var/www/html/vendor/magento/framework/Serialize/Serializer/Json.php:30',
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];
        $this->assertSame('ValueError', $entry['class']);
        $this->assertSame('/var/www/html/vendor/magento/framework/Serialize/Serializer/Json.php', $entry['file']);
        $this->assertSame(30, $entry['line']);
    }

    public function testRawErrorMixedWithMonologEntries(): void
    {
        $timestamp1 = date('Y-m-d\TH:i:s.u+00:00', strtotime('-10 minutes'));
        $timestamp2 = date('Y-m-d\TH:i:s.u+00:00', strtotime('-5 minutes'));

        $lines = [
            "[$timestamp1] main.CRITICAL: First monolog error {\"exception\":\"[object] (RuntimeError(code: 0): First monolog error at /test.php:1)\"} []",
            'PHP Fatal error: Uncaught TypeError: Raw PHP error in /var/www/html/test.php:99',
            "[$timestamp2] main.CRITICAL: Second monolog error {\"exception\":\"[object] (RuntimeError(code: 0): Second monolog error at /test.php:3)\"} []",
        ];

        $result = $this->parser->parse($lines, '1h');

        // All 3 should be parsed as separate entries
        $this->assertCount(3, $result);

        // Verify newest-first ordering (Monolog entries have timestamps, raw doesn't)
        // The two Monolog entries should maintain their relative order
        $messages = array_column($result, 'message');
        $this->assertContains('Second monolog error', $messages);
        $this->assertContains('First monolog error', $messages);
    }

    // ── parseReportFile tests ─────────────────────────────────────────

    public function testParseReportFile_StandardFormat(): void
    {
        $reportData = [
            0 => 'TypeError: Return value must be of type string, null returned in /var/www/html/app/code/Vendor/Module/Model/Config.php:87',
            1 => "#0 /var/www/html/vendor/magento/framework/App/Http.php(116): ...\n#1 /var/www/html/pub/index.php(7): ...",
            'url' => '/checkout/cart/',
            'script_name' => '/var/www/html/pub/index.php',
            'report_time' => '2026-02-10 14:30:00',
        ];

        $result = $this->parser->parseReportFile($reportData, 'abc123');

        $this->assertNotNull($result);
        $this->assertSame('TypeError', $result['class']);
        $this->assertSame('/var/www/html/app/code/Vendor/Module/Model/Config.php', $result['file']);
        $this->assertSame(87, $result['line']);
        $this->assertCount(2, $result['stack_trace']);
        $this->assertSame('/checkout/cart/', $result['url']);
        $this->assertSame('var/report', $result['source']);
        $this->assertSame('abc123', $result['report_id']);
        $this->assertSame('2026-02-10 14:30:00', $result['timestamp']);
    }

    public function testParseReportFile_EmptyData(): void
    {
        $result = $this->parser->parseReportFile([], 'empty');

        $this->assertNull($result);
    }

    public function testParseReportFile_MessageOnly(): void
    {
        $reportData = [
            0 => 'Some error message',
            1 => '',
        ];

        $result = $this->parser->parseReportFile($reportData, 'msg-only');

        $this->assertNotNull($result);
        $this->assertSame('Some error message', $result['message']);
        $this->assertNull($result['class']);
        $this->assertEmpty($result['stack_trace']);
    }

    public function testParseReportFile_MissingTraceString(): void
    {
        $reportData = [
            0 => 'TypeError: something wrong in /path/file.php:42',
        ];

        $result = $this->parser->parseReportFile($reportData, 'no-trace');

        $this->assertNotNull($result);
        $this->assertSame('TypeError', $result['class']);
        $this->assertStringContainsString('something wrong', $result['message']);
        $this->assertEmpty($result['stack_trace']);
    }

    // ── calculateCutoff edge case tests ───────────────────────────────

    public function testCalculateCutoff_Minutes(): void
    {
        $result = $this->invokePrivateMethod('calculateCutoff', ['30m']);
        $expected = time() - (30 * 60);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta($expected, $result, 2);
    }

    public function testCalculateCutoff_Hours(): void
    {
        $result = $this->invokePrivateMethod('calculateCutoff', ['2h']);
        $expected = time() - (2 * 3600);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta($expected, $result, 2);
    }

    public function testCalculateCutoff_Days(): void
    {
        $result = $this->invokePrivateMethod('calculateCutoff', ['7d']);
        $expected = time() - (7 * 86400);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta($expected, $result, 2);
    }

    public function testCalculateCutoff_InvalidFormat(): void
    {
        $this->assertNull($this->invokePrivateMethod('calculateCutoff', ['invalid']));
        $this->assertNull($this->invokePrivateMethod('calculateCutoff', ['']));
        $this->assertNull($this->invokePrivateMethod('calculateCutoff', ['5x']));
        $this->assertNull($this->invokePrivateMethod('calculateCutoff', ['abc']));
    }

    public function testCalculateCutoff_ZeroValue(): void
    {
        $result = $this->invokePrivateMethod('calculateCutoff', ['0h']);
        $expected = time();

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta($expected, $result, 2);
    }

    public function testItExtractsAChainedExceptionClassContainingDigits(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.u+00:00', strtotime('-10 minutes'));
        $lines = [
            "[$timestamp] main.CRITICAL: Outer exception {\"exception\":\"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0): Outer exception at /var/www/html/vendor/magento/framework/View/Layout.php:345, Vendor\\\\Module2\\\\Exception\\\\Custom2Exception(code: 1): Inner cause at /var/www/html/app/code/Vendor/Module2/Block/Custom.php:28)\"} []",
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];
        $this->assertNotNull($entry['previous']);
        $this->assertSame('Vendor\\Module2\\Exception\\Custom2Exception', $entry['previous']['class']);
    }

    public function testItExtractsAChainedExceptionClassContainingUnderscores(): void
    {
        $timestamp = date('Y-m-d\TH:i:s.u+00:00', strtotime('-10 minutes'));
        $lines = [
            "[$timestamp] main.CRITICAL: Outer exception {\"exception\":\"[object] (Magento\\\\Framework\\\\Exception\\\\LocalizedException(code: 0): Outer exception at /var/www/html/vendor/magento/framework/View/Layout.php:345, Vendor\\\\Module\\\\Exception\\\\Custom_Exception(code: 2): Underscore cause at /var/www/html/app/code/Vendor/Module/Block/Custom.php:42)\"} []",
        ];

        $result = $this->parser->parse($lines, '24h');

        $this->assertCount(1, $result);
        $entry = $result[0];
        $this->assertNotNull($entry['previous']);
        $this->assertSame('Vendor\\Module\\Exception\\Custom_Exception', $entry['previous']['class']);
    }

    /**
     * Invoke a private method on ExceptionParser via reflection.
     */
    private function invokePrivateMethod(string $method, array $args): mixed
    {
        $ref = new \ReflectionClass(ExceptionParser::class);
        $m = $ref->getMethod($method);

        return $m->invoke($this->parser, ...$args);
    }
}
