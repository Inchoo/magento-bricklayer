<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\DiagnosticTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\EavTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\ModuleTools;
use PHPUnit\Framework\TestCase;

class VerbosityTest extends TestCase
{
    public function testListModulesVerbosityParameterExists(): void
    {
        $method = new \ReflectionMethod(ModuleTools::class, 'listModules');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $this->assertContains('verbosity', $params);
    }

    public function testListModulesVerbosityDefault(): void
    {
        $method = new \ReflectionMethod(ModuleTools::class, 'listModules');
        $param = $method->getParameters()[3]; // verbosity is 4th param
        $this->assertEquals('standard', $param->getDefaultValue());
    }

    public function testListModulesRejectsInvalidVerbosity(): void
    {
        // Verbosity is pure input validation and runs before the Magento bootstrap check,
        // so an invalid value is rejected without a live Magento install.
        $result = (new ModuleTools())->listModules(verbosity: 'bogus');

        $this->assertTrue($result['error'] ?? false, 'invalid verbosity must return an error envelope');
        $this->assertStringContainsString('verbosity', $result['message'] ?? '');
    }

    public function testGetEavAttributesVerbosityParameterExists(): void
    {
        $method = new \ReflectionMethod(EavTools::class, 'getEavAttributes');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $this->assertContains('verbosity', $params);
    }

    public function testGetEavAttributesVerbosityDefault(): void
    {
        $method = new \ReflectionMethod(EavTools::class, 'getEavAttributes');
        $param = $method->getParameters()[2]; // verbosity is 3rd param
        $this->assertEquals('standard', $param->getDefaultValue());
    }

    public function testDiagnoseErrorVerbosityParameterExists(): void
    {
        $method = new \ReflectionMethod(DiagnosticTools::class, 'diagnoseError');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $this->assertContains('verbosity', $params);
    }

    public function testDiagnoseErrorVerbosityDefault(): void
    {
        $method = new \ReflectionMethod(DiagnosticTools::class, 'diagnoseError');
        $param = $method->getParameters()[4]; // verbosity is 5th param
        $this->assertEquals('standard', $param->getDefaultValue());
    }

    public function testModuleToolsDescriptionMentionsVerbosity(): void
    {
        $method = new \ReflectionMethod(ModuleTools::class, 'listModules');
        $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);
        $instance = $attrs[0]->newInstance();
        $this->assertStringContainsString('verbosity', $instance->description);
    }

    public function testEavToolsDescriptionMentionsVerbosity(): void
    {
        $method = new \ReflectionMethod(EavTools::class, 'getEavAttributes');
        $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);
        $instance = $attrs[0]->newInstance();
        $this->assertStringContainsString('verbosity', $instance->description);
    }

    public function testDiagnosticToolsHasVerbosityParameter(): void
    {
        $method = new \ReflectionMethod(DiagnosticTools::class, 'diagnoseError');
        $params = $method->getParameters();
        $paramNames = array_map(fn(\ReflectionParameter $p) => $p->getName(), $params);
        $this->assertContains('verbosity', $paramNames, 'diagnoseError should have a verbosity parameter');
    }
}
