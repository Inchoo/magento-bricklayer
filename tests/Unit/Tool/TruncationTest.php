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

            /**
             * @param array<int, array<string, mixed>> $entries
             * @return array<int, array<string, mixed>>
             */
            public function callTruncateEntries(array $entries, int $maxEntryLength): array
            {
                return $this->truncateEntries($entries, $maxEntryLength);
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

    public function testItReturnsTruncatedTextNoLongerThanTheMaxLength(): void
    {
        $maxLength = 500;
        $longText = str_repeat('a', 2000);
        /** @phpstan-ignore-next-line */
        $result = $this->reader->testTruncate($longText, $maxLength);

        $this->assertTrue($result['truncated']);
        $this->assertLessThanOrEqual($maxLength, strlen($result['text']));
    }

    public function testItKeepsHeadAndTailWithinTheMaxLengthBudgetForSmallCaps(): void
    {
        $maxLength = 50;
        $longText = str_repeat('b', 200);
        /** @phpstan-ignore-next-line */
        $result = $this->reader->testTruncate($longText, $maxLength);

        $this->assertTrue($result['truncated']);
        $this->assertLessThanOrEqual($maxLength, strlen($result['text']));
    }

    /**
     * B11: output must never exceed maxLength even when the cap is smaller than the
     * descriptive separator, and even when the tail budget rounds down to zero
     * (a naive substr($text, -0) would otherwise return the whole string).
     *
     * @dataProvider smallCapProvider
     */
    public function testItNeverExceedsMaxLengthForAnyCap(int $maxLength): void
    {
        $longText = str_repeat('z', 200);
        /** @phpstan-ignore-next-line */
        $result = $this->reader->testTruncate($longText, $maxLength);

        $this->assertTrue($result['truncated']);
        $this->assertLessThanOrEqual(
            $maxLength,
            strlen($result['text']),
            "truncated output exceeded maxLength={$maxLength}"
        );
    }

    public function testTruncateEntriesLeavesEntriesUnchangedWhenNoLimit(): void
    {
        $entries = [['message' => str_repeat('a', 500)], ['message' => 'short']];
        /** @phpstan-ignore-next-line */
        $result = $this->reader->callTruncateEntries($entries, 0);

        $this->assertSame($entries, $result);
    }

    public function testTruncateEntriesTruncatesOnlyOversizeMessages(): void
    {
        $entries = [
            ['message' => str_repeat('a', 500)],
            ['message' => 'short message'],
            ['level' => 'ERROR'], // no 'message' key — must be left intact
        ];
        /** @phpstan-ignore-next-line */
        $result = $this->reader->callTruncateEntries($entries, 100);

        // Oversize entry truncated + flagged
        $this->assertTrue($result[0]['truncated']);
        $this->assertSame(500, $result[0]['original_length']);
        $this->assertLessThanOrEqual(100, strlen($result[0]['message']));
        // Short entry untouched
        $this->assertSame('short message', $result[1]['message']);
        $this->assertArrayNotHasKey('truncated', $result[1]);
        // No 'message' key — untouched
        $this->assertSame(['level' => 'ERROR'], $result[2]);
    }

    /** @return array<string, array{int}> */
    public static function smallCapProvider(): array
    {
        $caps = [1, 5, 10, 20, 30, 38, 39, 40, 41, 42, 45, 50, 60];
        $out = [];
        foreach ($caps as $c) {
            $out["cap_{$c}"] = [$c];
        }
        return $out;
    }
}
