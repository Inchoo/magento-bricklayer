<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\DatabaseTools;

final class DatabaseToolsTest extends IntegrationTestCase
{
    public function testSelectQueriesRunAgainstTheLiveDatabase(): void
    {
        $result = (new DatabaseTools())->executeQuery('SELECT entity_type_code FROM eav_entity_type LIMIT 5');

        self::assertToolSuccess($result);
        self::assertSame(true, $result['success'] ?? null);
        self::assertGreaterThanOrEqual(1, $result['row_count'] ?? 0);
        self::assertStringContainsString('catalog_product', var_export($result['results'] ?? [], true));
    }

    public function testNonSelectStatementsAreRejected(): void
    {
        $result = (new DatabaseTools())->executeQuery('DELETE FROM store');

        self::assertSame(true, $result['error'] ?? null);
        self::assertStringContainsString('SELECT', self::stringValue($result, 'message'));
    }

    public function testSchemaReflectsTheActualTableStructure(): void
    {
        $result = (new DatabaseTools())->getDatabaseSchema('store');

        self::assertToolSuccess($result);
        self::assertStringContainsString('store_id', var_export($result, true));
    }
}
