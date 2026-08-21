<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\ApplicationTools;

final class ApplicationToolsTest extends IntegrationTestCase
{
    public function testApplicationInfoReflectsTheRunningInstallation(): void
    {
        $result = (new ApplicationTools())->getApplicationInfo();

        self::assertToolSuccess($result);
        self::assertMatchesRegularExpression('/^\d+\.\d+/', self::stringValue($result, 'magento_version'));
        self::assertSame('developer', $result['deploy_mode'] ?? null, 'The CI image is baked in developer mode');
        self::assertSame(PHP_VERSION, $result['php_version'] ?? null);

        $modules = self::arrayValue($result, 'modules');
        self::assertGreaterThan(50, $modules['enabled'] ?? 0, 'Even the minimal edition reports its modules');

        $stores = self::arrayValue($result, 'stores');
        self::assertGreaterThanOrEqual(1, $stores['store_views'] ?? 0);
    }
}
