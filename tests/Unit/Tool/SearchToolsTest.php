<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\SearchTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

class SearchToolsTest extends TestCase
{
    private SearchTools $search;

    protected function setUp(): void
    {
        $this->search = new SearchTools();
        // Reset ToolRegistry cache between tests
        ToolRegistry::reset();
    }

    public function testSearchToolsReturnsStructure(): void
    {
        $result = $this->search->searchTools('', '', 'names');

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('tools', $result);
        $this->assertArrayHasKey('available_groups', $result);
        $this->assertGreaterThan(0, $result['total']);
    }

    public function testSearchToolsInvalidDetail(): void
    {
        $result = $this->search->searchTools('', '', 'invalid');

        $this->assertTrue($result['error']);
    }

    public function testSearchToolsInvalidGroup(): void
    {
        $result = $this->search->searchTools('', 'nonexistent', 'names');

        $this->assertTrue($result['error']);
    }

    public function testSearchToolsDetailLevelsHaveDifferentKeys(): void
    {
        $names = $this->search->searchTools('', '', 'names');
        $summary = $this->search->searchTools('', '', 'summary');
        $full = $this->search->searchTools('', '', 'full');

        if (!empty($names['tools'])) {
            $this->assertArrayNotHasKey('description', $names['tools'][0]);
        }
        if (!empty($summary['tools'])) {
            $this->assertArrayHasKey('description', $summary['tools'][0]);
            $this->assertArrayNotHasKey('parameters', $summary['tools'][0]);
        }
        if (!empty($full['tools'])) {
            $this->assertArrayHasKey('parameters', $full['tools'][0]);
        }
    }

    public function testSearchToolsFilterByGroup(): void
    {
        $result = $this->search->searchTools('', 'catalog', 'names');

        $this->assertFalse(isset($result['error']));
        $this->assertGreaterThan(0, $result['total']);

        foreach ($result['tools'] as $tool) {
            $this->assertEquals('catalog', $tool['group']);
        }
    }

    public function testSearchToolsFilterByQuery(): void
    {
        $result = $this->search->searchTools('product', '', 'summary');

        $this->assertFalse(isset($result['error']));
        foreach ($result['tools'] as $tool) {
            $nameOrDesc = strtolower($tool['name'] . ' ' . $tool['description']);
            $this->assertStringContainsString('product', $nameOrDesc);
        }
    }

    public function testSearchToolsHasMcpToolAttribute(): void
    {
        $ref = new \ReflectionClass(SearchTools::class);
        $method = $ref->getMethod('searchTools');
        $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertEquals('search-tools', $instance->name);
    }

    public function testSearchDocsFindsPerformanceTool(): void
    {
        $tools = new SearchTools();
        $result = $tools->searchDocs('performance');

        $this->assertGreaterThan(0, $result['result_count']);

        // Check that guidance mentions diagnose-performance
        $this->assertStringContainsString('diagnose-performance', $result['guidance']);
    }

    public function testSearchDocsFindsPerformanceInDiagnosticCategory(): void
    {
        $tools = new SearchTools();
        $result = $tools->searchDocs('diagnose');

        $this->assertGreaterThan(0, $result['result_count']);

        // Find the diagnostic category result
        $diagnosticResult = null;
        foreach ($result['results'] as $r) {
            if ($r['category'] === 'diagnostic') {
                $diagnosticResult = $r;
                break;
            }
        }

        $this->assertNotNull($diagnosticResult, 'diagnostic category should appear in results');
    }
}
