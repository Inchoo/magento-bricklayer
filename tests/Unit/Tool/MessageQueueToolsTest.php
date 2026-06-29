<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Config\ConfigInitializer;
use Inchoo\MagentoBricklayer\Config\ConfigValidator;
use Inchoo\MagentoBricklayer\Mcp\Tool\MessageQueueTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\ToolRegistry;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;

class MessageQueueToolsTest extends TestCase
{
    private MessageQueueTools $tools;

    protected function setUp(): void
    {
        $this->tools = new MessageQueueTools();
    }

    public function testInvalidVerbosityReturnsError(): void
    {
        $result = $this->tools->inspectMessageQueue('', '', 'chatty');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('verbosity must be one of', $result['message']);
    }

    public function testValidParamsReachMagentoGuard(): void
    {
        $result = $this->tools->inspectMessageQueue('', '', 'standard');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Magento not initialized', $result['message']);
    }

    public function testToolUsesExpectedTraits(): void
    {
        $ref = new \ReflectionClass(MessageQueueTools::class);
        $traitNames = array_map(fn($t) => $t->getShortName(), $ref->getTraits());

        $this->assertContains('RequiresMagento', $traitNames);
        $this->assertContains('RequiresValidVerbosity', $traitNames);
        $this->assertContains('RespondsWithErrors', $traitNames);
    }

    public function testToolIsRegisteredHiddenWithExpectedName(): void
    {
        $ref = new \ReflectionMethod(MessageQueueTools::class, 'inspectMessageQueue');
        $attrs = $ref->getAttributes(McpTool::class);

        $this->assertCount(1, $attrs);
        $attr = $attrs[0]->newInstance();
        $this->assertSame('message-queue-inspect', $attr->name);
        $this->assertSame(['hidden' => true], $attr->meta);
    }

    public function testToolIsKnownButUngated(): void
    {
        ConfigValidator::clearKnownToolsCache();
        $this->assertContains('message-queue-inspect', ConfigValidator::getKnownTools());

        // Ungated (no requireToolEnabled call) — must NOT appear in the configurable set.
        ConfigInitializer::clearConfigurableToolsCache();
        $this->assertNotContains('message-queue-inspect', ConfigInitializer::discoverConfigurableTools());
    }

    public function testRegisteredInIntrospectionGroup(): void
    {
        ToolRegistry::reset();
        $groups = ToolRegistry::getInstance()->getGroups();

        $this->assertContains('MessageQueueTools', $groups['introspection']);
    }
}
