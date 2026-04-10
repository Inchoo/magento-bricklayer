<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Config;

use Mcp\Capability\Attribute\McpTool;

class ConfigValidator
{
    /** @var array<string>|null */
    private static ?array $knownToolsCache = null;

    private const KNOWN_AGENTS = [
        'claude-code',
        'cursor',
        'copilot',
        'phpstorm',
        'gemini',
    ];

    private const KNOWN_GUIDELINE_CATEGORIES = [
        'core',
        'modules',
        'areas',
        'patterns',
        'database',
        'ecosystem',
    ];

    /** @var array<string> */
    private array $errors = [];

    /** @var array<string> */
    private array $warnings = [];

    /**
     * @param array<string, mixed> $config
     */
    public function validate(array $config): bool
    {
        $this->errors = [];
        $this->warnings = [];

        $this->validateTools($config['tools'] ?? []);
        $this->validateGuidelines($config['guidelines'] ?? []);
        $this->validateAgents($config['agents'] ?? []);

        return empty($this->errors);
    }

    /** @return array<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    private function validateTools(mixed $tools): void
    {
        if (!is_array($tools)) {
            if ($tools !== null) {
                $this->errors[] = "Configuration 'tools' must be an array";
            }
            return;
        }

        foreach ($tools as $toolName => $toolConfig) {
            if (!is_string($toolName)) {
                $this->errors[] = "Tool name must be a string";
                continue;
            }

            if (!in_array($toolName, self::getKnownTools(), true)) {
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
     * @param array<string, mixed> $config
     */
    private function validateToolConfig(string $toolName, array $config): void
    {
        if (isset($config['enabled']) && !is_bool($config['enabled'])) {
            $this->errors[] = "Tool '$toolName' 'enabled' option must be a boolean";
        }

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

        if ($toolName === 'log') {
            if (isset($config['max_lines'])) {
                if (!is_int($config['max_lines']) || $config['max_lines'] < 1) {
                    $this->errors[] = "Tool '$toolName' 'max_lines' must be a positive integer";
                }
            }
        }
    }

    private function validateGuidelines(mixed $guidelines): void
    {
        if (!is_array($guidelines)) {
            if ($guidelines !== null) {
                $this->errors[] = "Configuration 'guidelines' must be an array";
            }
            return;
        }

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

    private function validateAgents(mixed $agents): void
    {
        if (!is_array($agents)) {
            if ($agents !== null) {
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
     * Discovers tool names by reflecting over #[McpTool] attributes on the
     * Mcp\Tool\* classes — the single source of truth for registered tools.
     *
     * @return array<string>
     */
    public static function getKnownTools(): array
    {
        if (self::$knownToolsCache !== null) {
            return self::$knownToolsCache;
        }

        $tools = [];
        $toolDir = __DIR__ . '/../Mcp/Tool';

        if (!is_dir($toolDir)) {
            return self::$knownToolsCache = [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($toolDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($toolDir) + 1, -4);
            $class = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\'
                . str_replace('/', '\\', $relative);

            if (!class_exists($class)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
            } catch (\ReflectionException) {
                continue;
            }

            foreach ($reflection->getMethods() as $method) {
                foreach ($method->getAttributes(McpTool::class) as $attr) {
                    $args = $attr->getArguments();
                    $name = $args['name'] ?? $args[0] ?? null;
                    if (is_string($name) && $name !== '') {
                        $tools[] = $name;
                    }
                }
            }
        }

        sort($tools);
        return self::$knownToolsCache = array_values(array_unique($tools));
    }

    /**
     * Reset the cached tool list. Intended for tests.
     */
    public static function clearKnownToolsCache(): void
    {
        self::$knownToolsCache = null;
    }

    /** @return array<string> */
    public static function getKnownAgents(): array
    {
        return self::KNOWN_AGENTS;
    }
}
