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
     * Generate .bricklayer.json if it does not exist.
     *
     * @return array{created: bool, path: string, deploy_mode: string, safety: string}
     */
    public function generate(string $magentoRoot, bool $force = false): array
    {
        $configPath = $magentoRoot . '/' . self::CONFIG_FILE;
        $deployMode = $this->detectDeployMode($magentoRoot);
        $config = $this->buildConfig($deployMode);
        $safety = $config['production_safety'] ?? 'standard';

        if (file_exists($configPath) && !$force) {
            return [
                'created' => false,
                'path' => $configPath,
                'deploy_mode' => $deployMode,
                'safety' => $safety,
            ];
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($configPath, $json);

        return [
            'created' => true,
            'path' => $configPath,
            'deploy_mode' => $deployMode,
            'safety' => $safety,
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
     * @return array<string, mixed>
     */
    public function buildConfig(string $deployMode): array
    {
        if ($deployMode === 'production') {
            return [
                'production_safety' => 'standard',
                'tools' => [
                    'code-runner' => [
                        'enabled' => false,
                        'allow_write' => false,
                    ],
                    'database-query' => [
                        'max_rows' => 50,
                    ],
                    'product-delete' => ['enabled' => false],
                    'category-delete' => ['enabled' => false],
                    'customer-delete' => ['enabled' => false],
                    'customer-address-delete' => ['enabled' => false],
                    'order-cancel' => ['enabled' => false],
                    'creditmemo-create' => ['enabled' => false],
                    'generate-module' => ['enabled' => false],
                    'generate-model' => ['enabled' => false],
                    'generate-controller' => ['enabled' => false],
                    'generate-api' => ['enabled' => false],
                ],
            ];
        }

        // developer / default mode — permissive defaults
        return [
            'production_safety' => 'unrestricted',
            'tools' => [
                'code-runner' => [
                    'allow_write' => false,
                ],
            ],
        ];
    }
}
