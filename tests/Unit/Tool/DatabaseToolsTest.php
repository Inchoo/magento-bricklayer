<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Tests\Unit\Tool\Fixture\DatabaseToolsTestSubject;
use Inchoo\MagentoBricklayer\Tests\Unit\Tool\Fixture\MockDbConnection;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixture/DatabaseToolsTestSubject.php';
require_once __DIR__ . '/Fixture/MockDbConnection.php';

/**
 * Unit tests for B6 (SHOW TABLE STATUS exact-match) and B7 (database-schema gate).
 *
 * B6: getTableSchema must use WHERE Name = ? (exact match), not LIKE (wildcard).
 * B7: getDatabaseSchema must gate on 'database-schema', not 'database-query'.
 *
 * B6 tests: use MockDbConnection to record SQL and verify no LIKE in status query.
 * B7 tests: inject a stub ObjectManager into MagentoBootstrap so requireMagento()
 *           passes, then call the real getDatabaseSchema() with an injected ConfigLoader.
 *           This ensures that reverting line 31 of DatabaseTools.php to 'database-query'
 *           causes the B7 tests to fail.
 */
class DatabaseToolsTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_db_tools_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);

        // Reset MagentoBootstrap in case any test injected a stub ObjectManager.
        if (MagentoBootstrap::isInitialized()) {
            MagentoBootstrap::reset();
        }
    }

    // ── B6: Exact match for table status ─────────────────────────────────────

    public function testItReturnsStatusForTheExactTableNameNotALikeMatch(): void
    {
        $connection = new MockDbConnection();
        $connection->willReturn('SHOW TABLES', [['Tables_in_db' => 'sales_order']]);
        $connection->willReturn(
            'SHOW TABLE STATUS WHERE Name',
            [['Engine' => 'InnoDB', 'Rows' => 42, 'Collation' => 'utf8mb4_general_ci', 'Comment' => '']]
        );
        $connection->willReturn('SHOW FULL COLUMNS FROM', []);
        $connection->willReturn('SHOW INDEX FROM', []);
        $connection->willReturn('KEY_COLUMN_USAGE', []);

        $subject = new DatabaseToolsTestSubject();
        $subject->callGetTableSchema($connection, 'sales_order');

        $statusCalls = array_filter(
            $connection->calls,
            fn($c) => str_contains($c['sql'], 'TABLE STATUS')
        );
        $this->assertNotEmpty($statusCalls, 'Expected a SHOW TABLE STATUS call');

        foreach ($statusCalls as $call) {
            $this->assertStringNotContainsString(
                'LIKE',
                strtoupper($call['sql']),
                'SHOW TABLE STATUS must NOT use LIKE (wildcards would match wrong tables)'
            );
            $this->assertStringContainsString(
                'Name',
                $call['sql'],
                'SHOW TABLE STATUS must use WHERE Name = for exact matching'
            );
        }
    }

    public function testItDoesNotReturnMetadataFromASimilarlyNamedTable(): void
    {
        $connection = new MockDbConnection();
        $targetTable = 'catalog_product_entity';
        $connection->willReturn('SHOW TABLES', [['Tables_in_db' => $targetTable]]);

        $exactStatusRow = [
            'Engine' => 'InnoDB',
            'Rows' => 100,
            'Collation' => 'utf8_general_ci',
            'Comment' => 'exact',
        ];

        $connection->willReturn('SHOW TABLE STATUS WHERE Name', [$exactStatusRow]);
        $connection->willReturn('SHOW FULL COLUMNS FROM', []);
        $connection->willReturn('SHOW INDEX FROM', []);
        $connection->willReturn('KEY_COLUMN_USAGE', []);

        $subject = new DatabaseToolsTestSubject();
        $result = $subject->callGetTableSchema($connection, $targetTable);

        $this->assertSame(
            'exact',
            $result['comment'] ?? null,
            'Metadata must come from the exact table, not a LIKE-matched neighbour'
        );
        $this->assertNotEquals(
            999,
            $result['rows'] ?? null,
            'Row count must not come from a similarly-named table'
        );
    }

    // ── B7: database-schema gates on its own enabled flag ────────────────────

    /**
     * Inject a stub ObjectManager into MagentoBootstrap so requireMagento() passes.
     * This allows the real getDatabaseSchema() gate logic to run in unit tests.
     */
    private function simulateMagentoInitialized(): void
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $ref->getProperty('objectManager');
        // Inject a stdClass as a stand-in; the try/catch in getDatabaseSchema will catch
        // the BootstrapException when it tries to call get() on it, but by that point
        // the gate check (requireToolEnabled) has already run.
        $prop->setValue(null, new \stdClass());
    }

    public function testItGatesDatabaseSchemaOnItsOwnEnabledFlag(): void
    {
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            (string) json_encode([
                'tools' => [
                    'database-schema' => ['enabled' => false],
                    'database-query'  => ['enabled' => true],
                ],
            ])
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $this->simulateMagentoInitialized();

        $subject = new DatabaseToolsTestSubject();
        $subject->setConfigLoader($loader);

        // Call the REAL getDatabaseSchema — no bypass — so the production line 31 runs.
        $result = $subject->getDatabaseSchema('sales_order');

        $this->assertIsArray($result);
        $this->assertTrue($result['error'] ?? false, 'database-schema must be blocked when its own flag is false');
        $this->assertStringContainsString(
            'database-schema',
            $result['message'] ?? '',
            'Error message must name the disabled tool (database-schema, not database-query)'
        );
    }

    public function testItKeepsDatabaseSchemaAvailableWhenOnlyDatabaseQueryIsDisabled(): void
    {
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            (string) json_encode([
                'tools' => [
                    'database-query' => ['enabled' => false],
                ],
            ])
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $this->simulateMagentoInitialized();

        $subject = new DatabaseToolsTestSubject();
        $subject->setConfigLoader($loader);

        $result = $subject->getDatabaseSchema('any_table');

        // The gate for 'database-schema' should pass (only database-query is disabled).
        // The call then fails at the DB level (stub ObjectManager), but must NOT return
        // a config-disabled error that names 'database-query'.
        $this->assertFalse(
            (($result['error'] ?? false) === true)
                && str_contains($result['message'] ?? '', 'database-query is disabled'),
            'Disabling database-query must NOT disable database-schema'
        );
    }

    public function testItDisablesDatabaseSchemaWhenItsOwnFlagIsFalse(): void
    {
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            (string) json_encode([
                'tools' => [
                    'database-schema' => ['enabled' => false],
                ],
            ])
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $this->simulateMagentoInitialized();

        $subject = new DatabaseToolsTestSubject();
        $subject->setConfigLoader($loader);

        $result = $subject->getDatabaseSchema('', '');

        $this->assertIsArray($result);
        $this->assertTrue($result['error'] ?? false);
        $this->assertStringContainsString('disabled', $result['message'] ?? '');
        $this->assertStringContainsString('database-schema', $result['message'] ?? '');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
