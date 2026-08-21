<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\DevelopmentTools;

final class ReinitializeTest extends IntegrationTestCase
{
    public function testReinitializeRebuildsAndReportsRegistryState(): void
    {
        $result = (new DevelopmentTools())->reinitialize();

        self::assertToolSuccess($result);
        self::assertSame(true, $result['success'] ?? null);
        self::assertGreaterThan(50, $result['registered_components'] ?? 0);
        self::assertArrayHasKey('modules_loaded', $result);
        self::assertArrayHasKey('newly_registered_files', $result);
    }
}
