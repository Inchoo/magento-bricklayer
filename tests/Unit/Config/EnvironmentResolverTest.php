<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Config;

use Inchoo\MagentoBricklayer\Config\EnvironmentResolver;
use PHPUnit\Framework\TestCase;

class EnvironmentResolverTest extends TestCase
{
    private EnvironmentResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new EnvironmentResolver();
        // Clean up any test environment variables
        $this->cleanupEnv();
    }

    protected function tearDown(): void
    {
        $this->cleanupEnv();
    }

    public function testGetReturnsDefaultWhenEnvNotSet(): void
    {
        $result = $this->resolver->get('some.key', 'default');
        $this->assertEquals('default', $result);
    }

    public function testGetReturnsEnvValue(): void
    {
        putenv('BRICKLAYER_SOME_KEY=test_value');
        $result = $this->resolver->get('some.key');
        $this->assertEquals('test_value', $result);
    }

    public function testGetParsesBooleanTrue(): void
    {
        putenv('BRICKLAYER_BOOL_KEY=true');
        $result = $this->resolver->get('bool.key');
        $this->assertTrue($result);
    }

    public function testGetParsesBooleanFalse(): void
    {
        putenv('BRICKLAYER_BOOL_KEY=false');
        $result = $this->resolver->get('bool.key');
        $this->assertFalse($result);
    }

    public function testGetParsesInteger(): void
    {
        putenv('BRICKLAYER_INT_KEY=42');
        $result = $this->resolver->get('int.key');
        $this->assertSame(42, $result);
    }

    public function testGetParsesFloat(): void
    {
        putenv('BRICKLAYER_FLOAT_KEY=3.14');
        $result = $this->resolver->get('float.key');
        $this->assertSame(3.14, $result);
    }

    public function testGetParsesNull(): void
    {
        putenv('BRICKLAYER_NULL_KEY=null');
        $result = $this->resolver->get('null.key');
        $this->assertNull($result);
    }

    public function testGetParsesJsonArray(): void
    {
        putenv('BRICKLAYER_ARRAY_KEY=["a","b","c"]');
        $result = $this->resolver->get('array.key');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testHasReturnsTrueWhenSet(): void
    {
        putenv('BRICKLAYER_TEST_KEY=value');
        $result = $this->resolver->has('test.key');
        $this->assertTrue($result);
    }

    public function testHasReturnsFalseWhenNotSet(): void
    {
        $result = $this->resolver->has('nonexistent.key');
        $this->assertFalse($result);
    }

    public function testToEnvKeyConvertsCorrectly(): void
    {
        $result = $this->resolver->toEnvKey('tools.code-runner.enabled');
        $this->assertEquals('BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED', $result);
    }

    private function cleanupEnv(): void
    {
        $envVars = [
            'BRICKLAYER_SOME_KEY',
            'BRICKLAYER_BOOL_KEY',
            'BRICKLAYER_INT_KEY',
            'BRICKLAYER_FLOAT_KEY',
            'BRICKLAYER_NULL_KEY',
            'BRICKLAYER_ARRAY_KEY',
            'BRICKLAYER_TEST_KEY',
        ];

        foreach ($envVars as $var) {
            putenv($var);
        }
    }
}
