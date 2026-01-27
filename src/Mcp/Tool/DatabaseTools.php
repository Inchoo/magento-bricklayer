<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Mcp\Capability\Attribute\McpTool;

/**
 * Database Tools
 *
 * Provides MCP tools for inspecting Magento database schema.
 */
class DatabaseTools
{
    /**
     * Returns table structure with columns, indexes, and foreign keys.
     *
     * @param string $table Table name (optional, if empty lists all tables)
     * @param string $pattern Table name pattern for filtering (e.g., "catalog_%")
     * @return array<string, mixed> Schema information
     */
    #[McpTool(
        name: 'database-schema',
        description: 'Returns database table structure with columns, indexes, and foreign keys'
    )]
    public function getDatabaseSchema(string $table = '', string $pattern = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();

            // If specific table requested
            if ($table !== '') {
                return $this->getTableSchema($connection, $table);
            }

            // List tables, optionally filtered
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

    /**
     * Executes a read-only SQL query (SELECT only).
     *
     * @param string $query The SQL SELECT query to execute
     * @param int $limit Maximum number of rows to return
     * @return array<string, mixed> Query results
     */
    #[McpTool(
        name: 'database-query',
        description: 'Executes a read-only SQL SELECT query against the Magento database'
    )]
    public function executeQuery(string $query, int $limit = 100): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        // Security: Only allow SELECT queries
        $trimmedQuery = trim($query);
        if (!preg_match('/^SELECT\s/i', $trimmedQuery)) {
            return [
                'error' => true,
                'message' => 'Only SELECT queries are allowed for security reasons',
            ];
        }

        // Check for dangerous patterns
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

            // Add LIMIT if not present
            if (!preg_match('/\bLIMIT\s+\d+/i', $trimmedQuery)) {
                $trimmedQuery = rtrim($trimmedQuery, ';') . " LIMIT $limit";
            }

            $startTime = microtime(true);
            $results = $connection->fetchAll($trimmedQuery);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

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

    /**
     * Get detailed schema for a specific table
     *
     * @param object $connection Database connection
     * @param string $tableName Table name
     * @return array<string, mixed>
     */
    private function getTableSchema(object $connection, string $tableName): array
    {
        // Get columns
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

        // Get indexes
        $indexes = [];
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

        // Get foreign keys
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
            // Foreign key info may not be available
        }

        // Get table info
        $tableInfo = $connection->fetchRow("SHOW TABLE STATUS LIKE '$tableName'");

        return [
            'table' => $tableName,
            'engine' => $tableInfo['Engine'] ?? 'unknown',
            'rows' => (int) ($tableInfo['Rows'] ?? 0),
            'collation' => $tableInfo['Collation'] ?? '',
            'comment' => $tableInfo['Comment'] ?? '',
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
        ];
    }
}
