<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\ToolRegistry;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;

/**
 * Validates progressive disclosure: Tier 1 (visible) vs Tier 2 (hidden) tool counts
 * and ensures specific tools remain in Tier 1.
 */
class ProgressiveDisclosureTest extends TestCase
{
    /** @var array<string, bool> tool name => hidden */
    private static array $toolVisibility = [];
    private static int $totalTools = 0;

    public static function setUpBeforeClass(): void
    {
        self::$toolVisibility = [];
        self::$totalTools = 0;

        $toolDir = dirname(__DIR__, 3) . '/src/Mcp/Tool';

        foreach (glob($toolDir . '/*.php') ?: [] as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\' . $className;

            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(McpTool::class);
                if (empty($attrs)) {
                    continue;
                }

                $instance = $attrs[0]->newInstance();
                $toolName = $instance->name ?? $method->getName();
                $hidden = isset($instance->meta['hidden']) && $instance->meta['hidden'] === true;

                self::$toolVisibility[$toolName] = $hidden;
                self::$totalTools++;
            }
        }
    }

    public function testTier1ToolCount(): void
    {
        $tier1Count = count(array_filter(self::$toolVisibility, fn(bool $hidden) => !$hidden));

        $this->assertSame(
            17,
            $tier1Count,
            sprintf('Expected 17 Tier 1 (visible) tools, found %d: %s', $tier1Count, implode(', ', array_keys(array_filter(self::$toolVisibility, fn(bool $hidden) => !$hidden))))
        );
    }

    public function testTier2ToolCount(): void
    {
        $tier2Count = count(array_filter(self::$toolVisibility, fn(bool $hidden) => $hidden));

        $this->assertSame(
            66,
            $tier2Count,
            sprintf('Expected 66 Tier 2 (hidden) tools, found %d', $tier2Count)
        );
    }

    public function testTotalToolCount(): void
    {
        $this->assertSame(
            83,
            self::$totalTools,
            sprintf('Expected 83 total tools, found %d', self::$totalTools)
        );
    }

    /**
     * @dataProvider expectedTier1ToolsProvider
     */
    public function testSpecificTier1ToolIsNotHidden(string $toolName): void
    {
        $this->assertArrayHasKey(
            $toolName,
            self::$toolVisibility,
            "Tool '$toolName' should exist in registry"
        );

        $this->assertFalse(
            self::$toolVisibility[$toolName],
            "Tool '$toolName' should be Tier 1 (not hidden)"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function expectedTier1ToolsProvider(): array
    {
        return [
            'check-class' => ['check-class'],
            'search-tools' => ['search-tools'],
            'code-runner' => ['code-runner'],
            'code-runner-help' => ['code-runner-help'],
            'development-context' => ['development-context'],
            'product-get' => ['product-get'],
            'order-get' => ['order-get'],
            'customer-get' => ['customer-get'],
            'diagnose-error' => ['diagnose-error'],
            'database-schema' => ['database-schema'],
            'database-query' => ['database-query'],
            'eav-attributes' => ['eav-attributes'],
            'di-configuration' => ['di-configuration'],
            'plugin-list' => ['plugin-list'],
            'preference-list' => ['preference-list'],
            'batch-execute' => ['batch-execute'],
            'reinitialize' => ['reinitialize'],
        ];
    }
}
