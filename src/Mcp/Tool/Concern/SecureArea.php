<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;

trait SecureArea
{
    /**
     * Execute a callable within a secure area context.
     *
     * Registers isSecureArea=true before execution and restores it afterward,
     * which is required for delete operations in Magento.
     */
    private function withSecureArea(callable $fn): mixed
    {
        $registry = MagentoBootstrap::get(\Magento\Framework\Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);

        try {
            return $fn();
        } finally {
            $registry->unregister('isSecureArea');
            $registry->register('isSecureArea', false);
        }
    }
}
