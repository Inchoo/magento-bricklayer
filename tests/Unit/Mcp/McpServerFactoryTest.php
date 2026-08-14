<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Mcp;

use Inchoo\MagentoBricklayer\Mcp\McpServerFactory;
use Inchoo\MagentoBricklayer\Mcp\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

class McpServerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        ToolRegistry::reset();
    }

    public function testItDerivesTheAdvertisedToolCountFromTheToolRegistry(): void
    {
        $factory = new McpServerFactory();
        $ref = new \ReflectionClass($factory);
        $method = $ref->getMethod('getServerInstructions');

        $instructions = $method->invoke($factory);

        $expectedCount = (string) ToolRegistry::getInstance()->count();
        $this->assertStringContainsString($expectedCount, $instructions);
    }

    public function testItReportsTheActualToolCountAfterTheRegistryChanges(): void
    {
        $factory = new class extends McpServerFactory {
            protected function getToolCount(): int
            {
                return 99;
            }
        };

        $ref = new \ReflectionClass($factory);
        $method = $ref->getMethod('getServerInstructions');

        $instructions = $method->invoke($factory);

        $this->assertStringContainsString('99', $instructions);
        $this->assertStringNotContainsString('80', $instructions);
    }
}
