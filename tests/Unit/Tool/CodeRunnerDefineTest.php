<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\CodeRunnerTools;
use PHPUnit\Framework\TestCase;

class CodeRunnerDefineTest extends TestCase
{
    protected function setUp(): void
    {
        CodeRunnerTools::clearDefinedFunctions();
    }

    protected function tearDown(): void
    {
        CodeRunnerTools::clearDefinedFunctions();
    }

    public function testDefineStoresFunctionName(): void
    {
        $tools = new CodeRunnerTools();
        $result = $tools->execute(
            'function myHelper($x) { return $x * 2; }',
            mode: 'define'
        );

        $this->assertTrue($result['success']);
        $this->assertContains('myHelper', CodeRunnerTools::getDefinedFunctions());
    }

    public function testDefineMultipleFunctions(): void
    {
        $tools = new CodeRunnerTools();
        $result = $tools->execute(
            'function helperA() { return 1; } function helperB() { return 2; }',
            mode: 'define'
        );

        $this->assertTrue($result['success']);
        $this->assertContains('helperA', CodeRunnerTools::getDefinedFunctions());
        $this->assertContains('helperB', CodeRunnerTools::getDefinedFunctions());
    }

    public function testDefineWithNoFunctionReturnsError(): void
    {
        $tools = new CodeRunnerTools();
        $result = $tools->execute(
            'return 42;',
            mode: 'define'
        );

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('No function declarations found', $result['message']);
    }

    public function testDefineLimitEnforced(): void
    {
        $tools = new CodeRunnerTools();

        // Define 20 functions (the limit)
        for ($i = 0; $i < 20; $i++) {
            $result = $tools->execute(
                "function func{$i}() { return {$i}; }",
                mode: 'define'
            );
            $this->assertTrue($result['success'], "Failed defining func{$i}");
        }

        $this->assertCount(20, CodeRunnerTools::getDefinedFunctions());

        // 21st should fail
        $result = $tools->execute(
            'function funcOverflow() { return 999; }',
            mode: 'define'
        );

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Function limit reached', $result['message']);
    }

    public function testClearDefinedFunctionsEmptiesStore(): void
    {
        $tools = new CodeRunnerTools();
        $tools->execute(
            'function testClear() { return 1; }',
            mode: 'define'
        );

        $this->assertNotEmpty(CodeRunnerTools::getDefinedFunctions());

        CodeRunnerTools::clearDefinedFunctions();

        $this->assertEmpty(CodeRunnerTools::getDefinedFunctions());
    }

    public function testDefineValidatesDangerousCode(): void
    {
        $tools = new CodeRunnerTools();
        $result = $tools->execute(
            'function dangerous() { exec("rm -rf /"); }',
            mode: 'define'
        );

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Shell execution functions', $result['message']);
    }

    public function testInvalidModeReturnsError(): void
    {
        $tools = new CodeRunnerTools();
        $result = $tools->execute(
            'return 1;',
            mode: 'invalid'
        );

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid mode', $result['message']);
    }

    public function testGetDefinedFunctionsReturnsNames(): void
    {
        $tools = new CodeRunnerTools();
        $tools->execute(
            'function alpha() { return "a"; }',
            mode: 'define'
        );
        $tools->execute(
            'function beta() { return "b"; }',
            mode: 'define'
        );

        $names = CodeRunnerTools::getDefinedFunctions();
        $this->assertCount(2, $names);
        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);
    }

    public function testDefineReturnsDefinedFunctionsList(): void
    {
        $tools = new CodeRunnerTools();
        $result = $tools->execute(
            'function myFunc() { return 42; }',
            mode: 'define'
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('defined_functions', $result);
        $this->assertContains('myFunc', $result['defined_functions']);
        $this->assertEquals(1, $result['total_defined']);
    }

    public function testMultiFunctionDefineDoesNotDuplicateInPreamble(): void
    {
        $tools = new CodeRunnerTools();

        // Define two functions in a single call
        $tools->execute(
            'function preambleA() { return 1; } function preambleB() { return 2; }',
            mode: 'define'
        );

        // Internal storage has 2 keys but both point to the same code string
        $functions = CodeRunnerTools::getDefinedFunctions();
        $this->assertCount(2, $functions);

        // Use reflection to access the private static $definedFunctions and verify
        // that array_unique reduces the values (proving dedup is needed and works)
        $ref = new \ReflectionClass(CodeRunnerTools::class);
        $prop = $ref->getProperty('definedFunctions');
        $raw = $prop->getValue();

        $this->assertCount(2, $raw, 'Raw array should have 2 entries (one per function name)');
        $unique = array_unique($raw);
        $this->assertCount(1, $unique, 'Unique values should be 1 (same code block stored under both keys)');
    }

    public function testDefineDeclaresCallableGlobalFunction(): void
    {
        (new CodeRunnerTools())->execute(
            'function bricklayerFixtureAlpha($x) { return $x + 41; }',
            mode: 'define'
        );
        $this->assertTrue(function_exists('bricklayerFixtureAlpha'));
        $this->assertSame(42, bricklayerFixtureAlpha(1));
    }

    public function testRedefiningSameNameDoesNotFatalAndWarns(): void
    {
        $tools = new CodeRunnerTools();
        $tools->execute('function bricklayerFixtureBeta() { return 1; }', mode: 'define');

        // Previously this fataled with "Cannot redeclare" on the second execute.
        $result = $tools->execute('function bricklayerFixtureBeta() { return 2; }', mode: 'define');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('warning', $result);
        $this->assertStringContainsString('bricklayerFixtureBeta', $result['warning']);
        $this->assertTrue(function_exists('bricklayerFixtureBeta'));
        $this->assertSame(1, bricklayerFixtureBeta()); // original body wins
    }

    public function testDefineWithSyntaxErrorReturnsErrorAtDefineTime(): void
    {
        $result = (new CodeRunnerTools())->execute(
            'function bricklayerFixtureBroken( { return 1; }',
            mode: 'define'
        );
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Failed to declare', $result['message']);
    }
}
