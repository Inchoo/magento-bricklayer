<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Guidelines;

use Inchoo\MagentoBricklayer\Guidelines\ToolScanner;
use PHPUnit\Framework\TestCase;

class ToolScannerTest extends TestCase
{
    public function testScanReturnsPositiveCount(): void
    {
        $scanner = new ToolScanner();
        $result = $scanner->scan();

        $this->assertArrayHasKey('totalCount', $result);
        $this->assertIsInt($result['totalCount']);
        $this->assertGreaterThan(0, $result['totalCount']);
    }

    public function testScanCountMatchesMcpToolAttributes(): void
    {
        $scanner = new ToolScanner();
        $result = $scanner->scan();

        // Count #[McpTool] attributes directly from tool files
        $toolDir = dirname(__DIR__, 3) . '/src/Mcp/Tool';
        $expectedCount = 0;

        foreach (glob($toolDir . '/*.php') as $file) {
            $content = file_get_contents($file);
            $expectedCount += preg_match_all('/#\[McpTool/', $content);
        }

        $this->assertEquals($expectedCount, $result['totalCount']);
    }

    public function testScannerFindsToolDirectory(): void
    {
        // The scanner relies on a Tool directory relative to its own location.
        // Verify the expected directory exists.
        $toolDir = dirname(__DIR__, 3) . '/src/Mcp/Tool';
        $this->assertDirectoryExists($toolDir);
        $this->assertNotEmpty(glob($toolDir . '/*.php'));
    }
}
