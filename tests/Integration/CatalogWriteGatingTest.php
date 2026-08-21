<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Inchoo\MagentoBricklayer\Mcp\Tool\CatalogTools;

/**
 * Exercises three mechanisms at once against a real installation: entity
 * writes through the repository layer, the per-tool config gate, and the
 * mtime-based hot reload (the second delete uses the SAME tool instance,
 * so it only succeeds if reloadIfStale() picks up the config edit).
 *
 * The config file is edited with explicit future mtimes because filesystem
 * mtime granularity is one second; same-second edits would be invisible to
 * the staleness check and make the test flaky.
 */
final class CatalogWriteGatingTest extends IntegrationTestCase
{
    private string $configPath;
    private ?string $originalConfig = null;
    private bool $configExisted = false;

    protected function setUp(): void
    {
        $this->configPath = self::magentoRoot() . '/.bricklayer.json';
        $this->configExisted = file_exists($this->configPath);

        if ($this->configExisted) {
            $contents = file_get_contents($this->configPath);
            $this->originalConfig = $contents === false ? null : $contents;
        }
    }

    protected function tearDown(): void
    {
        if ($this->configExisted && $this->originalConfig !== null) {
            file_put_contents($this->configPath, $this->originalConfig);
        } elseif (!$this->configExisted && file_exists($this->configPath)) {
            unlink($this->configPath);
        }

        if (file_exists($this->configPath)) {
            touch($this->configPath, time() + 10);
        }
    }

    public function testProductDeleteGateBlocksAndHotReloadUnblocks(): void
    {
        $sku = 'bricklayer-ci-' . bin2hex(random_bytes(4));
        $this->writeGateConfig(['product-delete' => ['enabled' => false]], 2);

        $tools = new CatalogTools();

        try {
            $created = $tools->createProduct($sku, 'Bricklayer CI product', 9.99);
            self::assertToolSuccess($created);
            self::assertSame(true, $created['success'] ?? null);

            $blocked = $tools->deleteProduct($sku);
            self::assertSame(true, $blocked['error'] ?? null, 'product-delete must be blocked while gated off');
            self::assertStringContainsString('disabled', self::stringValue($blocked, 'message'));

            $this->writeGateConfig(['product-delete' => ['enabled' => true]], 5);

            $deleted = $tools->deleteProduct($sku);
            self::assertToolSuccess($deleted);
            self::assertSame(true, $deleted['success'] ?? null, 'Hot reload should have re-enabled the tool');
        } finally {
            $this->writeGateConfig(['product-delete' => ['enabled' => true]], 8);
            (new CatalogTools())->deleteProduct($sku);
        }
    }

    /**
     * Merge per-tool settings into .bricklayer.json and push its mtime into
     * the future so the staleness check sees the edit immediately.
     *
     * @param array<string, array<string, bool>> $tools
     */
    private function writeGateConfig(array $tools, int $mtimeOffset): void
    {
        $config = [];

        if (file_exists($this->configPath)) {
            $raw = file_get_contents($this->configPath);
            $decoded = $raw === false ? null : json_decode($raw, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        $toolsSection = $config['tools'] ?? null;
        if (!is_array($toolsSection)) {
            $toolsSection = [];
        }

        foreach ($tools as $name => $settings) {
            $existing = $toolsSection[$name] ?? null;
            $toolsSection[$name] = array_merge(is_array($existing) ? $existing : [], $settings);
        }

        $config['tools'] = $toolsSection;

        file_put_contents(
            $this->configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        touch($this->configPath, time() + $mtimeOffset);
    }
}
