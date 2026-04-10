<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Config;

/**
 * Generates .bricklayer.json with deploy-mode-aware defaults.
 */
class ConfigInitializer
{
    private const CONFIG_FILE = '.bricklayer.json';

    /**
     * Tools that are destructive or hard to reverse. Disabled by default
     * in both developer and production modes — must be explicitly enabled.
     */
    private const DESTRUCTIVE_TOOLS = [
        'product-delete',
        'category-delete',
        'customer-delete',
        'customer-address-delete',
        'order-cancel',
        'creditmemo-create',
        'generate-module',
        'generate-model',
        'generate-controller',
        'generate-api',
    ];

    /** @var array<string>|null */
    private static ?array $configurableToolsCache = null;

    /**
     * Generate .bricklayer.json if it does not exist.
     *
     * @return array{created: bool, path: string, deploy_mode: string, disabled_tools: int}
     */
    public function generate(string $magentoRoot, bool $force = false): array
    {
        $configPath = $magentoRoot . '/' . self::CONFIG_FILE;
        $deployMode = $this->detectDeployMode($magentoRoot);
        $config = $this->buildConfig($deployMode);

        $disabledCount = $this->countDisabledTools($config);

        if (file_exists($configPath) && !$force) {
            return [
                'created' => false,
                'path' => $configPath,
                'deploy_mode' => $deployMode,
                'disabled_tools' => $disabledCount,
            ];
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($configPath, $json);

        return [
            'created' => true,
            'path' => $configPath,
            'deploy_mode' => $deployMode,
            'disabled_tools' => $disabledCount,
        ];
    }

    /**
     * Check whether .bricklayer.json exists at the given root.
     */
    public function exists(string $magentoRoot): bool
    {
        return file_exists($magentoRoot . '/' . self::CONFIG_FILE);
    }

    /**
     * Detect Magento deploy mode from app/etc/env.php without bootstrapping.
     */
    public function detectDeployMode(string $magentoRoot): string
    {
        $envPath = $magentoRoot . '/app/etc/env.php';

        if (!file_exists($envPath)) {
            return 'default';
        }

        try {
            $env = require $envPath;
            if (is_array($env) && isset($env['MAGE_MODE'])) {
                return $env['MAGE_MODE'];
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return 'default';
    }

    /**
     * Build configuration array based on deploy mode.
     *
     * Generates one entry per runtime-configurable tool (discovered by
     * scanning source for `requireToolEnabled()` call sites), so every key
     * the file contains is one the runtime actually honors.
     *
     * @return array<string, mixed>
     */
    public function buildConfig(string $deployMode): array
    {
        $isProduction = $deployMode === 'production';
        $tools = [];

        foreach (self::discoverConfigurableTools() as $tool) {
            $tools[$tool] = $this->buildToolEntry($tool, $isProduction);
        }

        return ['tools' => $tools];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildToolEntry(string $tool, bool $isProduction): array
    {
        $enabled = !in_array($tool, self::DESTRUCTIVE_TOOLS, true);

        switch ($tool) {
            case 'code-runner':
                return [
                    'enabled' => !$isProduction,
                    'allow_write' => false,
                    'max_timeout' => 60,
                ];
            case 'database-query':
                return [
                    'enabled' => true,
                    'max_rows' => $isProduction ? 50 : 100,
                ];
            case 'log':
                return [
                    'enabled' => true,
                    'max_lines' => 500,
                ];
            default:
                return ['enabled' => $enabled];
        }
    }

    /**
     * Discovers tool names that honor the `enabled` config flag by scanning
     * `Mcp/Tool/**\/*.php` for `requireToolEnabled('name')` call sites.
     *
     * Only called at install-time (never on the MCP hot path), so the
     * scan cost is irrelevant.
     *
     * @return array<string>
     */
    public static function discoverConfigurableTools(): array
    {
        if (self::$configurableToolsCache !== null) {
            return self::$configurableToolsCache;
        }

        $toolDir = __DIR__ . '/../Mcp/Tool';

        if (!is_dir($toolDir)) {
            return self::$configurableToolsCache = [];
        }

        $tools = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($toolDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (preg_match_all(
                '/requireToolEnabled\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                $contents,
                $matches
            )) {
                foreach ($matches[1] as $name) {
                    $tools[$name] = true;
                }
            }
        }

        $tools = array_keys($tools);
        sort($tools);

        return self::$configurableToolsCache = $tools;
    }

    /**
     * Reset the cached configurable-tools list. Intended for tests.
     */
    public static function clearConfigurableToolsCache(): void
    {
        self::$configurableToolsCache = null;
    }

    /**
     * Count how many tools are explicitly disabled in the config.
     *
     * @param array<string, mixed> $config
     */
    private function countDisabledTools(array $config): int
    {
        $count = 0;
        foreach ($config['tools'] ?? [] as $toolConfig) {
            if (is_array($toolConfig) && isset($toolConfig['enabled']) && $toolConfig['enabled'] === false) {
                $count++;
            }
        }
        return $count;
    }
}
