<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class that uses ChecksConfig trait for testing purposes.
 */
class ChecksConfigTestSubject
{
    use ChecksConfig;

    /**
     * Override to inject a preconfigured ConfigLoader.
     */
    public function setConfigLoader(ConfigLoader $loader): void
    {
        $this->configLoader = $loader;
    }

    /**
     * Expose requireToolEnabled for testing.
     */
    public function testRequireToolEnabled(string $toolName): ?array
    {
        return $this->requireToolEnabled($toolName);
    }

    /**
     * Expose requireNonProduction for testing.
     * Note: isProductionMode() depends on MagentoBootstrap which cannot be easily
     * mocked without the full Magento framework, so we test the config lookup logic
     * indirectly through the method's behavior.
     */
    public function testRequireNonProduction(string $toolName): ?array
    {
        return $this->requireNonProduction($toolName);
    }

    /**
     * Expose isProductionMode for testing.
     */
    public function testIsProductionMode(): bool
    {
        return $this->isProductionMode();
    }
}

class ChecksConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_checks_config_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testRequireToolEnabledReturnsNullWhenEnabled(): void
    {
        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $subject = new ChecksConfigTestSubject();
        $subject->setConfigLoader($loader);

        // Default config: product-create is enabled
        $result = $subject->testRequireToolEnabled('product-create');
        $this->assertNull($result);
    }

    public function testRequireToolEnabledReturnsErrorWhenDisabled(): void
    {
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode(['tools' => ['product-create' => ['enabled' => false]]])
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $subject = new ChecksConfigTestSubject();
        $subject->setConfigLoader($loader);

        $result = $subject->testRequireToolEnabled('product-create');
        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('product-create', $result['message']);
        $this->assertStringContainsString('disabled', $result['message']);
    }

    public function testRequireToolEnabledReturnsNullForUnknownTool(): void
    {
        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $subject = new ChecksConfigTestSubject();
        $subject->setConfigLoader($loader);

        // Unknown tool defaults to enabled
        $result = $subject->testRequireToolEnabled('nonexistent-tool');
        $this->assertNull($result);
    }

    public function testIsProductionModeReturnsTrueWhenMagentoNotInitialized(): void
    {
        if (MagentoBootstrap::isInitialized()) {
            $this->markTestSkipped('Magento is initialized — cannot test "not initialized" path.');
        }

        $subject = new ChecksConfigTestSubject();

        // Without Magento bootstrap, should return true (fail closed)
        $result = $subject->testIsProductionMode();
        $this->assertTrue($result);
    }

    public function testRequireNonProductionBlocksWhenMagentoNotInitialized(): void
    {
        if (MagentoBootstrap::isInitialized()) {
            $this->markTestSkipped('Magento is initialized — cannot test "not initialized" path.');
        }

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $subject = new ChecksConfigTestSubject();
        $subject->setConfigLoader($loader);

        // Without Magento initialized, isProductionMode() returns true (fail closed)
        // so requireNonProduction should return error (blocked)
        $result = $subject->testRequireNonProduction('product-delete');
        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('product-delete', $result['message']);
        $this->assertStringContainsString('production mode', $result['message']);
    }

    public function testDestructiveToolsHaveNoDefaultEnabled(): void
    {
        // Verify that the 11 destructive tools don't have 'enabled' in defaults
        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $destructiveTools = [
            'product-delete',
            'category-delete',
            'customer-delete',
            'customer-address-delete',
            'order-cancel',
            'order-create',
            'creditmemo-create',
            'generate-module',
            'generate-model',
            'generate-controller',
            'generate-api',
        ];

        foreach ($destructiveTools as $tool) {
            // get() with null default should return null for the enabled key
            // because these tools no longer have 'enabled' => true in defaults
            $enabled = $loader->get("tools.$tool.enabled", null);
            $this->assertNull(
                $enabled,
                "Destructive tool '$tool' should not have a default 'enabled' value"
            );
        }
    }

    public function testNonDestructiveToolsStillHaveDefaultEnabled(): void
    {
        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $nonDestructiveTools = [
            'product-create',
            'product-update',
            'product-stock-update',
            'category-create',
            'customer-create',
            'order-hold',
        ];

        foreach ($nonDestructiveTools as $tool) {
            $enabled = $loader->get("tools.$tool.enabled", null);
            $this->assertTrue(
                $enabled,
                "Non-destructive tool '$tool' should have default 'enabled' => true"
            );
        }
    }

    public function testExplicitEnableTrueOverridesDestructiveDefault(): void
    {
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode(['tools' => ['product-delete' => ['enabled' => true]]])
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $enabled = $loader->get("tools.product-delete.enabled", null);
        $this->assertTrue($enabled);
    }

    public function testExplicitDisableOverridesDefault(): void
    {
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode(['tools' => ['product-create' => ['enabled' => false]]])
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);

        $enabled = $loader->get("tools.product-create.enabled", null);
        $this->assertFalse($enabled);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
