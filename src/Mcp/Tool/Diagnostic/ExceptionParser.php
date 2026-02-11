<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Diagnostic;

/**
 * Multi-line Magento exception log parser.
 *
 * Magento exception logs use Monolog format where a single error can span
 * multiple lines — the header line contains the timestamp and level, followed
 * by JSON-encoded exception context with chained exceptions and stack traces.
 *
 * This parser groups raw lines into blocks, extracts structured exception data,
 * and applies time/pattern filters.
 */
class ExceptionParser
{
    /**
     * Monolog timestamp pattern — marks the start of a new log block.
     */
    private const TIMESTAMP_PATTERN = '/^\[\d{4}-/';

    /**
     * Monolog header pattern — extracts timestamp, channel, level, and message.
     */
    private const HEADER_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s*(\w+)\.(\w+):\s*(.*)$/';

    /**
     * Exception chain pattern — matches "ClassName(code: N): message at /path:LINE"
     */
    private const EXCEPTION_CHAIN_PATTERN = '/([A-Za-z\\\\]+(?:Exception|Error))\(code:\s*(\d+)\):\s*(.*?)\s+at\s+([^\s:]+):(\d+)/';

    /**
     * Stack trace frame pattern — matches "#N /path(LINE): call"
     */
    private const STACK_FRAME_PATTERN = '/^#(\d+)\s+(.+?)\((\d+)\):\s*(.*)$/';

    /**
     * Parse raw log lines into structured exception entries.
     *
     * Groups multi-line entries, extracts exception chains from JSON context,
     * parses stack trace frames, and applies time/pattern filters.
     *
     * @param array<string> $rawLines Raw lines from tail-read of log file
     * @param string $since Relative time filter ('5m', '1h', '24h', '7d')
     * @param string $pattern Optional message substring filter
     * @return array<array<string, mixed>> Structured exceptions, newest first
     */
    public function parse(array $rawLines, string $since = '1h', string $pattern = ''): array
    {
        // Stage 1: Group lines into blocks
        $blocks = $this->groupIntoBlocks($rawLines);

        // Stage 2: Extract exception data from each block
        $entries = [];
        foreach ($blocks as $block) {
            $entry = $this->extractExceptionData($block);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        // Stage 3: Filter by time and pattern
        $cutoff = $this->calculateCutoff($since);
        $filtered = [];

        foreach ($entries as $entry) {
            // Apply time filter
            if ($cutoff !== null && isset($entry['timestamp'])) {
                $entryTime = strtotime($entry['timestamp']);
                if ($entryTime !== false && $entryTime < $cutoff) {
                    continue;
                }
            }

            // Apply pattern filter
            if ($pattern !== '' && stripos($entry['raw'] ?? '', $pattern) === false) {
                continue;
            }

            $filtered[] = $entry;
        }

        // Return newest first
        return array_reverse($filtered);
    }

    /**
     * Group raw lines into logical blocks.
     *
     * A new block starts when a line matches the Monolog timestamp pattern.
     * All subsequent lines without a timestamp belong to the current block.
     *
     * @param array<string> $lines
     * @return array<array<string>>
     */
    private function groupIntoBlocks(array $lines): array
    {
        $blocks = [];
        $currentBlock = [];

        foreach ($lines as $line) {
            if (preg_match(self::TIMESTAMP_PATTERN, $line)) {
                if (!empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }
                $currentBlock = [$line];
            } elseif (!empty($currentBlock)) {
                $currentBlock[] = $line;
            }
            // Lines before any timestamp are discarded (partial reads)
        }

        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        return $blocks;
    }

    /**
     * Extract structured exception data from a block of lines.
     *
     * @param array<string> $block
     * @return array<string, mixed>|null
     */
    private function extractExceptionData(array $block): ?array
    {
        if (empty($block)) {
            return null;
        }

        $firstLine = $block[0];
        $raw = implode("\n", $block);

        // Parse Monolog header
        if (!preg_match(self::HEADER_PATTERN, $firstLine, $headerMatches)) {
            return null;
        }

        $entry = [
            'timestamp' => $headerMatches[1],
            'channel' => $headerMatches[2],
            'level' => $headerMatches[3],
            'message' => '',
            'class' => null,
            'code' => null,
            'file' => null,
            'line' => null,
            'stack_trace' => [],
            'previous' => null,
            'raw' => $raw,
        ];

        // The message part may contain inline JSON context
        $messagePart = $headerMatches[4];

        // Try to find JSON context in the full block text
        $fullText = $raw;

        // Extract exception chain from JSON context: {"exception":"[object] (...)"}
        $exceptions = $this->extractExceptionChain($fullText);

        if (!empty($exceptions)) {
            // First exception in chain is the primary
            $primary = $exceptions[0];
            $entry['class'] = $primary['class'];
            $entry['code'] = $primary['code'];
            $entry['message'] = $primary['message'];
            $entry['file'] = $primary['file'];
            $entry['line'] = $primary['line'];

            // Second exception is the previous/cause
            if (isset($exceptions[1])) {
                $entry['previous'] = $exceptions[1];
            }
        } else {
            // No JSON context found — use the raw message
            // Strip trailing JSON artifacts like {} []
            $cleanMessage = preg_replace('/\s*\{[^}]*\}\s*\[\]\s*$/', '', $messagePart);
            $entry['message'] = trim($cleanMessage ?? $messagePart);

            // Try to extract exception class from message
            if (preg_match('/^([\w\\\\]+(?:Exception|Error)):\s*(.*)/', $entry['message'], $m)) {
                $entry['class'] = $m[1];
                $entry['message'] = $m[2];
            }
        }

        // Parse stack trace frames from the block
        $entry['stack_trace'] = $this->extractStackTrace($block);

        return $entry;
    }

    /**
     * Extract exception chain from the full block text.
     *
     * Looks for the JSON context pattern and parses each exception entry.
     *
     * @param string $text
     * @return array<array<string, mixed>>
     */
    private function extractExceptionChain(string $text): array
    {
        $exceptions = [];

        if (preg_match_all(self::EXCEPTION_CHAIN_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $exceptions[] = [
                    'class' => $match[1],
                    'code' => (int) $match[2],
                    'message' => trim($match[3]),
                    'file' => $match[4],
                    'line' => (int) $match[5],
                ];
            }
        }

        return $exceptions;
    }

    /**
     * Extract stack trace frames from block lines.
     *
     * @param array<string> $block
     * @return array<array<string, mixed>>
     */
    private function extractStackTrace(array $block): array
    {
        $frames = [];

        foreach ($block as $line) {
            $line = trim($line);
            if (preg_match(self::STACK_FRAME_PATTERN, $line, $match)) {
                $frames[] = [
                    'index' => (int) $match[1],
                    'file' => $match[2],
                    'line' => (int) $match[3],
                    'call' => $match[4],
                ];
            }
        }

        return $frames;
    }

    /**
     * Calculate the Unix timestamp cutoff from a relative time string.
     *
     * @param string $since Relative time like '5m', '1h', '24h', '7d'
     * @return int|null Unix timestamp cutoff, or null if unparseable
     */
    private function calculateCutoff(string $since): ?int
    {
        if (!preg_match('/^(\d+)([mhd])$/', $since, $m)) {
            return null;
        }

        $value = (int) $m[1];
        $seconds = match ($m[2]) {
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => 3600,
        };

        return time() - $seconds;
    }
}
