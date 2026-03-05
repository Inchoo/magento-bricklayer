<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\ContextTools;
use PHPUnit\Framework\TestCase;

/**
 * Tests that development-context responses include _next_steps for categories
 * that have introspection recommendations and null for those that don't.
 */
class NextStepsTest extends TestCase
{
    private ContextTools $tools;

    protected function setUp(): void
    {
        $this->tools = new ContextTools();
    }

    /**
     * @dataProvider categoriesWithNextStepsProvider
     */
    public function testCategoryHasNextSteps(string $category): void
    {
        $result = $this->tools->getDevelopmentContext($category);

        $this->assertArrayHasKey('_next_steps', $result, "Category '{$category}' should have _next_steps key");
        $this->assertIsArray($result['_next_steps'], "Category '{$category}' _next_steps should be an array");
        $this->assertNotEmpty($result['_next_steps'], "Category '{$category}' _next_steps should not be empty");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function categoriesWithNextStepsProvider(): array
    {
        return [
            'plugin' => ['plugin'],
            'observer' => ['observer'],
            'preference' => ['preference'],
            'eav' => ['eav'],
            'model' => ['model'],
            'data-patch' => ['data-patch'],
            'rest-api' => ['rest-api'],
            'graphql' => ['graphql'],
            'cron' => ['cron'],
            'indexer' => ['indexer'],
            'module' => ['module'],
            'frontend' => ['frontend'],
            'adminhtml' => ['adminhtml'],
            'checkout' => ['checkout'],
            'hyva-checkout' => ['hyva-checkout'],
            'hyva-theme' => ['hyva-theme'],
        ];
    }

    /**
     * @dataProvider categoriesWithoutNextStepsProvider
     */
    public function testCategoryHasNullNextSteps(string $category): void
    {
        $result = $this->tools->getDevelopmentContext($category);

        $this->assertArrayHasKey('_next_steps', $result, "Category '{$category}' should have _next_steps key");
        $this->assertNull($result['_next_steps'], "Category '{$category}' _next_steps should be null");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function categoriesWithoutNextStepsProvider(): array
    {
        return [
            'coding-standards' => ['coding-standards'],
            'security' => ['security'],
            'performance' => ['performance'],
            'testing' => ['testing'],
        ];
    }

    public function testNextStepsSchemaConsistency(): void
    {
        // Every category should have _next_steps key (either array or null)
        foreach (array_keys(ContextTools::CATEGORY_MAP) as $category) {
            $result = $this->tools->getDevelopmentContext($category);

            $this->assertArrayHasKey(
                '_next_steps',
                $result,
                "Category '{$category}' response should always include _next_steps key"
            );

            if ($result['_next_steps'] !== null) {
                $this->assertIsArray($result['_next_steps']);
                foreach ($result['_next_steps'] as $step) {
                    $this->assertIsString($step, "Each next step should be a string");
                }
            }
        }
    }
}
