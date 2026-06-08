<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Config;

use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Config\EnvironmentResolver;
use PHPUnit\Framework\TestCase;

class EnvOverrideReversibleTest extends TestCase
{
    private string $tempDir;

    /** @var list<string> */
    private array $envVarsToClean = [];

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_env_reversible_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->envVarsToClean = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->envVarsToClean as $var) {
            putenv($var);
        }

        $this->removeDirectory($this->tempDir);
    }

    public function testItAppliesAHyphenatedToolOverrideFromAnEnvironmentVariable(): void
    {
        $this->setEnv('BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED', 'false');

        $loader = new ConfigLoader();
        $config = $loader->load($this->tempDir);

        $this->assertFalse($config['tools']['code-runner']['enabled']);
    }

    public function testItDisablesCodeRunnerWhenBricklayerToolsCodeRunnerEnabledIsFalse(): void
    {
        $this->setEnv('BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED', 'false');

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $this->assertFalse($loader->isToolEnabled('code-runner'));
    }

    public function testItStillAppliesSingleWordConfigOverridesUnchanged(): void
    {
        $this->setEnv('BRICKLAYER_TOOLS_LOG_ENABLED', 'false');

        $loader = new ConfigLoader();
        $config = $loader->load($this->tempDir);

        $this->assertFalse($config['tools']['log']['enabled']);
    }

    public function testItRoundTripsEveryDefaultToolKeyThroughToEnvKeyBackToTheSameConfigKey(): void
    {
        $resolver = new EnvironmentResolver();

        $loader = new ConfigLoader(null, null);
        $defaultConfig = $loader->load($this->tempDir);

        $tools = $defaultConfig['tools'] ?? [];
        $this->assertNotEmpty($tools, 'Default tools must not be empty');

        foreach (array_keys($tools) as $toolName) {
            $configKey = "tools.{$toolName}.enabled";
            $envKey = $resolver->toEnvKey($configKey);

            $this->setEnv($envKey, 'true');

            $freshLoader = new ConfigLoader();
            $result = $freshLoader->load($this->tempDir);

            $this->assertSame(
                true,
                $result['tools'][$toolName]['enabled'] ?? null,
                "Expected tools.{$toolName}.enabled to be true via env var {$envKey}"
            );

            putenv($envKey);
            $this->envVarsToClean = array_filter($this->envVarsToClean, fn($v) => $v !== $envKey);
        }
    }

    public function testItDisablesAnEmptyDefaultDeleteToolViaTheEnabledEnvVar(): void
    {
        // product-delete defaults to an empty array (no overrides). An operator must still
        // be able to lock it down via the documented _ENABLED form.
        $this->setEnv('BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED', 'false');

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $this->assertFalse(
            $loader->isToolEnabled('product-delete'),
            'BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED=false must disable the product-delete tool'
        );
    }

    public function testTheBareToolEnvVarDoesNotShadowTheEnabledReadPath(): void
    {
        // Setting the (unsupported) bare form must NOT write a scalar at tools.product-delete
        // that shadows the tools.product-delete.enabled read path — it must fail safe, not open.
        $this->setEnv('BRICKLAYER_TOOLS_PRODUCT_DELETE', 'false');

        $loader = new ConfigLoader();
        $config = $loader->load($this->tempDir);

        $this->assertIsArray(
            $config['tools']['product-delete'],
            'The bare env var must not overwrite the tool container with a scalar'
        );
    }

    public function testItLeavesConfigUntouchedWhenNoMatchingBricklayerVarIsSet(): void
    {
        $loader = new ConfigLoader();
        $config = $loader->load($this->tempDir);

        $this->assertTrue($config['tools']['code-runner']['enabled']);
        $this->assertTrue($config['tools']['log']['enabled']);
    }

    public function testItGivesAnEnvironmentOverridePrecedenceOverTheProjectConfigFileValue(): void
    {
        $projectConfig = [
            'tools' => [
                'code-runner' => [
                    'enabled' => false,
                ],
            ],
        ];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($projectConfig)
        );

        $this->setEnv('BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED', 'true');

        $loader = new ConfigLoader();
        $config = $loader->load($this->tempDir);

        $this->assertTrue($config['tools']['code-runner']['enabled']);
    }

    private function setEnv(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $this->envVarsToClean[] = $name;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $scan = scandir($dir);
        if ($scan === false) {
            return;
        }

        $files = array_diff($scan, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
