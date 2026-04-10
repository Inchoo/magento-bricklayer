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
    private function performList(): array
    {
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

    /**
     * Analyzes exception log for error patterns.
     *
     * Public so it can be called from DiagnosticTools without being a registered MCP tool.
     *
     * @param int $hours Analyze entries from the last N hours
     * @return array<string, mixed> Error analysis
     */
    public function analyzeExceptionLog(int $hours): array
    {
        return $this->performAnalyze($hours);
    }

    /**
     * Analyzes exception log for error patterns (internal implementation).
     *
     * @param int $hours Analyze entries from the last N hours
     * @return array<string, mixed> Error analysis
     */
    private function performAnalyze(int $hours): array
    {
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
}
