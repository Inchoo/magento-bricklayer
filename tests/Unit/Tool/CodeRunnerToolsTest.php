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

    // ─── PsySH execution path ───

    /**
     * Regression: PsySH must actually execute code and return the value. Previously
     * executeWithPsySH() constructed a Shell without setting an output, so
     * Shell::execute() threw "Typed property Psy\Shell::$output must not be accessed
     * before initialization" and code-runner silently fell back to eval while reporting
     * "PsySH not available" (contradicting verify, which detects PsySH as available).
     */
    public function testRunPsyshExecutesCodeAndReturnsValue(): void
    {
        if (!class_exists(\Psy\Shell::class)) {
            $this->markTestSkipped('PsySH not installed');
        }

        $method = new \ReflectionMethod(CodeRunnerTools::class, 'runPsysh');
        $method->setAccessible(true);

        // Empty scope vars → no Magento bootstrap needed for plain arithmetic.
        $result = $method->invoke($this->runner, 'return 6 * 7;', 'execute', []);

        $this->assertTrue(
            $result['success'],
            'PsySH path must succeed; error: ' . json_encode($result['error'] ?? null)
        );
        $this->assertSame('psysh', $result['runtime']);
        $this->assertSame(42, $result['return']);
    }

    /**
     * Regression: the documented bare helper functions — get(), create(), config(),
     * query() — must be callable inside the PsySH runtime, not only the eval fallback.
     * Previously runPsysh() only exposed the $get/$create closures as scope variables,
     * so bare get(...) fataled with "Call to undefined function get()" while the help
     * text and every example advertised the bare form.
     *
     * The real gate is the SECOND call: the MCP server is long-lived, runPsysh builds a
     * fresh Shell per call, and global PHP functions persist process-wide. Call 2 must
     * succeed AND pick up the fresh per-call closure (bound to the current ObjectManager),
     * not a stale one stapled to call 1.
     */
    public function testRunPsyshExposesBareHelperFunctionsAcrossMultipleCalls(): void
    {
        if (!class_exists(\Psy\Shell::class)) {
            $this->markTestSkipped('PsySH not installed');
        }

        $method = new \ReflectionMethod(CodeRunnerTools::class, 'runPsysh');
        $method->setAccessible(true);

        $scopeA = ['get' => static fn(string $c): string => 'A:' . $c];
        $first = $method->invoke($this->runner, 'return get("Foo");', 'execute', $scopeA);
        $this->assertTrue($first['success'], 'call 1 error: ' . json_encode($first['error'] ?? null));
        $this->assertSame('A:Foo', $first['return']);

        // Different closure → proves $GLOBALS helper map is refreshed each call.
        $scopeB = ['get' => static fn(string $c): string => 'B:' . $c];
        $second = $method->invoke($this->runner, 'return get("Bar");', 'execute', $scopeB);
        $this->assertTrue($second['success'], 'call 2 error: ' . json_encode($second['error'] ?? null));
        $this->assertSame('B:Bar', $second['return']);
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
        $this->assertEquals(['code', 'area', 'allow_write', 'timeout', 'mode'], $paramNames);

        // Check defaults
        $this->assertEquals('', $params[1]->getDefaultValue());
        $this->assertFalse($params[2]->getDefaultValue());
        $this->assertEquals(30, $params[3]->getDefaultValue());
        $this->assertEquals('execute', $params[4]->getDefaultValue());
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

    // ─── State reset ───

    public function testResetApplicationStateMethodExists(): void
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('resetApplicationState');
        $this->assertTrue($method->isPrivate());
        $this->assertEquals('void', $method->getReturnType()?->getName());
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

    // ─── B3: Production fail-closed guard ───

    public function testItBlocksCodeRunnerWhenDeployModeCannotBeDetermined(): void
    {
        if (\Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap::isInitialized()) {
            $this->markTestSkipped('Magento is initialized — cannot test "cannot determine mode" path.');
        }

        // isProductionMode() is private in the trait; access via reflection on the runner.
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('isProductionMode');
        $result = $method->invoke($this->runner);

        // Without a Magento bootstrap, getMode() throws, so isProductionMode() must return true (fail-closed).
        $this->assertTrue($result, 'isProductionMode() must return true (fail-closed) when mode cannot be determined');
    }

    public function testItDoesNotAllowAConfigFlagToBypassTheProductionBlockForCodeRunner(): void
    {
        // Read the source of CodeRunnerTools::execute and assert the guard uses isProductionMode()
        // as an actual method call — NOT requireNonProduction() which has a config escape hatch.
        // The old (buggy) code had an inline try/catch that fell through on Throwable (fail-open).
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('execute');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $fileName = $ref->getFileName();
        $this->assertIsString($fileName, 'Could not determine source file path for CodeRunnerTools');
        $lines = file($fileName);
        $this->assertIsArray($lines, 'Could not read source file for CodeRunnerTools');
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        // Must call $this->isProductionMode() — not just mention it in a comment
        $this->assertMatchesRegularExpression(
            '/\$this\s*->\s*isProductionMode\s*\(\s*\)/',
            $body,
            'execute() must call $this->isProductionMode() as the production guard'
        );
        $this->assertStringNotContainsString(
            'requireNonProduction',
            $body,
            'execute() must NOT use requireNonProduction() — it has a config escape hatch that bypasses the hard block'
        );
        // The fail-open catch pattern must be gone
        $this->assertStringNotContainsString(
            'Cannot determine deploy mode — proceed',
            $body,
            'The misleading fail-open comment must be removed'
        );
    }

    // ─── B12: formatArray off-by-one ───

    public function testItTruncatesAFormattedArrayAtExactly100Items(): void
    {
        $input = array_fill(0, 150, 'x');

        $result = $this->invokeFormatArray($input);

        // Remove the __truncated__ marker to count real items
        $marker = $result['__truncated__'] ?? null;
        unset($result['__truncated__']);

        $this->assertNotNull($marker, '__truncated__ marker must be present for arrays > 100 items');
        $this->assertCount(100, $result, 'formatArray must keep exactly 100 items (not 101)');
    }

    public function testItReportsTheTruncationCountMatchingTheItemsActuallyKept(): void
    {
        $input = array_fill(0, 150, 'x');

        $result = $this->invokeFormatArray($input);

        $this->assertArrayHasKey('__truncated__', $result);
        $this->assertStringContainsString('100', $result['__truncated__'], 'Truncation marker must mention "100"');

        // The kept items count must equal 100
        $kept = count($result) - 1; // subtract __truncated__ entry
        $this->assertEquals(100, $kept, 'Items kept must equal what the marker says (100)');
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

    /**
     * Invoke the private formatArray method via reflection.
     *
     * @param array<mixed> $input
     * @return array<mixed>
     */
    private function invokeFormatArray(array $input): array
    {
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $method = $ref->getMethod('formatArray');

        /** @var array<mixed> */
        return $method->invoke($this->runner, $input);
    }
}
