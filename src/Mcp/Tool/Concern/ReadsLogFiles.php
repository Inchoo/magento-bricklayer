<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

/**
 * Shared log file reading infrastructure for LogTools and DiagnosticTools.
 */
trait ReadsLogFiles
{
    protected function readLastLines(string $path, int $lines): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $fileSize = filesize($path);
        $bufferSize = min($fileSize, $lines * 500);

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
