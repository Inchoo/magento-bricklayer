<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Diagnostic;

use Inchoo\MagentoBricklayer\Mcp\Tool\TimeUnits;

/**
 * Parses multi-line Magento exception/error logs into structured data.
 * Supports both Monolog format and raw PHP error output (e.g., from var/report files
 * or non-Monolog log entries). Groups raw lines into blocks, extracts exception chains,
 * and applies time/pattern filters.
 */
class ExceptionParser
{
    private const TIMESTAMP_PATTERN = '/^\[\d{4}-/';
    private const HEADER_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s*(\w+)\.(\w+):\s*(.*)$/';
    private const EXCEPTION_CHAIN_PATTERN = '/([\w\\\\]+(?:Exception|Error))\(code:\s*(\d+)\):\s*(.*?)\s+at\s+([^\s:]+):(\d+)/';
    private const STACK_FRAME_PATTERN = '/^#(\d+)\s+(.+?)\((\d+)\):\s*(.*)$/';

    /**
     * Pattern for raw PHP error lines that can start a new block.
     * Matches: "PHP Fatal error:", "TypeError:", "ValueError:", "Magento\...\Exception:", etc.
     */
    private const RAW_ERROR_PATTERN = '/^(?:PHP\s+(?:Fatal|Parse|Warning|Notice)\s+error\s*:|[\w\\\\]+(?:Exception|Error)\s*:)/';

