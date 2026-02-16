<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\BatchTools;
use PHPUnit\Framework\TestCase;

class BatchToolsTest extends TestCase
{
    private BatchTools $batch;

    protected function setUp(): void
    {
        $this->batch = new BatchTools();
        // Reset static cache between tests
        $ref = new \ReflectionClass(BatchTools::class);
        $prop = $ref->getProperty('toolRegistry');
        $prop->setValue(null, null);
    }

    public function testRejectsInvalidJson(): void
    {
        $result = $this->batch->batchExecute('not json');
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid JSON', $result['message']);
    }

    public function testRejectsEmptyArray(): void
    {
        $result = $this->batch->batchExecute('[]');
        $this->assertTrue($result['error']);
    }

    public function testRejectsTooManyOperations(): void
    {
        $ops = array_fill(0, 21, ['tool' => 'application-info', 'params' => []]);
        $result = $this->batch->batchExecute(json_encode($ops));
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Too many', $result['message']);
    }

    public function testRejectsBlockedTools(): void
    {
        $ops = [['tool' => 'code-runner', 'params' => ['code' => 'echo 1;']]];
        $result = $this->batch->batchExecute(json_encode($ops));
        $this->assertTrue($result['error']);
        $this->assertNotEmpty($result['validation_errors']);
    }

    public function testRejectsBatchRecursion(): void
    {
        $ops = [['tool' => 'batch-execute', 'params' => ['operations_json' => '[]']]];
        $result = $this->batch->batchExecute(json_encode($ops));
        $this->assertTrue($result['error']);
    }

    public function testRejectsUnknownTool(): void
    {
        $ops = [['tool' => 'nonexistent-tool', 'params' => []]];
        $result = $this->batch->batchExecute(json_encode($ops));
        $this->assertTrue($result['error']);
        $this->assertNotEmpty($result['validation_errors']);
    }

    public function testRejectsMissingToolKey(): void
    {
        $ops = [['params' => []]];
        $result = $this->batch->batchExecute(json_encode($ops));
        $this->assertTrue($result['error']);
    }

    public function testValidOperationStructure(): void
    {
        // application-info doesn't need Magento to return a structure
        // (it returns error: 'Magento not initialized' which is still a valid result)
        $ops = [['tool' => 'application-info', 'params' => []]];
        $result = $this->batch->batchExecute(json_encode($ops));

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('succeeded', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertEquals(1, $result['total']);
    }

    public function testHasMcpToolAttribute(): void
    {
        $ref = new \ReflectionClass(BatchTools::class);
        $method = $ref->getMethod('batchExecute');
        $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertEquals('batch-execute', $instance->name);
    }
}
