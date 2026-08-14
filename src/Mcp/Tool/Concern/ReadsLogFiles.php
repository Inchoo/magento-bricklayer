<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\TimeUnits;

/**
 * Shared log file reading infrastructure for LogTools and DiagnosticTools.
 */
trait ReadsLogFiles
{
    /**
     * Canonical map of Magento log source names to their paths relative to the Magento root.
     *
     * Single source of truth shared by LogTools and DiagnosticTools. Implemented as a method
     * rather than a trait constant to remain compatible with PHP 8.1 (trait constants require 8.2).
     *
     * @return array<string, string>
     */
    protected static function logFiles(): array
    {
        return [
            'system' => 'var/log/system.log',
            'exception' => 'var/log/exception.log',
            'debug' => 'var/log/debug.log',
            'cron' => 'var/log/cron.log',
        ];
    }

    protected function readLastLines(string $path, int $lines): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $fileSize = filesize($path);
        if ($fileSize === false) {
            fclose($handle);
            return [];
        }

        $bufferSize = min($fileSize, $lines * 500);
        if ($bufferSize <= 0) {
            fclose($handle);
            return [];
        }

        fseek($handle, max(0, $fileSize - $bufferSize));
        $content = fread($handle, $bufferSize);
        fclose($handle);

        if ($content === false) {
            return [];
        }

        $allLines = explode("\n", $content);

        if ($fileSize > $bufferSize) {
            array_shift($allLines);
        }

        return array_slice($allLines, -$lines);
    }

    protected function parseLogEntry(string $entry): ?array
    {
        $entry = trim($entry);
        if ($entry === '') {
            return null;
        }

        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s*(\w+)\.(\w+):\s*(.*)$/', $entry, $matches)) {
            return [
                'timestamp' => $matches[1],
                'channel' => $matches[2],
                'level' => $matches[3],
                'message' => $matches[4],
            ];
        }

        return [
            'timestamp' => null,
            'channel' => null,
            'level' => null,
            'message' => $entry,
        ];
    }

    /**
     * Calculate a Unix timestamp cutoff from a relative time string.
     *
     * @param string $since Relative time ('5m', '1h', '24h', '7d')
     * @return int|null Cutoff timestamp, or null if format is invalid
     */
    protected function calculateTimeCutoff(string $since): ?int
    {
        return TimeUnits::toCutoff($since);
    }

    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Truncate a string to a maximum length, preserving beginning and end.
     *
     * @param string $text The text to truncate
     * @param int $maxLength Maximum allowed length
     * @return array{text: string, truncated: bool, original_length: int}
     */
    protected function truncateText(string $text, int $maxLength): array
    {
        $originalLength = strlen($text);

        if ($originalLength <= $maxLength) {
            return [
                'text' => $text,
                'truncated' => false,
                'original_length' => $originalLength,
            ];
        }

        $separator = "\n... [truncated — {$originalLength} chars total] ...\n";

        // When the cap is too small to hold even the descriptive separator, fall back to
        // a hard cut so the output still respects maxLength.
        if (strlen($separator) >= $maxLength) {
            return [
                'text' => substr($text, 0, $maxLength),
                'truncated' => true,
                'original_length' => $originalLength,
            ];
        }

        // Show first 70% and last 20% of the budget after subtracting the separator length.
        $budget = $maxLength - strlen($separator);
        $headLength = (int) ($budget * 0.7);
        $tailLength = (int) ($budget * 0.2);

        // Guard $tailLength === 0: substr($text, -0) would return the entire string.
        $tail = $tailLength > 0 ? substr($text, -$tailLength) : '';

        return [
            'text' => substr($text, 0, $headLength) . $separator . $tail,
            'truncated' => true,
            'original_length' => $originalLength,
        ];
    }

    /**
     * Truncate the 'message' field of each entry that exceeds $maxEntryLength, flagging it
     * with 'truncated' => true and the original length. A non-positive $maxEntryLength means
     * no limit and the entries are returned unchanged.
     *
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    protected function truncateEntries(array $entries, int $maxEntryLength): array
    {
        if ($maxEntryLength <= 0) {
            return $entries;
        }

        foreach ($entries as &$entry) {
            if (
                isset($entry['message'])
                && is_string($entry['message'])
                && strlen($entry['message']) > $maxEntryLength
            ) {
                $truncated = $this->truncateText($entry['message'], $maxEntryLength);
                $entry['message'] = $truncated['text'];
                $entry['truncated'] = true;
                $entry['original_length'] = $truncated['original_length'];
            }
        }
        unset($entry);

        return $entries;
    }
}
