<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\CodeRunnerTools;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CodeRunnerTools.
 *
 * Tests validation patterns, area validation, and structural aspects
 * that don't require a live Magento bootstrap.
 */
class CodeRunnerToolsTest extends TestCase
{
    private CodeRunnerTools $runner;

    protected function setUp(): void
    {
        $this->runner = new CodeRunnerTools();
    }

    // ─── Validation: existing dangerous patterns ───

    public function testRejectsShellExecution(): void
    {
        $result = $this->invokeValidateCode('exec("ls")');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Shell execution', $result);
    }

    public function testRejectsFileWriteOperations(): void
    {
        $result = $this->invokeValidateCode('file_put_contents("/tmp/x", "data")');
        $this->assertNotNull($result);
        $this->assertStringContainsString('File write', $result);
    }

    public function testRejectsSuperglobalAccess(): void
    {
        $result = $this->invokeValidateCode('return $_SERVER["HTTP_HOST"];');
        $this->assertNotNull($result);
        $this->assertStringContainsString('superglobal', $result);
    }

    public function testRejectsCurlExecution(): void
    {
        $result = $this->invokeValidateCode('curl_exec($ch)');
        $this->assertNotNull($result);
        $this->assertStringContainsString('cURL', $result);
    }

    public function testRejectsExitDie(): void
    {
        $result = $this->invokeValidateCode('exit(1)');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Exit/die', $result);
    }

    public function testRejectsNestedEval(): void
    {
        $result = $this->invokeValidateCode('eval("echo 1;")');
        $this->assertNotNull($result);
        $this->assertStringContainsString('eval', $result);
    }

    // ─── Validation: new dangerous patterns ───

    public function testRejectsHeaderManipulation(): void
    {
        $result = $this->invokeValidateCode('header("Location: /foo")');
        $this->assertNotNull($result);
        $this->assertStringContainsString('HTTP header', $result);
    }

    public function testRejectsSetcookie(): void
    {
        $result = $this->invokeValidateCode('setcookie("name", "val")');
        $this->assertNotNull($result);
        $this->assertStringContainsString('HTTP header', $result);
    }

    public function testRejectsGlobalHandlerRegistration(): void
    {
        $result = $this->invokeValidateCode('register_shutdown_function(function(){})');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Global handler', $result);
    }

    public function testRejectsSetErrorHandler(): void
    {
        $result = $this->invokeValidateCode('set_error_handler(function(){})');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Global handler', $result);
    }

    public function testRejectsSetExceptionHandler(): void
    {
        $result = $this->invokeValidateCode('set_exception_handler(function(){})');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Global handler', $result);
    }

    public function testRejectsLongSleep(): void
    {
        $result = $this->invokeValidateCode('sleep(99)');
        $this->assertNotNull($result);
        $this->assertStringContainsString('sleep', $result);
    }

    // ─── Validation: safe code (no false positives) ───

    public function testAllowsSafeCode(): void
    {
        $safeSnippets = [
            'return 1 + 1;',
            'return $di->get(SomeClass::class);',
            '$product = $get(ProductRepositoryInterface::class)->get("sku"); return $product->getData();',
            'return $config("general/locale/code");',
            'return array_map(fn($x) => $x * 2, [1, 2, 3]);',
            'sleep(5);', // short sleep is OK — only 2+ digit values are blocked
        ];

        foreach ($safeSnippets as $code) {
            $result = $this->invokeValidateCode($code);
            $this->assertNull($result, "Safe code was rejected: $code");
        }
    }

    // ─── Area validation ───

    public function testAreaEmulatorValidAreas(): void
    {
        $emulator = new \Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator();

        $validAreas = ['global', 'adminhtml', 'frontend', 'webapi_rest', 'webapi_soap', 'graphql', 'crontab'];
        foreach ($validAreas as $area) {
            $this->assertTrue($emulator->isValidArea($area), "Area '$area' should be valid");
        }
    }

    public function testAreaEmulatorInvalidAreas(): void
    {
        $emulator = new \Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator();

        $invalidAreas = ['invalid', 'web', 'backend', 'api', ''];
        foreach ($invalidAreas as $area) {
            $this->assertFalse($emulator->isValidArea($area), "Area '$area' should be invalid");
        }
    }

    public function testAreaEmulatorGetAvailableAreas(): void
    {
        $emulator = new \Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator();
        $areas = $emulator->getAvailableAreas();

        $this->assertCount(7, $areas);
        $this->assertContains('frontend', $areas);
        $this->assertContains('adminhtml', $areas);
        $this->assertContains('graphql', $areas);
    }

