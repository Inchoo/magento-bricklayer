<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

class ConfigurationException extends BricklayerException
{
    public static function invalidFile(string $path, string $reason): self
    {
        return new self("Invalid configuration file at '$path': $reason");
    }

    public static function missingRequired(string $key): self
    {
        return new self("Missing required configuration: $key");
    }

    public static function invalidValue(string $key, mixed $value, string $expected): self
    {
        $type = get_debug_type($value);
        return new self("Invalid value for '$key': got $type, expected $expected");
    }
}