    /**
     * @param array<string> $rawLines Raw lines from tail-read of log file
     * @param string $since Relative time filter ('5m', '1h', '24h', '7d')
     * @param string $pattern Optional message substring filter
     * @return array<array<string, mixed>> Structured exceptions, newest first
     */
    public function parse(array $rawLines, string $since = '1h', string $pattern = ''): array
    {
        $blocks = $this->groupIntoBlocks($rawLines);

        $entries = [];
        foreach ($blocks as $block) {
            $entry = $this->extractExceptionData($block);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        $cutoff = $this->calculateCutoff($since);
        $filtered = [];

        foreach ($entries as $entry) {
            if ($cutoff !== null && isset($entry['timestamp'])) {
                $entryTime = strtotime($entry['timestamp']);
                if ($entryTime !== false && $entryTime < $cutoff) {
                    continue;
                }
            }

            if ($pattern !== '' && stripos($entry['raw'] ?? '', $pattern) === false) {
                continue;
            }

            $filtered[] = $entry;
        }

        return array_reverse($filtered);
    }

    /**
     * Parse structured error data from a Magento var/report JSON file.
     *
     * @param array<string, mixed> $reportData Decoded JSON from a report file
     * @param string $reportId The report file name/ID
     * @return array<string, mixed>|null Structured error data or null if invalid
     */
    public function parseReportFile(array $reportData, string $reportId): ?array
    {
        if (empty($reportData)) {
            return null;
        }

        $message = $reportData[0] ?? '';
        $trace = $reportData[1] ?? '';
        $url = $reportData['url'] ?? null;
        $scriptName = $reportData['script_name'] ?? null;
        $reportTime = $reportData['report_time'] ?? null;

        if ($message === '') {
            return null;
        }

        $entry = [
            'timestamp' => $reportTime,
            'channel' => 'report',
            'level' => 'CRITICAL',
            'message' => '',
            'class' => null,
            'code' => null,
            'file' => null,
            'line' => null,
            'stack_trace' => [],
            'previous' => null,
            'raw' => is_string($trace) ? $message . "\n" . $trace : $message,
            'source' => 'var/report',
            'report_id' => $reportId,
            'url' => $url,
            'script_name' => $scriptName,
        ];

        // Extract class and message from the error string
        // Format: "ClassName: message in /file:line"
        if (preg_match('/^([\w\\\\]+(?:Exception|Error)):\s*(.*?)(?:\s+in\s+(\S+):(\d+))?$/', $message, $m)) {
            $entry['class'] = $m[1];
            $entry['message'] = $m[2];
            $this->applyFileAndLine($entry, $m[3] ?? '', $m[4] ?? '');
        } else {
            $entry['message'] = $message;
        }

        // Parse stack trace from the trace string
        if (is_string($trace) && $trace !== '') {
            $traceLines = explode("\n", $trace);
            foreach ($traceLines as $traceLine) {
                $traceLine = trim($traceLine);
                if (preg_match(self::STACK_FRAME_PATTERN, $traceLine, $match)) {
                    $entry['stack_trace'][] = [
                        'index' => (int) $match[1],
                        'file' => $match[2],
                        'line' => (int) $match[3],
                        'call' => $match[4],
                    ];
                }
            }
        }

        return $entry;
    }

    private function groupIntoBlocks(array $lines): array
    {
        $blocks = [];
        $currentBlock = [];

        foreach ($lines as $line) {
            if ($this->isBlockStarter($line)) {
                if (!empty($currentBlock)) {
                    $blocks[] = $currentBlock;
                }
                $currentBlock = [$line];
            } elseif (!empty($currentBlock)) {
                $currentBlock[] = $line;
            }
        }

        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        return $blocks;
    }

    /**
     * Determine if a line starts a new log block.
     * Recognizes both Monolog timestamp format and raw PHP error output.
     */
    private function isBlockStarter(string $line): bool
    {
        return preg_match(self::TIMESTAMP_PATTERN, $line) === 1
            || preg_match(self::RAW_ERROR_PATTERN, $line) === 1;
    }

    private function extractExceptionData(array $block): ?array
    {
        if (empty($block)) {
            return null;
        }

        $firstLine = $block[0];
        $raw = implode("\n", $block);

        // Try Monolog format first
        if (preg_match(self::HEADER_PATTERN, $firstLine, $headerMatches)) {
            return $this->extractMonologEntry($headerMatches, $block, $raw);
        }

        // Try raw PHP error format
        if (preg_match(self::RAW_ERROR_PATTERN, $firstLine)) {
            return $this->extractRawErrorEntry($block, $raw);
        }

        return null;
    }

    /**
     * Extract structured data from a Monolog-formatted log block.
     *
     * @param array<string> $headerMatches Regex matches from HEADER_PATTERN
     * @param array<string> $block All lines in the block
     * @param string $raw Joined block content
     * @return array<string, mixed>
     */
    private function extractMonologEntry(array $headerMatches, array $block, string $raw): array
    {
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

        $messagePart = $headerMatches[4];
        $exceptions = $this->extractExceptionChain($raw);

        if (!empty($exceptions)) {
            $primary = $exceptions[0];
            $entry['class'] = $primary['class'];
            $entry['code'] = $primary['code'];
            $entry['message'] = $primary['message'];
            $entry['file'] = $primary['file'];
            $entry['line'] = $primary['line'];

            if (isset($exceptions[1])) {
                $entry['previous'] = $exceptions[1];
            }
        } else {
            $cleanMessage = preg_replace('/\s*\{[^}]*\}\s*\[\]\s*$/', '', $messagePart);
            $entry['message'] = trim($cleanMessage ?? $messagePart);

            if (preg_match('/^([\w\\\\]+(?:Exception|Error)):\s*(.*)/', $entry['message'], $m)) {
                $entry['class'] = $m[1];
                $entry['message'] = $m[2];
            }
        }

        $entry['stack_trace'] = $this->extractStackTrace($block);

        return $entry;
    }

    /**
     * Extract structured data from a raw PHP error block (non-Monolog format).
     *
     * Handles formats like:
     * - "PHP Fatal error: Uncaught TypeError: ... in /path/file.php:123"
     * - "TypeError: Return value must be ... in /path/file.php:123"
     *
     * @param array<string> $block All lines in the block
     * @param string $raw Joined block content
     * @return array<string, mixed>
     */
    private function extractRawErrorEntry(array $block, string $raw): array
    {
        $firstLine = $block[0];

        $entry = [
            'timestamp' => null,
            'channel' => 'php',
            'level' => 'ERROR',
            'message' => '',
            'class' => null,
            'code' => null,
            'file' => null,
            'line' => null,
            'stack_trace' => [],
            'previous' => null,
            'raw' => $raw,
        ];

        // Try "PHP Fatal error: Uncaught ExceptionClass: message in /file:line"
        if (preg_match('/^PHP\s+(?:Fatal|Parse|Warning|Notice)\s+error\s*:\s*(?:Uncaught\s+)?([\w\\\\]+(?:Exception|Error)):\s*(.*?)(?:\s+in\s+(\S+):(\d+))?$/', $firstLine, $m)) {
            $entry['level'] = 'CRITICAL';
            $entry['class'] = $m[1];
            $entry['message'] = trim($m[2]);
            $this->applyFileAndLine($entry, $m[3] ?? '', $m[4] ?? '');
        } elseif (preg_match('/^([\w\\\\]+(?:Exception|Error))\s*:\s*(.*?)(?:\s+in\s+(\S+):(\d+))?$/', $firstLine, $m)) {
            // Try "ExceptionClass: message in /file:line"
            $entry['class'] = $m[1];
            $entry['message'] = trim($m[2]);
            $this->applyFileAndLine($entry, $m[3] ?? '', $m[4] ?? '');
        } else {
            $entry['message'] = $firstLine;
        }

        // Extract exception chains from the full raw text
        $exceptions = $this->extractExceptionChain($raw);
        if (!empty($exceptions) && $entry['class'] === null) {
            $primary = $exceptions[0];
            $entry['class'] = $primary['class'];
            $entry['code'] = $primary['code'];
            $entry['message'] = $primary['message'];
            $entry['file'] = $primary['file'];
            $entry['line'] = $primary['line'];
        }

        $entry['stack_trace'] = $this->extractStackTrace($block);

        return $entry;
    }

    /**
     * Set file and line on an entry from regex match groups, ignoring empty values.
     *
     * @param array<string, mixed> $entry
     */
    private function applyFileAndLine(array &$entry, string $file, string $line): void
    {
        if ($file !== '') {
            $entry['file'] = $file;
        }
        if ($line !== '') {
            $entry['line'] = (int) $line;
        }
    }

    private function extractExceptionChain(string $text): array
    {
        $exceptions = [];

        if (preg_match_all(self::EXCEPTION_CHAIN_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $exceptions[] = [
                    'class' => str_replace('\\\\', '\\', $match[1]),
                    'code' => (int) $match[2],
                    'message' => trim($match[3]),
                    'file' => $match[4],
                    'line' => (int) $match[5],
                ];
            }
        }

        return $exceptions;
    }

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

    private function calculateCutoff(string $since): ?int
    {
        return TimeUnits::toCutoff($since);
    }
}
