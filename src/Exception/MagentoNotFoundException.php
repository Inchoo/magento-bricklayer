<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

/**
 * Exception thrown when Magento installation cannot be found
 *
 * This typically occurs when running Bricklayer from a directory that is not
 * within a Magento project structure.
 */
class MagentoNotFoundException extends BricklayerException
{
    /**
     * Create exception for missing Magento root
     *
     * @param string|null $searchPath The path that was searched
     * @return self
     */
    public static function noMagentoRoot(?string $searchPath = null): self
    {
        $message = 'Could not detect Magento root directory.';

        if ($searchPath !== null) {
            $message .= " Searched from: $searchPath";
        }

        $message .= ' Please run this command from within a Magento project or specify --magento-root.';

        return new self($message);
    }

    /**
     * Create exception for missing bootstrap file
     *
     * @param string $path The expected path to bootstrap.php
     * @return self
     */
    public static function noBootstrapFile(string $path): self
    {
        return new self("Magento bootstrap file not found at: $path");
    }

    /**
     * Create exception for missing env.php
     *
     * @param string $path The expected path to env.php
     * @return self
     */
    public static function noEnvFile(string $path): self
    {
        return new self("Magento env.php configuration file not found at: $path");
    }
}
