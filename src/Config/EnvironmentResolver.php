<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Config;

/**
 * Environment Variable Resolver
 *
 * Resolves configuration values from environment variables.
 * Environment variables take precedence over file-based configuration.
 */
class EnvironmentResolver
{
    /**
     * Environment variable prefix for Bricklayer settings
     */
    private const ENV_PREFIX = 'BRICKLAYER_';

    /**
     * Get an environment variable value
     *
     * @param string $key The configuration key (without prefix)
     * @param mixed $default Default value if not set
     * @return mixed The environment variable value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $envKey = $this->toEnvKey($key);
        $value = getenv($envKey);

        if ($value === false) {
            return $default;
        }

        return $this->parseValue($value);
    }

    /**
     * Check if an environment variable is set
     *
     * @param string $key The configuration key (without prefix)
     * @return bool True if the environment variable is set
     */
    public function has(string $key): bool
    {
        $envKey = $this->toEnvKey($key);
        return getenv($envKey) !== false;
    }

    /**
     * Get all Bricklayer environment variables
     *
     * @return array<string, mixed> Key-value pairs of all Bricklayer env vars
     */
    public function getAll(): array
    {
        $result = [];
        $prefix = self::ENV_PREFIX;
        $prefixLen = strlen($prefix);

        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $configKey = $this->fromEnvKey(substr($key, $prefixLen));
                $result[$configKey] = $this->parseValue($value);
            }
        }

        // Also check getenv() for vars not in $_ENV
        foreach (getenv() as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $configKey = $this->fromEnvKey(substr($key, $prefixLen));
                if (!isset($result[$configKey])) {
                    $result[$configKey] = $this->parseValue($value);
                }
            }
        }

        return $result;
    }

    /**
     * Convert a configuration key to environment variable name
     *
     * @param string $key The configuration key (e.g., "tools.database-query.enabled")
     * @return string The environment variable name (e.g., "BRICKLAYER_TOOLS_DATABASE_QUERY_ENABLED")
     */
    public function toEnvKey(string $key): string
    {
        $key = str_replace(['.', '-'], '_', $key);
        $key = strtoupper($key);
        return self::ENV_PREFIX . $key;
    }

    /**
     * Convert an environment variable name (without prefix) to configuration key
     *
     * @param string $envKey The environment variable name without prefix
     * @return string The configuration key
     */
    private function fromEnvKey(string $envKey): string
    {
        return strtolower(str_replace('_', '.', $envKey));
    }

    /**
     * Parse an environment variable value to the appropriate type
     *
     * @param string $value The raw string value
     * @return mixed The parsed value
     */
    private function parseValue(string $value): mixed
    {
        // Boolean values
        $lower = strtolower($value);
        if ($lower === 'true' || $lower === '1' || $lower === 'yes' || $lower === 'on') {
            return true;
        }
        if ($lower === 'false' || $lower === '0' || $lower === 'no' || $lower === 'off') {
            return false;
        }

        // Null
        if ($lower === 'null' || $lower === '') {
            return null;
        }

        // Numeric values
        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        // JSON arrays/objects
        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Plain string
        return $value;
    }
}
