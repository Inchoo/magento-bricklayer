<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ReadsLogFiles;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Mcp\Capability\Attribute\McpTool;

/**
 * Log Tools
 *
 * Provides MCP tools for reading and analyzing Magento logs.
 */
class LogTools
{
    use ChecksConfig;
    use ReadsLogFiles;
    use RequiresMagento;
    use RespondsWithErrors;

    /**
     * Read, list, search, or analyze Magento logs.
     *
     * @param string $action Action to perform: read, list, search, analyze
     * @param string $logType Log type for read action (system, exception, debug, cron)
     * @param int $lines Number of lines to read (read action)
     * @param string $filter Filter entries containing this text (read action)
     * @param int $max_entry_length Maximum length per log entry message, 0 = no limit (read, search actions)
     * @param string $query Search query (search action)
     * @param int $maxResults Maximum results per file (search action)
     * @param int $hours Analyze entries from the last N hours (analyze action)
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'log',
        description: 'Read, list, search, or analyze Magento logs. Use max_entry_length to truncate long entries.',
        meta: ['hidden' => true]
    )]
    public function log(
        string $action,
        string $logType = 'system',
        int $lines = 100,
        string $filter = '',
        int $max_entry_length = 0,
        string $query = '',
        int $maxResults = 50,
        int $hours = 24,
    ): array {
        $allowedActions = ['read', 'list', 'search', 'analyze'];

        if (!in_array($action, $allowedActions, true)) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Invalid action "%s". Allowed actions: %s',
                    $action,
                    implode(', ', $allowedActions)
                ),
            ];
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('log')) {
            return $error;
        }

        return match ($action) {
            'read'    => $this->performRead($logType, $lines, $filter, $max_entry_length),
            'list'    => $this->performList(),
            'search'  => $this->performSearch($query, $maxResults, $max_entry_length),
            'analyze' => $this->performAnalyze($hours),
        };
    }

    /**
     * Reads recent entries from a Magento log file.
     *
     * @param string $logType Log type (system, exception, debug, cron)
     * @param int $lines Number of lines to read
     * @param string $filter Filter entries containing this text
     * @param int $max_entry_length Maximum length per log entry message (0 = no limit)
     * @return array<string, mixed> Log entries
     */
    private function performRead(string $logType, int $lines, string $filter, int $max_entry_length): array
    {
        try {
            $configMaxLines = (int) $this->getConfigLoader()->get('tools.log.max_lines', 500);
            if ($lines > $configMaxLines) {
                $lines = $configMaxLines;
            }
        } catch (\Throwable $e) {
            // proceed with parameter default
        }

        if (!isset(self::logFiles()[$logType])) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Invalid log type "%s". Available: %s',
                    $logType,
                    implode(', ', array_keys(self::logFiles()))
                ),
            ];
        }

        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $logPath = $magentoRoot . '/' . self::logFiles()[$logType];

            if (!file_exists($logPath)) {
                return [
                    'log_type' => $logType,
                    'file' => self::logFiles()[$logType],
                    'exists' => false,
                    'entries' => [],
                ];
            }

            $entries = $this->readLastLines($logPath, $lines * 2); // Read extra for filtering

            // Parse and filter entries
            $parsedEntries = [];
            foreach ($entries as $entry) {
                if ($filter !== '' && stripos($entry, $filter) === false) {
                    continue;
                }

                $parsed = $this->parseLogEntry($entry);
                if ($parsed !== null) {
                    $parsedEntries[] = $parsed;
                }
            }

            // Limit to requested number
            $parsedEntries = array_slice($parsedEntries, -$lines);
            $parsedEntries = $this->truncateEntries($parsedEntries, $max_entry_length);

            return [
                'log_type' => $logType,
                'file' => self::logFiles()[$logType],
                'exists' => true,
                'filter' => $filter ?: null,
                'total_entries' => count($parsedEntries),
                'entries' => $parsedEntries,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Lists available log files and their sizes.
     *
     * @return array<string, mixed> List of log files
     */
    private function performList(): array
    {
        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $logDir = $magentoRoot . '/var/log';

            $logs = [];

            // Check predefined logs
            foreach (self::logFiles() as $type => $relativePath) {
                $path = $magentoRoot . '/' . $relativePath;
                if (file_exists($path)) {
                    $entry = $this->buildLogEntry($type, $relativePath, $path);
                    if ($entry !== null) {
                        $logs[] = $entry;
                    }
                }
            }

            // Find additional log files
            if (is_dir($logDir)) {
                $files = glob($logDir . '/*.log') ?: [];
                foreach ($files as $file) {
                    $filename = basename($file);
                    $relativePath = 'var/log/' . $filename;

                    // Skip if already in predefined list
                    if (in_array($relativePath, self::logFiles())) {
                        continue;
                    }

                    $entry = $this->buildLogEntry(
                        pathinfo($filename, PATHINFO_FILENAME),
                        $relativePath,
                        $file
                    );
                    if ($entry !== null) {
                        $logs[] = $entry;
                    }
                }
            }

            // Sort by modified time descending
            usort($logs, fn($a, $b) => strcmp($b['modified'], $a['modified']));

            return [
                'log_directory' => 'var/log',
                'total' => count($logs),
                'logs' => $logs,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Build a log file entry array from file stat; returns null if stat fails.
     *
     * Guards against filesize()/filemtime() returning false (TOCTOU race or
     * inaccessible file) to prevent TypeError under strict_types.
     *
     * @return array<string, mixed>|null
     */
    private function buildLogEntry(string $type, string $relativePath, string $absolutePath): ?array
    {
        $size = @filesize($absolutePath);
        $mtime = @filemtime($absolutePath);

        if ($size === false || $mtime === false) {
            return null;
        }

        return [
            'type' => $type,
            'file' => $relativePath,
            'size' => $size,
            'size_human' => $this->formatFileSize($size),
            'modified' => date('Y-m-d H:i:s', $mtime),
        ];
    }

    /**
     * Searches across all log files.
     *
     * @param string $query Search query
     * @param int $maxResults Maximum results per file
     * @param int $max_entry_length Maximum length per log entry message (0 = no limit)
     * @return array<string, mixed> Search results
     */
    private function performSearch(string $query, int $maxResults, int $max_entry_length): array
    {
        if (strlen($query) < 3) {
            return ['error' => true, 'message' => 'Search query must be at least 3 characters'];
        }

        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $results = [];

            foreach (self::logFiles() as $type => $relativePath) {
                $path = $magentoRoot . '/' . $relativePath;
                if (!file_exists($path)) {
                    continue;
                }

                $entries = $this->readLastLines($path, 5000);
                $matches = [];

                foreach ($entries as $entry) {
                    if (stripos($entry, $query) !== false) {
                        $parsed = $this->parseLogEntry($entry);
                        if ($parsed !== null) {
                            $matches[] = $parsed;
                        }
                    }
                }

                if (!empty($matches)) {
                    $limitedMatches = array_slice($matches, -$maxResults);
                    $limitedMatches = $this->truncateEntries($limitedMatches, $max_entry_length);

                    $results[$type] = [
                        'file' => $relativePath,
                        'match_count' => count($matches),
                        'matches' => $limitedMatches,
                    ];
                }
            }

            return [
                'query' => $query,
                'files_searched' => count(self::logFiles()),
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Analyzes exception log for error patterns.
     *
     * Public so it can be called from DiagnosticTools without being a registered MCP tool.
     *
     * @param int $hours Analyze entries from the last N hours
     * @param string $source Log file source key (system, exception, debug, cron); defaults to 'exception'
     * @return array<string, mixed> Error analysis
     */
    public function analyzeExceptionLog(int $hours, string $source = 'exception'): array
    {
        return $this->performAnalyze($hours, $source);
    }

    /**
     * Analyzes exception log for error patterns (internal implementation).
     *
     * @param int $hours Analyze entries from the last N hours
     * @param string $source Log file source key (system, exception, debug, cron); defaults to 'exception'
     * @return array<string, mixed> Error analysis
     */
    private function performAnalyze(int $hours, string $source = 'exception'): array
    {
        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $logPath = $magentoRoot . '/' . (self::logFiles()[$source] ?? self::logFiles()['exception']);

            if (!file_exists($logPath)) {
                return [
                    'message' => 'No exception log found',
                    'errors' => [],
                ];
            }

            $cutoffTime = time() - ($hours * 3600);
            $entries = $this->readLastLines($logPath, 5000);

            $errorTypes = [];
            $recentErrors = [];

            foreach ($entries as $entry) {
                $parsed = $this->parseLogEntry($entry);
                if ($parsed === null) {
                    continue;
                }

                // Check if within time range
                $entryTime = strtotime($parsed['timestamp'] ?? '');
                if ($entryTime && $entryTime < $cutoffTime) {
                    continue;
                }

                // Extract exception class
                $message = $parsed['message'] ?? '';
                if (preg_match('/^([\w\\\\]+(?:Exception|Error)):/', $message, $matches)) {
                    $errorTypes[$matches[1]] = ($errorTypes[$matches[1]] ?? 0) + 1;
                }

                $recentErrors[] = $parsed;
            }

            // Sort error types by frequency
            arsort($errorTypes);

            // Get top 10 most recent unique errors
            $uniqueErrors = [];
            $seenMessages = [];
            foreach (array_reverse($recentErrors) as $error) {
                $msgKey = substr($error['message'] ?? '', 0, 100);
                if (!isset($seenMessages[$msgKey])) {
                    $uniqueErrors[] = $error;
                    $seenMessages[$msgKey] = true;
                }
                if (count($uniqueErrors) >= 10) {
                    break;
                }
            }

            return [
                'period_hours' => $hours,
                'total_errors' => count($recentErrors),
                'error_types' => array_slice($errorTypes, 0, 20, true),
                'recent_unique_errors' => $uniqueErrors,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
