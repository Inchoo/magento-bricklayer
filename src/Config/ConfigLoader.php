<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Config;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Exception\ConfigurationException;

/**
 * Loads and merges Bricklayer configuration from multiple sources:
 * 1. Default configuration (built-in)
 * 2. Project configuration (.bricklayer.json)
 * 3. Environment variables (highest priority)
 */
class ConfigLoader
{
    private const CONFIG_FILE = '.bricklayer.json';

    private EnvironmentResolver $envResolver;
    private ConfigValidator $validator;
    private ?array $config = null;
    private ?string $projectRoot = null;

    public function __construct(
        ?EnvironmentResolver $envResolver = null,
        ?ConfigValidator $validator = null
    ) {
        $this->envResolver = $envResolver ?? new EnvironmentResolver();
        $this->validator = $validator ?? new ConfigValidator();
    }

    /**
     * @return array<string, mixed>
     * @throws ConfigurationException
     */
    public function load(?string $projectRoot = null): array
    {
        if ($this->config !== null && $this->projectRoot === $projectRoot) {
            return $this->config;
        }

        if ($projectRoot === null) {
            $projectRoot = MagentoBootstrap::getMagentoRoot();
        }

        $this->projectRoot = $projectRoot;

        // Start with defaults
        $config = $this->getDefaultConfig();

        // Merge project configuration if exists
        if ($projectRoot !== null) {
            $projectConfig = $this->loadProjectConfig($projectRoot);
            if ($projectConfig !== null) {
                $config = $this->mergeConfig($config, $projectConfig);
            }
        }

        // Apply environment overrides
        $config = $this->applyEnvironmentOverrides($config);

        // Validate final configuration
        if (!$this->validator->validate($config)) {
            $errors = implode(', ', $this->validator->getErrors());
            throw ConfigurationException::invalidFile(
                $projectRoot . '/' . self::CONFIG_FILE,
                $errors
            );
        }

        $this->config = $config;
        return $config;
    }

    /**
     * Get a specific configuration value using dot-notation (e.g., "tools.code-runner.enabled").
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->config ?? $this->load();
        return $this->getNestedValue($config, $key, $default);
    }

    public function isToolEnabled(string $toolName): bool
    {
        return $this->get("tools.$toolName.enabled", true) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getToolConfig(string $toolName): array
    {
        return $this->get("tools.$toolName", []);
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'tools' => [
                'code-runner' => [
                    'enabled' => true,
                    'allow_write' => false,
                    'max_timeout' => 60,
                ],
                'database-query' => [
                    'enabled' => true,
                    'max_rows' => 100,
                ],
                'log-reader' => [
                    'enabled' => true,
                    'max_lines' => 500,
                ],
                // Catalog write tools
                'product-create' => ['enabled' => true],
                'product-update' => ['enabled' => true],
                'product-delete' => [],
                'product-stock-update' => ['enabled' => true],
                'product-media-add' => ['enabled' => true],
                'product-link-set' => ['enabled' => true],
                'category-create' => ['enabled' => true],
                'category-update' => ['enabled' => true],
                'category-delete' => [],
                'category-assign-products' => ['enabled' => true],
                // Order write tools
                'order-cancel' => [],
                'order-hold' => ['enabled' => true],
                'order-unhold' => ['enabled' => true],
                'order-add-comment' => ['enabled' => true],
                'invoice-create' => ['enabled' => true],
                'shipment-create' => ['enabled' => true],
                'shipment-track-add' => ['enabled' => true],
                'creditmemo-create' => [],
                // Customer write tools
                'customer-create' => ['enabled' => true],
                'customer-update' => ['enabled' => true],
                'customer-delete' => [],
                'customer-address-create' => ['enabled' => true],
                'customer-address-update' => ['enabled' => true],
                'customer-address-delete' => [],
                // Code generation tools
                'generate-module' => [],
                'generate-model' => [],
                'generate-controller' => [],
                'generate-api' => [],
            ],
            'guidelines' => [
                'include' => ['core', 'modules', 'areas', 'patterns'],
                'exclude' => [],
            ],
            'agents' => ['claude-code', 'cursor'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     * @throws ConfigurationException
     */
    private function loadProjectConfig(string $projectRoot): ?array
    {
        $configPath = $projectRoot . '/' . self::CONFIG_FILE;

        if (!file_exists($configPath)) {
            return null;
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            throw ConfigurationException::invalidFile($configPath, 'Unable to read file');
        }

        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ConfigurationException::invalidFile(
                $configPath,
                'Invalid JSON: ' . json_last_error_msg()
            );
        }

        if (!is_array($config)) {
            throw ConfigurationException::invalidFile(
                $configPath,
                'Configuration must be a JSON object'
            );
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                // Check if it's an associative array (merge) or indexed array (replace)
                if ($this->isAssociativeArray($value) && $this->isAssociativeArray($base[$key])) {
                    $base[$key] = $this->mergeConfig($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function applyEnvironmentOverrides(array $config): array
    {
        $envVars = $this->envResolver->getAll();

        foreach ($envVars as $key => $value) {
            $config = $this->setNestedValue($config, $key, $value);
        }

        return $config;
    }

    /**
     * Get a nested configuration value using dot notation
     *
     * @param array<string, mixed> $array The configuration array
     * @param string $key The dot-notation key
     * @param mixed $default Default value if not found
     * @return mixed The value or default
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a nested configuration value using dot notation
     *
     * @param array<string, mixed> $array The configuration array
     * @param string $key The dot-notation key
     * @param mixed $value The value to set
     * @return array<string, mixed> The modified array
     */
    private function setNestedValue(array $array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }

        return $array;
    }

    /**
     * Clear cached configuration
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->config = null;
        $this->projectRoot = null;
    }
}
