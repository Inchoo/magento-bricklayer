<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\PerformanceTools;
use PHPUnit\Framework\TestCase;

class PerformanceToolsTest extends TestCase
{
    private PerformanceTools $tools;

    protected function setUp(): void
    {
        $this->tools = new PerformanceTools();
    }

    public function testInvalidCheckReturnsError(): void
    {
        $result = $this->tools->diagnosePerformance('nonexistent');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid check "nonexistent"', $result['message']);
        $this->assertStringContainsString('indexes', $result['message']);
        $this->assertStringContainsString('cache', $result['message']);
        $this->assertStringContainsString('flat-tables', $result['message']);
        $this->assertStringContainsString('cron-backlog', $result['message']);
        $this->assertStringContainsString('config', $result['message']);
        $this->assertStringContainsString('queries', $result['message']);
    }

    public function testAllCheckValuesAreAccepted(): void
    {
        $validChecks = ['all', 'indexes', 'cache', 'flat-tables', 'cron-backlog', 'config', 'queries'];

        foreach ($validChecks as $check) {
            $result = $this->tools->diagnosePerformance($check);

            // Should not return the "invalid check" error
            $this->assertStringNotContainsString(
                'Invalid check',
                $result['message'] ?? '',
                "Check value '$check' was rejected as invalid"
            );
        }
    }

    public function testDefaultCheckIsAll(): void
    {
        $result = $this->tools->diagnosePerformance();

        // Without Magento, this will return an error about Magento not being initialized
        // but the routing should accept "all" as default
        if (isset($result['check'])) {
            $this->assertEquals('all', $result['check']);
        } else {
            // Magento not initialized — just verify it didn't fail with "invalid check"
            $this->assertStringNotContainsString('Invalid check', $result['message'] ?? '');
        }
    }

    public function testEmptyStringCheckIsInvalid(): void
    {
        $result = $this->tools->diagnosePerformance('');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid check', $result['message']);
    }

    public function testToolUsesChecksConfigTrait(): void
    {
        // Verify the trait is used (ensures config gating is wired)
        $ref = new \ReflectionClass(PerformanceTools::class);
        $traitNames = array_map(fn($t) => $t->getShortName(), $ref->getTraits());

        $this->assertContains('ChecksConfig', $traitNames);
        $this->assertContains('RequiresMagento', $traitNames);
    }
}
