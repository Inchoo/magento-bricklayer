<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Exception;

class MagentoNotFoundException extends BricklayerException
{
    public static function noMagentoRoot(?string $searchPath = null): self
    {
        $message = 'Could not detect Magento root directory.';

        if ($searchPath !== null) {
            $message .= " Searched from: $searchPath";
        }

        $message .= ' Please run this command from within a Magento project or specify --magento-root.';

        return new self($message);
    }

    public static function noBootstrapFile(string $path): self
    {
        return new self("Magento bootstrap file not found at: $path");
    }

    public static function noEnvFile(string $path): self
    {
        return new self("Magento env.php configuration file not found at: $path");
    }
}
