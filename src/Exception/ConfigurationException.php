<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

/**
 * Exception thrown when Bricklayer configuration is invalid
 */
class ConfigurationException extends BricklayerException
{
    /**
     * Create exception for invalid configuration file
     *
     * @param string $path The path to the configuration file
     * @param string $reason The reason it's invalid
     * @return self
     */
    public static function invalidFile(string $path, string $reason): self
    {
        return new self("Invalid configuration file at '$path': $reason");
    }

    /**
     * Create exception for missing required configuration
     *
     * @param string $key The missing configuration key
     * @return self
     */
    public static function missingRequired(string $key): self
    {
        return new self("Missing required configuration: $key");
    }

    /**
     * Create exception for invalid configuration value
     *
     * @param string $key The configuration key
     * @param mixed $value The invalid value
     * @param string $expected What was expected
     * @return self
     */
    public static function invalidValue(string $key, mixed $value, string $expected): self
    {
        $type = get_debug_type($value);
        return new self("Invalid value for '$key': got $type, expected $expected");
    }
}
