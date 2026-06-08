<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Mcp\Prompt;

use Inchoo\MagentoBricklayer\Mcp\Prompt\AbstractPrompt;
use Inchoo\MagentoBricklayer\Mcp\Prompt\BlockPrompts;
use Inchoo\MagentoBricklayer\Mcp\Prompt\CatalogPrompts;
use Inchoo\MagentoBricklayer\Mcp\Prompt\OrderPrompts;
use Mcp\Capability\Attribute\McpPrompt;
use PHPUnit\Framework\TestCase;

class AbstractPromptTest extends TestCase
{
    public function testItBuildsAUserPromptMessageWithOneConsistentContentShape(): void
    {
        // Verify the canonical shape: [['role'=>'user','content'=>['type'=>'text','text'=>...]]]
        $concrete = new class extends AbstractPrompt {
        };

        $result = $this->callUserMessage($concrete, 'Hello World');

        $this->assertCount(1, $result);
        $this->assertSame('user', $result[0]['role']);
        $this->assertIsArray($result[0]['content']);
        $this->assertSame('text', $result[0]['content']['type']);
        $this->assertSame('Hello World', $result[0]['content']['text']);
    }

    public function testItProducesTheCanonicalContentShapeForAPreviouslyPlainStringPrompt(): void
    {
        // BlockPrompts, CatalogPrompts, OrderPrompts used to return plain strings
        // After refactoring they must return the typed ['type'=>'text','text'=>...] shape
        $blockPrompts = new BlockPrompts();
        $result = $blockPrompts->createBlock('Vendor', 'Module', 'MyBlock');

        $this->assertCount(1, $result);
        $this->assertSame('user', $result[0]['role']);
        $msg = 'BlockPrompts should now use typed content shape, not a plain string';
        $this->assertIsArray($result[0]['content'], $msg);
        $this->assertSame('text', $result[0]['content']['type']);
        $this->assertStringContainsString('MyBlock', $result[0]['content']['text']);
    }

    public function testItKeepsEveryPromptMethodDiscoverableAsAnMcpPromptEntryPoint(): void
    {
        $promptClasses = [
            \Inchoo\MagentoBricklayer\Mcp\Prompt\ApiPrompts::class,
            \Inchoo\MagentoBricklayer\Mcp\Prompt\BlockPrompts::class,
            \Inchoo\MagentoBricklayer\Mcp\Prompt\CatalogPrompts::class,
            \Inchoo\MagentoBricklayer\Mcp\Prompt\ControllerPrompts::class,
            \Inchoo\MagentoBricklayer\Mcp\Prompt\ModulePrompts::class,
            \Inchoo\MagentoBricklayer\Mcp\Prompt\OrderPrompts::class,
            \Inchoo\MagentoBricklayer\Mcp\Prompt\PluginPrompts::class,
            \Inchoo\MagentoBricklayer\Mcp\Prompt\TestPrompts::class,
        ];

        $total = 0;
        foreach ($promptClasses as $className) {
            $reflClass = new \ReflectionClass($className);

            foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Discoverer only picks up methods declared in the concrete class itself
                if ($method->getDeclaringClass()->getName() !== $className) {
                    continue;
                }
                if (
                    $method->isStatic() || $method->isAbstract()
                    || $method->isConstructor() || $method->isDestructor()
                ) {
                    continue;
                }

                $attrs = $method->getAttributes(McpPrompt::class, \ReflectionAttribute::IS_INSTANCEOF);
                if (!empty($attrs)) {
                    $total++;
                }
            }
        }

        // 4 + 5 + 3 + 4 + 7 + 2 + 4 + 4 = 33
        $this->assertSame(33, $total, 'All #[McpPrompt] methods should still be discoverable after refactoring');
    }

    public function testItReturnsTheSameRoleAndMessageTextThePromptPreviouslyProduced(): void
    {
        // Verify that the text content is preserved (not truncated/altered)
        $catalogPrompts = new CatalogPrompts();
        $result = $catalogPrompts->createProduct('simple', 'Default', 'TEST-SKU', 'Test Product', 9.99);

        $this->assertCount(1, $result);
        $this->assertSame('user', $result[0]['role']);
        $this->assertIsArray($result[0]['content']);
        $this->assertSame('text', $result[0]['content']['type']);
        $this->assertStringContainsString('TEST-SKU', $result[0]['content']['text']);
        $this->assertStringContainsString('Test Product', $result[0]['content']['text']);
        $this->assertStringContainsString('simple', $result[0]['content']['text']);

        // Also verify OrderPrompts (another formerly plain-string class)
        $orderPrompts = new OrderPrompts();
        $orderResult = $orderPrompts->processOrder('000000001', 'invoice', 'rush order');

        $this->assertCount(1, $orderResult);
        $this->assertSame('user', $orderResult[0]['role']);
        $this->assertIsArray($orderResult[0]['content']);
        $this->assertSame('text', $orderResult[0]['content']['type']);
        $this->assertStringContainsString('000000001', $orderResult[0]['content']['text']);
    }

    /**
     * Helper to call the protected userMessage() method via reflection.
     *
     * @return array<array<string, mixed>>
     */
    private function callUserMessage(AbstractPrompt $instance, string $text): array
    {
        $method = new \ReflectionMethod($instance, 'userMessage');
        /** @var array<array<string, mixed>> $result */
        $result = $method->invoke($instance, $text);
        return $result;
    }
}
