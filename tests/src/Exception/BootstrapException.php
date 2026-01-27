<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

/**
 * Exception thrown when Magento bootstrap fails
 *
 * This can occur due to configuration errors, database connectivity issues,
 * or other problems during Magento initialization.
 */
class BootstrapException extends BricklayerException
{
    /**
     * Create exception for general bootstrap failure
     *
     * @param string $reason The reason for failure
     * @param \Throwable|null $previous The previous exception
     * @return self
     */
    public static function failed(string $reason, ?\Throwable $previous = null): self
    {
        return new self("Failed to bootstrap Magento: $reason", 0, $previous);
    }

    /**
     * Create exception for area code initialization failure
     *
     * @param string $areaCode The area code that failed to initialize
     * @param \Throwable|null $previous The previous exception
     * @return self
     */
    public static function areaCodeFailed(string $areaCode, ?\Throwable $previous = null): self
    {
        return new self("Failed to initialize area code '$areaCode'", 0, $previous);
    }

    /**
     * Create exception for ObjectManager not initialized
     *
     * @return self
     */
    public static function objectManagerNotInitialized(): self
    {
        return new self(
            'Magento has not been initialized. Call MagentoBootstrap::initialize() first.'
        );
    }
}
