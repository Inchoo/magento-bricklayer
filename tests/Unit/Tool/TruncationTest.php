<?php
declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use PHPUnit\Framework\TestCase;

class TruncationTest extends TestCase
{
    private object $reader;

    protected function setUp(): void
    {
        $this->reader = new class {
            use \Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ReadsLogFiles;

            public function testTruncate(string $text, int $maxLength): array
            {
                return $this->truncateText($text, $maxLength);
            }
        };
    }

    public function testTruncateTextShortStringUnchanged(): void
    {
        $result = $this->reader->testTruncate('short text', 100);
        $this->assertFalse($result['truncated']);
        $this->assertEquals('short text', $result['text']);
    }

    public function testTruncateTextLongStringIsTruncated(): void
    {
        $longText = str_repeat('a', 2000);
        $result = $this->reader->testTruncate($longText, 500);

        $this->assertTrue($result['truncated']);
        $this->assertEquals(2000, $result['original_length']);
        $this->assertStringContainsString('truncated', $result['text']);
        $this->assertLessThan(600, strlen($result['text']));
    }

    public function testTruncateTextPreservesHeadAndTail(): void
    {
        $text = 'START_MARKER' . str_repeat('x', 2000) . 'END_MARKER';
        $result = $this->reader->testTruncate($text, 500);

        $this->assertStringStartsWith('START_MARKER', $result['text']);
        $this->assertStringEndsWith('END_MARKER', $result['text']);
    }

    public function testLogToolHasMaxEntryLengthParam(): void
    {
        $ref = new \ReflectionMethod(\Inchoo\MagentoBricklayer\Mcp\Tool\LogTools::class, 'log');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertContains('max_entry_length', $params);
    }

    public function testTruncateTextExactLengthNotTruncated(): void
    {
        $text = str_repeat('a', 100);
        $result = $this->reader->testTruncate($text, 100);
        $this->assertFalse($result['truncated']);
        $this->assertEquals($text, $result['text']);
    }
}
