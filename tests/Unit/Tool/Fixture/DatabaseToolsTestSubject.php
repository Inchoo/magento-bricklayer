<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Fixture;

use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Mcp\Tool\DatabaseTools;

/**
 * Testable subclass of DatabaseTools for unit testing.
 *
 * - Allows injecting a pre-configured ConfigLoader (gate logic tests).
 * - Exposes the private getTableSchema() via a public proxy (B6 SQL tests).
 *
 * For B7 gate tests: inject a stub ObjectManager into MagentoBootstrap via
 * reflection in the test, then call getDatabaseSchema() directly on this class
 * to exercise the real production gate logic on line 31 of DatabaseTools.php.
 */
class DatabaseToolsTestSubject extends DatabaseTools
{
    /**
     * Inject a ConfigLoader to override the default lazy-loaded one.
     *
     * Uses reflection to set the private 'configLoader' property declared in the
     * ChecksConfig trait (compiled into DatabaseTools, not accessible from subclass).
     */
    public function setConfigLoader(ConfigLoader $loader): void
    {
        $ref = new \ReflectionClass(DatabaseTools::class);
        $prop = $ref->getProperty('configLoader');
        $prop->setValue($this, $loader);
    }

    /**
     * Expose the private getTableSchema so tests can inject a mock connection.
     */
    public function callGetTableSchema(object $connection, string $tableName): array
    {
        $ref = new \ReflectionClass(DatabaseTools::class);
        $method = $ref->getMethod('getTableSchema');
        return $method->invoke($this, $connection, $tableName);
    }
}
