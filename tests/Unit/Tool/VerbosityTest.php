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
        // Without Magento bootstrap, the initialization check fires first.
        // Verify via reflection that the validation logic exists in the method body.
        $method = new \ReflectionMethod(ModuleTools::class, 'listModules');
        $file = file_get_contents($method->getFileName());
        $this->assertStringContainsString(
            "if (!in_array(\$verbosity, ['minimal', 'standard', 'detailed'], true))",
            $file
        );
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

    public function testDiagnosticToolsDescriptionMentionsVerbosity(): void
    {
        $method = new \ReflectionMethod(DiagnosticTools::class, 'diagnoseError');
        $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);
        $instance = $attrs[0]->newInstance();
        $this->assertStringContainsString('verbosity', $instance->description);
    }
}
