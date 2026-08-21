<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\ModuleTools;

final class ModuleToolsTest extends IntegrationTestCase
{
    public function testListModulesReadsTheLiveModuleRegistry(): void
    {
        $result = (new ModuleTools())->listModules(true, 'Magento', false, 'minimal');

        self::assertToolSuccess($result);
        self::assertGreaterThan(50, $result['total'] ?? 0);

        $names = [];
        foreach (self::arrayValue($result, 'modules') as $module) {
            if (is_array($module) && is_string($module['name'] ?? null)) {
                $names[] = $module['name'];
            }
        }

        self::assertContains('Magento_Catalog', $names);
        self::assertContains('Magento_Sales', $names);
    }

    public function testCountOnlyAvoidsTheFullPayload(): void
    {
        $result = (new ModuleTools())->listModules(false, '', true);

        self::assertToolSuccess($result);
        self::assertSame(true, $result['count_only'] ?? null);
        self::assertIsInt($result['total'] ?? null);
        self::assertArrayNotHasKey('modules', $result);
    }
}
