<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\ConfigurationTools;

/**
 * These assertions can only pass against a bootstrapped ObjectManager:
 * the resolved preference for an API interface is runtime state that no
 * amount of static file reading produces, which is exactly the behavior
 * the integration lane exists to protect.
 */
final class ConfigurationToolsTest extends IntegrationTestCase
{
    public function testDiConfigurationResolvesTheRuntimePreference(): void
    {
        $interface = 'Magento\\Catalog\\Api\\ProductRepositoryInterface';
        $result = (new ConfigurationTools())->getDiConfiguration($interface);

        self::assertToolSuccess($result);
        self::assertSame($interface, $result['class'] ?? null);

        $preference = self::stringValue($result, 'preference');
        self::assertNotSame($interface, $preference);
        self::assertStringContainsString('ProductRepository', $preference);
    }

    public function testCheckClassCombinesPluginDiAndPreferenceViews(): void
    {
        $result = (new ConfigurationTools())->checkClass('Magento\\Framework\\App\\Config\\ScopeConfigInterface');

        self::assertToolSuccess($result);
        self::assertSame('Magento\\Framework\\App\\Config\\ScopeConfigInterface', $result['class'] ?? null);
        self::assertIsArray($result['plugins'] ?? null);
        self::assertIsArray($result['di_configuration'] ?? null);
        self::assertIsArray($result['preferences'] ?? null);
    }
}
