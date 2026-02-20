<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ReadsLogFiles;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

/**
 * Log Tools
 *
 * Provides MCP tools for reading and analyzing Magento logs.
 */
class LogTools
{
    use ReadsLogFiles;
    use RequiresMagento;
    /**
     * Available Magento log files
     */
    private const LOG_FILES = [
        'system' => 'var/log/system.log',
        'exception' => 'var/log/exception.log',
        'debug' => 'var/log/debug.log',
        'cron' => 'var/log/cron.log',
        'support_report' => 'var/log/support_report.log',
    ];

    /**
     * Reads recent entries from a Magento log file.
     *
     * @param string $logType Log type (system, exception, debug, cron)
     * @param int $lines Number of lines to read
     * @param string $filter Filter entries containing this text
     * @param int $max_entry_length Maximum length per log entry message (0 = no limit)
     * @return array<string, mixed> Log entries
     */
    #[McpTool(
        name: 'log-read',
        description: 'Reads recent entries from Magento log files. Set max_entry_length to truncate long entries (0 = no limit).'
    )]
    public function readLog(string $logType = 'system', int $lines = 100, string $filter = '', int $max_entry_length = 0): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if (!isset(self::LOG_FILES[$logType])) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Invalid log type "%s". Available: %s',
                    $logType,
                    implode(', ', array_keys(self::LOG_FILES))
                ),
            ];
        }

        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $logPath = $magentoRoot . '/' . self::LOG_FILES[$logType];

            if (!file_exists($logPath)) {
                return [
                    'log_type' => $logType,
                    'file' => self::LOG_FILES[$logType],
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

            // Apply truncation if max_entry_length is set
            if ($max_entry_length > 0) {
                foreach ($parsedEntries as &$entry) {
                    if (isset($entry['message']) && strlen($entry['message']) > $max_entry_length) {
                        $truncated = $this->truncateText($entry['message'], $max_entry_length);
                        $entry['message'] = $truncated['text'];
                        $entry['truncated'] = true;
                        $entry['original_length'] = $truncated['original_length'];
                    }
                }
                unset($entry);
            }

            return [
                'log_type' => $logType,
                'file' => self::LOG_FILES[$logType],
                'exists' => true,
                'filter' => $filter ?: null,
                'total_entries' => count($parsedEntries),
                'entries' => $parsedEntries,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists available log files and their sizes.
     *
     * @return array<string, mixed> List of log files
     */
    #[McpTool(
        name: 'log-list',
        description: 'Lists available Magento log files with sizes and modification times'
    )]
    public function listLogs(): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $logDir = $magentoRoot . '/var/log';

            $logs = [];

            // Check predefined logs
            foreach (self::LOG_FILES as $type => $relativePath) {
                $path = $magentoRoot . '/' . $relativePath;
                if (file_exists($path)) {
                    $logs[] = [
                        'type' => $type,
                        'file' => $relativePath,
                        'size' => filesize($path),
                        'size_human' => $this->formatFileSize(filesize($path)),
                        'modified' => date('Y-m-d H:i:s', filemtime($path)),
                    ];
                }
            }

            // Find additional log files
            if (is_dir($logDir)) {
                $files = glob($logDir . '/*.log');
                foreach ($files as $file) {
                    $filename = basename($file);
                    $relativePath = 'var/log/' . $filename;

                    // Skip if already in predefined list
                    if (in_array($relativePath, self::LOG_FILES)) {
                        continue;
                    }

                    $logs[] = [
                        'type' => pathinfo($filename, PATHINFO_FILENAME),
                        'file' => $relativePath,
                        'size' => filesize($file),
                        'size_human' => $this->formatFileSize(filesize($file)),
                        'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    ];
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
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Analyzes exception log for error patterns.
     *
     * @param int $hours Analyze entries from the last N hours
     * @return array<string, mixed> Error analysis
     */
    #[McpTool(
        name: 'log-analyze',
        description: 'Analyzes exception log for error patterns and frequency'
    )]
    public function analyzeExceptionLog(int $hours = 24): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $logPath = $magentoRoot . '/' . self::LOG_FILES['exception'];

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
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Searches across all log files.
     *
     * @param string $query Search query
     * @param int $maxResults Maximum results per file
     * @param int $max_entry_length Maximum length per log entry message (0 = no limit)
     * @return array<string, mixed> Search results
     */
    #[McpTool(
        name: 'log-search',
        description: 'Searches for a pattern across all Magento log files. Set max_entry_length to truncate long entries.'
    )]
    public function searchLogs(string $query, int $maxResults = 50, int $max_entry_length = 0): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if (strlen($query) < 3) {
            return ['error' => true, 'message' => 'Search query must be at least 3 characters'];
        }

        try {
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $results = [];

            foreach (self::LOG_FILES as $type => $relativePath) {
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

                    // Apply truncation if max_entry_length is set
                    if ($max_entry_length > 0) {
                        foreach ($limitedMatches as &$match) {
                            if (isset($match['message']) && strlen($match['message']) > $max_entry_length) {
                                $truncated = $this->truncateText($match['message'], $max_entry_length);
                                $match['message'] = $truncated['text'];
                                $match['truncated'] = true;
                                $match['original_length'] = $truncated['original_length'];
                            }
                        }
                        unset($match);
                    }

                    $results[$type] = [
                        'file' => $relativePath,
                        'match_count' => count($matches),
                        'matches' => $limitedMatches,
                    ];
                }
            }

            return [
                'query' => $query,
                'files_searched' => count(self::LOG_FILES),
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

}
