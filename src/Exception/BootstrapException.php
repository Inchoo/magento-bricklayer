<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

class BootstrapException extends BricklayerException
{
    public static function failed(string $reason, ?\Throwable $previous = null): self
    {
        return new self("Failed to bootstrap Magento: $reason", 0, $previous);
    }

    public static function areaCodeFailed(string $areaCode, ?\Throwable $previous = null): self
    {
        return new self("Failed to initialize area code '$areaCode'", 0, $previous);
    }

    public static function objectManagerNotInitialized(): self
    {
        return new self(
            'Magento has not been initialized. Call MagentoBootstrap::initialize() first.'
        );
    }
}
