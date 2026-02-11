<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

/**
 * Shared log file reading infrastructure.
 *
 * Extracted from LogTools so that DiagnosticTools can reuse the same
 * tail-read and Monolog parsing logic without class inheritance.
 */
trait ReadsLogFiles
{
    /**
     * Read last N lines from a file
     *
     * @param string $path File path
     * @param int $lines Number of lines
     * @return array<string>
     */
    protected function readLastLines(string $path, int $lines): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        // Use tail-like approach for large files
        $fileSize = filesize($path);
        $bufferSize = min($fileSize, $lines * 500); // Estimate 500 bytes per line

        fseek($handle, max(0, $fileSize - $bufferSize));
        $content = fread($handle, $bufferSize);
        fclose($handle);

        if ($content === false) {
            return [];
        }

        $allLines = explode("\n", $content);

        // Skip potentially incomplete first line if we didn't start at beginning
        if ($fileSize > $bufferSize) {
            array_shift($allLines);
        }

        return array_slice($allLines, -$lines);
    }

    /**
     * Parse a log entry into structured data
     *
     * @param string $entry Raw log entry
     * @return array<string, mixed>|null
     */
    protected function parseLogEntry(string $entry): ?array
    {
        $entry = trim($entry);
        if ($entry === '') {
            return null;
        }

        // Match Monolog format: [2024-01-15T10:30:45.123456+00:00] main.LEVEL: message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s*(\w+)\.(\w+):\s*(.*)$/', $entry, $matches)) {
            return [
                'timestamp' => $matches[1],
                'channel' => $matches[2],
                'level' => $matches[3],
                'message' => $matches[4],
            ];
        }

        // Fallback: return raw entry
        return [
            'timestamp' => null,
            'channel' => null,
            'level' => null,
            'message' => $entry,
        ];
    }

    /**
     * Format file size in human readable format
     *
     * @param int $bytes
     * @return string
     */
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
}
