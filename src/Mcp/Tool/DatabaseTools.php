<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

class DatabaseTools
{
    use ChecksConfig;
    use RequiresMagento;

    #[McpTool(
        name: 'database-schema',
        description: 'Check BEFORE writing db_schema.xml — shows actual database table structure. Runtime schema may differ from declarative schema due to third-party modules or patches.'
    )]
    public function getDatabaseSchema(string $table = '', string $pattern = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('database-query')) {
            return $error;
        }

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();

            if ($table !== '') {
                return $this->getTableSchema($connection, $table);
            }

            $sql = "SHOW TABLES";
            if ($pattern !== '') {
                $sql .= " LIKE " . $connection->quote($pattern);
            }

            $tables = $connection->fetchCol($sql);

            return [
                'total' => count($tables),
                'tables' => $tables,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'database-query',
        description: 'Executes a read-only SQL SELECT query against the Magento database'
    )]
    public function executeQuery(string $query, int $limit = 100): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('database-query')) {
            return $error;
        }

        try {
            $configMaxRows = (int) $this->getConfigLoader()->get('tools.database-query.max_rows', 100);
            if ($limit > $configMaxRows) {
                $limit = $configMaxRows;
            }
        } catch (\Throwable $e) {
            // proceed with parameter default
        }

        $trimmedQuery = trim($query);
        if (!preg_match('/^SELECT\s/i', $trimmedQuery)) {
            return [
                'error' => true,
                'message' => 'Only SELECT queries are allowed for security reasons',
            ];
        }

        $dangerous = [
            '/;\s*(?:INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|GRANT|REVOKE)/i',
            '/INTO\s+OUTFILE/i',
            '/INTO\s+DUMPFILE/i',
            '/LOAD\s+DATA/i',
        ];

        foreach ($dangerous as $pattern) {
            if (preg_match($pattern, $trimmedQuery)) {
                return [
                    'error' => true,
                    'message' => 'Query contains forbidden patterns',
                ];
            }
        }

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();

            if (!preg_match('/\bLIMIT\s+\d+/i', $trimmedQuery)) {
                $trimmedQuery = rtrim($trimmedQuery, ';') . " LIMIT $limit";
            }

            $startTime = microtime(true);
            $results = $connection->fetchAll($trimmedQuery);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $results = $this->maskSensitiveConfigValues($results);

            return [
                'success' => true,
                'row_count' => count($results),
                'execution_time_ms' => $executionTime,
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getTableSchema(object $connection, string $tableName): array
    {
        // Validate table exists (prevents SQL injection via table name)
        $tables = $connection->fetchCol("SHOW TABLES");
        if (!in_array($tableName, $tables, true)) {
            return ['error' => true, 'message' => "Table not found: $tableName"];
        }

        $columns = [];
        $columnsData = $connection->fetchAll("SHOW FULL COLUMNS FROM `$tableName`");
        foreach ($columnsData as $column) {
            $columns[] = [
                'name' => $column['Field'],
                'type' => $column['Type'],
                'null' => $column['Null'] === 'YES',
                'key' => $column['Key'],
                'default' => $column['Default'],
                'extra' => $column['Extra'],
                'comment' => $column['Comment'] ?? '',
            ];
        }

        $indexData = $connection->fetchAll("SHOW INDEX FROM `$tableName`");
        $indexGroups = [];
        foreach ($indexData as $index) {
            $keyName = $index['Key_name'];
            if (!isset($indexGroups[$keyName])) {
                $indexGroups[$keyName] = [
                    'name' => $keyName,
                    'type' => $index['Index_type'],
                    'unique' => !$index['Non_unique'],
                    'columns' => [],
                ];
            }
            $indexGroups[$keyName]['columns'][] = $index['Column_name'];
        }
        $indexes = array_values($indexGroups);

        $foreignKeys = [];
        try {
            $fkData = $connection->fetchAll(
                "SELECT
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL",
                [$tableName]
            );

            foreach ($fkData as $fk) {
                $foreignKeys[] = [
                    'name' => $fk['CONSTRAINT_NAME'],
                    'column' => $fk['COLUMN_NAME'],
                    'referenced_table' => $fk['REFERENCED_TABLE_NAME'],
                    'referenced_column' => $fk['REFERENCED_COLUMN_NAME'],
                ];
            }
        } catch (\Throwable $e) {
        }

        $tableInfo = $connection->fetchRow("SHOW TABLE STATUS LIKE '$tableName'");

        $result = [
            'table' => $tableName,
            'engine' => $tableInfo['Engine'] ?? 'unknown',
            'rows' => (int) ($tableInfo['Rows'] ?? 0),
            'collation' => $tableInfo['Collation'] ?? '',
            'comment' => $tableInfo['Comment'] ?? '',
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ];

        $result['_skill_hint'] = 'For declarative schema and data patch patterns: development-context category=model';

        return $result;
    }

    private const SENSITIVE_PATH_PREFIXES = [
        'payment/',
        'carriers/',
        'system/smtp',
        'trans_email/',
        'oauth/',
        'admin/security/',
        'catalog/search/elasticsearch',
        'catalog/search/opensearch',
    ];

    /**
     * Mask sensitive configuration values in query results.
     *
     * When results contain rows from core_config_data (detected by having both
     * 'path' and 'value' columns), mask values for known sensitive config paths.
     *
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function maskSensitiveConfigValues(array $results): array
    {
        foreach ($results as &$row) {
            if (!isset($row['path'], $row['value']) || !is_string($row['path'])) {
                continue;
            }

            foreach (self::SENSITIVE_PATH_PREFIXES as $prefix) {
                if (str_starts_with($row['path'], $prefix)) {
                    $row['value'] = '***MASKED***';
                    break;
                }
            }
        }
        unset($row);

        return $results;
    }
}
