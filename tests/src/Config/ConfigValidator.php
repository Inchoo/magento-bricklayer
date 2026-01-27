<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Config;

use Inchoo\MagentoBricklayer\Exception\ConfigurationException;

/**
 * Configuration Validator
 *
 * Validates Bricklayer configuration against the expected schema.
 */
class ConfigValidator
{
    /**
     * Known tool names
     */
    private const KNOWN_TOOLS = [
        'application-info',
        'module-list',
        'module-structure',
        'store-configuration',
        'database-schema',
        'database-query',
        'eav-attributes',
        'eav-entity-types',
        'configuration-get',
        'configuration-list',
        'di-configuration',
        'plugin-list',
        'preference-list',
        'route-list',
        'api-endpoints',
        'graphql-schema',
        'event-list',
        'cache-status',
        'indexer-status',
        'cron-list',
        'log-reader',
        'validate-code',
        'code-runner',
        'product-get',
        'product-list',
        'product-create',
        'product-update',
        'product-delete',
        'product-stock-get',
        'product-stock-update',
        'category-get',
        'category-tree',
        'category-create',
        'order-get',
        'order-list',
        'order-add-comment',
        'order-cancel',
        'order-hold',
        'order-unhold',
        'invoice-create',
        'shipment-create',
        'creditmemo-create',
        'customer-get',
        'customer-list',
        'customer-create',
        'customer-update',
        'customer-delete',
        'customer-address-create',
        'customer-groups-list',
        'customer-orders',
        'search-docs',
    ];

    /**
     * Known agent types
     */
    private const KNOWN_AGENTS = [
        'claude-code',
        'cursor',
        'copilot',
        'phpstorm',
        'gemini',
    ];

    /**
     * Known guideline categories
     */
    private const KNOWN_GUIDELINE_CATEGORIES = [
        'core',
        'modules',
        'areas',
        'patterns',
        'database',
        'ecosystem',
    ];

    /**
     * @var array<string> Validation errors
     */
    private array $errors = [];

    /**
     * @var array<string> Validation warnings
     */
    private array $warnings = [];

    /**
     * Validate configuration array
     *
     * @param array<string, mixed> $config The configuration to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(array $config): bool
    {
        $this->errors = [];
        $this->warnings = [];

        $this->validateTools($config['tools'] ?? []);
        $this->validateGuidelines($config['guidelines'] ?? []);
        $this->validateAgents($config['agents'] ?? []);
        $this->validateDocumentation($config['documentation'] ?? []);

        return empty($this->errors);
    }

    /**
     * Get validation errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation warnings
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Validate tools configuration
     *
     * @param mixed $tools The tools configuration
     * @return void
     */
    private function validateTools(mixed $tools): void
    {
        if (!is_array($tools)) {
            if ($tools !== null && $tools !== []) {
                $this->errors[] = "Configuration 'tools' must be an array";
            }
            return;
        }

        foreach ($tools as $toolName => $toolConfig) {
            if (!is_string($toolName)) {
                $this->errors[] = "Tool name must be a string";
                continue;
            }

            if (!in_array($toolName, self::KNOWN_TOOLS, true)) {
                $this->warnings[] = "Unknown tool: '$toolName'";
            }

            if (!is_array($toolConfig)) {
                $this->errors[] = "Tool configuration for '$toolName' must be an array";
                continue;
            }

            $this->validateToolConfig($toolName, $toolConfig);
        }
    }

    /**
     * Validate individual tool configuration
     *
     * @param string $toolName The tool name
     * @param array<string, mixed> $config The tool configuration
     * @return void
     */
    private function validateToolConfig(string $toolName, array $config): void
    {
        // Validate 'enabled' option
        if (isset($config['enabled']) && !is_bool($config['enabled'])) {
            $this->errors[] = "Tool '$toolName' 'enabled' option must be a boolean";
        }

        // Tool-specific validations
        if ($toolName === 'database-query') {
            if (isset($config['max_rows'])) {
                if (!is_int($config['max_rows']) || $config['max_rows'] < 1) {
                    $this->errors[] = "Tool '$toolName' 'max_rows' must be a positive integer";
                }
                if ($config['max_rows'] > 10000) {
                    $this->warnings[] = "Tool '$toolName' 'max_rows' is very high ($config[max_rows])";
                }
            }
        }

        if ($toolName === 'log-reader') {
            if (isset($config['max_lines'])) {
                if (!is_int($config['max_lines']) || $config['max_lines'] < 1) {
                    $this->errors[] = "Tool '$toolName' 'max_lines' must be a positive integer";
                }
            }
        }
    }

    /**
     * Validate guidelines configuration
     *
     * @param mixed $guidelines The guidelines configuration
     * @return void
     */
    private function validateGuidelines(mixed $guidelines): void
    {
        if (!is_array($guidelines)) {
            if ($guidelines !== null && $guidelines !== []) {
                $this->errors[] = "Configuration 'guidelines' must be an array";
            }
            return;
        }

        // Validate 'include'
        if (isset($guidelines['include'])) {
            if (!is_array($guidelines['include'])) {
                $this->errors[] = "Guidelines 'include' must be an array";
            } else {
                foreach ($guidelines['include'] as $category) {
                    if (!is_string($category)) {
                        $this->errors[] = "Guidelines 'include' entries must be strings";
                        continue;
                    }
                    $baseCategory = explode('/', $category)[0];
                    if (!in_array($baseCategory, self::KNOWN_GUIDELINE_CATEGORIES, true)) {
                        $this->warnings[] = "Unknown guideline category: '$category'";
                    }
                }
            }
        }

        // Validate 'exclude'
        if (isset($guidelines['exclude'])) {
            if (!is_array($guidelines['exclude'])) {
                $this->errors[] = "Guidelines 'exclude' must be an array";
            } else {
                foreach ($guidelines['exclude'] as $category) {
                    if (!is_string($category)) {
                        $this->errors[] = "Guidelines 'exclude' entries must be strings";
                    }
                }
            }
        }
    }

    /**
     * Validate agents configuration
     *
     * @param mixed $agents The agents configuration
     * @return void
     */
    private function validateAgents(mixed $agents): void
    {
        if (!is_array($agents)) {
            if ($agents !== null && $agents !== []) {
                $this->errors[] = "Configuration 'agents' must be an array";
            }
            return;
        }

        foreach ($agents as $agent) {
            if (!is_string($agent)) {
                $this->errors[] = "Agent names must be strings";
                continue;
            }

            if (!in_array($agent, self::KNOWN_AGENTS, true)) {
                $this->warnings[] = "Unknown agent: '$agent'";
            }
        }
    }

    /**
     * Validate documentation configuration
     *
     * @param mixed $documentation The documentation configuration
     * @return void
     */
    private function validateDocumentation(mixed $documentation): void
    {
        if (!is_array($documentation)) {
            if ($documentation !== null && $documentation !== []) {
                $this->errors[] = "Configuration 'documentation' must be an array";
            }
            return;
        }

        if (isset($documentation['index_path'])) {
            if (!is_string($documentation['index_path'])) {
                $this->errors[] = "Documentation 'index_path' must be a string";
            }
        }
    }

    /**
     * Get list of known tools
     *
     * @return array<string>
     */
    public static function getKnownTools(): array
    {
        return self::KNOWN_TOOLS;
    }

    /**
     * Get list of known agents
     *
     * @return array<string>
     */
    public static function getKnownAgents(): array
    {
        return self::KNOWN_AGENTS;
    }
}