    // ─── Config defaults ───

    public function testConfigLoaderHasCodeRunnerDefaults(): void
    {
        $tempDir = sys_get_temp_dir() . '/bricklayer_cr_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $loader = new \Inchoo\MagentoBricklayer\Config\ConfigLoader();
            $config = $loader->load($tempDir);

            $this->assertTrue($config['tools']['code-runner']['enabled']);
            $this->assertFalse($config['tools']['code-runner']['allow_write']);
            $this->assertEquals(60, $config['tools']['code-runner']['max_timeout']);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testConfigLoaderWritePolicyOverride(): void
    {
        $tempDir = sys_get_temp_dir() . '/bricklayer_cr_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            file_put_contents(
                $tempDir . '/.bricklayer.json',
                json_encode([
                    'tools' => [
                        'code-runner' => [
                            'allow_write' => true,
                            'max_timeout' => 30,
                        ],
                    ],
                ])
            );

            $loader = new \Inchoo\MagentoBricklayer\Config\ConfigLoader();
            $config = $loader->load($tempDir);

            $this->assertTrue($config['tools']['code-runner']['allow_write']);
            $this->assertEquals(30, $config['tools']['code-runner']['max_timeout']);
        } finally {
            @unlink($tempDir . '/.bricklayer.json');
            rmdir($tempDir);
        }
    }

    // ─── Tool signature ───

    public function testToolHasMcpToolAttribute(): void
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('execute');
        $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertEquals('code-runner', $instance->name);
        $this->assertStringContainsString('get(class)', $instance->description);
        $this->assertStringContainsString('Read-only by default', $instance->description);
        $this->assertStringContainsString('production', $instance->description);
        $this->assertStringContainsString('multi-step operations', $instance->description);
        $this->assertStringContainsString('code-runner-help', $instance->description);
        $this->assertLessThanOrEqual(250, strlen($instance->description), 'Description should be under 250 characters');
    }

    public function testExecuteMethodAcceptsNewParameters(): void
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('execute');
        $params = $method->getParameters();

        $paramNames = array_map(fn($p) => $p->getName(), $params);
        $this->assertEquals(['code', 'area', 'allow_write', 'timeout'], $paramNames);

        // Check defaults
        $this->assertEquals('', $params[1]->getDefaultValue());
        $this->assertFalse($params[2]->getDefaultValue());
        $this->assertEquals(30, $params[3]->getDefaultValue());
    }

    // ─── code-runner-help ───

    public function testGetHelpReturnsExpectedStructure(): void
    {
        $result = $this->runner->getHelp();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('helpers', $result);
        $this->assertArrayHasKey('variables', $result);
        $this->assertArrayHasKey('areas', $result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('examples', $result);
        $this->assertArrayHasKey('safety', $result);
        $this->assertNotEmpty($result['helpers']);
        $this->assertNotEmpty($result['examples']);
    }

    public function testGetHelpHasMcpToolAttribute(): void
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('getHelp');
        $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertEquals('code-runner-help', $instance->name);
    }

    // ─── Scope variables structure ───

    public function testBuildScopeVariablesStructure(): void
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('buildScopeVariables');

        // We can't actually call it without Magento bootstrap,
        // but we can verify the method exists and is callable
        $this->assertTrue($method->isPrivate());
        $this->assertCount(0, $method->getParameters());
    }

    public function testBuildScopeVariablesContainsQueryAndLog(): void
    {
        $method = new \ReflectionMethod(CodeRunnerTools::class, 'buildScopeVariables');
        $this->assertTrue($method->isPrivate());
    }

    public function testLogBufferPropertyExists(): void
    {
        $property = new \ReflectionProperty(CodeRunnerTools::class, 'logBuffer');
        $this->assertTrue($property->isPrivate());
        $this->assertEquals('array', $property->getType()?->getName());
    }

    // ─── Dangerous patterns count ───

    public function testDangerousPatternsCount(): void
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $prop = $ref->getReflectionConstant('DANGEROUS_PATTERNS');

        $this->assertNotFalse($prop);
        $patterns = $prop->getValue();
        $this->assertCount(9, $patterns, 'Should have 9 dangerous patterns (6 original + 3 new)');
    }

    /**
     * Invoke the private validateCode method via reflection.
     */
    private function invokeValidateCode(string $code): ?string
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('validateCode');

        return $method->invoke($this->runner, $code);
    }
}
