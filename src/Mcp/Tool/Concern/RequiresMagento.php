<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;

trait RequiresMagento
{
    /**
     * Assert that Magento is initialized, returning a standard error array if not.
     *
     * Also auto-reinitializes when sentinel files have changed on disk
     * (e.g. after setup:upgrade or setup:di:compile ran externally).
     *
     * @return array{error: true, message: string}|null Null if initialized, error array if not.
     */
    private function requireMagento(): ?array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        MagentoBootstrap::reinitializeIfStale();

        return null;
    }
}
