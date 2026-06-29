<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Config\ConfigInitializer;
use Inchoo\MagentoBricklayer\Config\ConfigValidator;
use Inchoo\MagentoBricklayer\Mcp\Tool\ToolRegistry;
use Inchoo\MagentoBricklayer\Mcp\Tool\ViewTools;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;

class ViewToolsTest extends TestCase
{
    private ViewTools $tools;

    protected function setUp(): void
    {
        $this->tools = new ViewTools();
    }

    public function testLayoutInvalidVerbosityReturnsError(): void
    {
        $result = $this->tools->inspectLayout('catalog_product_view', 'frontend', 'loud');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('verbosity must be one of', $result['message']);
    }

    public function testLayoutInvalidAreaReturnsError(): void
    {
        $result = $this->tools->inspectLayout('catalog_product_view', 'webapi_rest', 'standard');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid area', $result['message']);
        $this->assertStringContainsString('frontend', $result['message']);
        $this->assertStringContainsString('adminhtml', $result['message']);
    }

    public function testUiComponentRequiresName(): void
    {
        $result = $this->tools->inspectUiComponent('', 'standard');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('name is required', $result['message']);
    }

    public function testUiComponentInvalidVerbosityReturnsError(): void
    {
        $result = $this->tools->inspectUiComponent('customer_listing', 'verbose');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('verbosity must be one of', $result['message']);
    }

    public function testValidParamsReachMagentoGuard(): void
    {
        // Without a bootstrapped Magento, valid params fall through to the Magento guard
        // rather than a validation error — proving validation accepted them.
        $result = $this->tools->inspectLayout('catalog_product_view', 'frontend', 'standard');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Magento not initialized', $result['message']);
    }

    public function testToolUsesExpectedTraits(): void
    {
        $ref = new \ReflectionClass(ViewTools::class);
        $traitNames = array_map(fn($t) => $t->getShortName(), $ref->getTraits());

        $this->assertContains('ChecksConfig', $traitNames);
        $this->assertContains('RequiresMagento', $traitNames);
        $this->assertContains('RequiresValidVerbosity', $traitNames);
        $this->assertContains('RespondsWithErrors', $traitNames);
    }

    public function testToolsAreRegisteredHiddenWithExpectedNames(): void
    {
        $names = [];
        foreach ((new \ReflectionClass(ViewTools::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(McpTool::class);
            if ($attrs === []) {
                continue;
            }
            $attr = $attrs[0]->newInstance();
            $names[] = $attr->name;
            $this->assertSame(['hidden' => true], $attr->meta, "{$attr->name} must be hidden");
        }

        $this->assertContains('layout-inspect', $names);
        $this->assertContains('ui-component-inspect', $names);
    }

    public function testToolsAreKnownAndConfigGated(): void
    {
        ConfigValidator::clearKnownToolsCache();
        $known = ConfigValidator::getKnownTools();
        $this->assertContains('layout-inspect', $known);
        $this->assertContains('ui-component-inspect', $known);

        // Both call requireToolEnabled(), so the config scanner must auto-discover them.
        ConfigInitializer::clearConfigurableToolsCache();
        $configurable = ConfigInitializer::discoverConfigurableTools();
        $this->assertContains('layout-inspect', $configurable);
        $this->assertContains('ui-component-inspect', $configurable);
    }

    public function testRegisteredInIntrospectionGroup(): void
    {
        ToolRegistry::reset();
        $groups = ToolRegistry::getInstance()->getGroups();

        $this->assertContains('ViewTools', $groups['introspection']);
    }
}
