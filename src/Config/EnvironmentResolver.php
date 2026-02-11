<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Config;

class EnvironmentResolver
{
    private const ENV_PREFIX = 'BRICKLAYER_';

    public function get(string $key, mixed $default = null): mixed
    {
        $envKey = $this->toEnvKey($key);
        $value = getenv($envKey);

        if ($value === false) {
            return $default;
        }

        return $this->parseValue($value);
    }

    public function has(string $key): bool
    {
        $envKey = $this->toEnvKey($key);
        return getenv($envKey) !== false;
    }

    /** @return array<string, mixed> */
    public function getAll(): array
    {
        $result = [];
        $prefixLen = strlen(self::ENV_PREFIX);

        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, self::ENV_PREFIX)) {
                $configKey = $this->fromEnvKey(substr($key, $prefixLen));
                $result[$configKey] = $this->parseValue($value);
            }
        }

        foreach (getenv() as $key => $value) {
            if (str_starts_with($key, self::ENV_PREFIX)) {
                $configKey = $this->fromEnvKey(substr($key, $prefixLen));
                if (!isset($result[$configKey])) {
                    $result[$configKey] = $this->parseValue($value);
                }
            }
        }

        return $result;
    }

    public function toEnvKey(string $key): string
    {
        return self::ENV_PREFIX . strtoupper(str_replace(['.', '-'], '_', $key));
    }

    private function fromEnvKey(string $envKey): string
    {
        return strtolower(str_replace('_', '.', $envKey));
    }

    private function parseValue(string $value): mixed
    {
        $lower = strtolower($value);

        if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
            return false;
        }
        if ($lower === 'null' || $lower === '') {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }
}
