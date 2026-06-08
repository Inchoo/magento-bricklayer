<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;

/**
 * Centralizes the package-root / Magento-root constructor logic shared by
 * SearchTools and ContextTools.
 *
 * The using class must call initPackagePaths() in its constructor to populate
 * $this->packageRoot and $this->magentoRootOverride.
 */
trait ResolvesPackagePaths
{
    private string $packageRoot;
    private ?string $magentoRootOverride;

    /**
     * Initialise the two path properties.
     *
     * Call this from the host class constructor:
     *   $this->initPackagePaths($magentoRoot, $packageRoot);
     *
     * @param string|null $magentoRoot  Optional explicit Magento root override (used in tests).
     * @param string|null $packageRoot  Optional explicit package root (defaults to dirname(__DIR__, 3)).
     */
    private function initPackagePaths(?string $magentoRoot, ?string $packageRoot): void
    {
        $this->magentoRootOverride = $magentoRoot !== null ? rtrim($magentoRoot, '/\\') : null;
        $this->packageRoot = $packageRoot !== null
            ? rtrim($packageRoot, '/\\')
            : dirname(__DIR__, 4);
    }

    /**
     * Resolve the effective Magento root path, preferring an override passed
     * into the constructor (used in CLI/test contexts) and falling back to
     * MagentoBootstrap::getMagentoRoot() for live MCP invocations.
     */
    private function resolveMagentoRoot(): ?string
    {
        if ($this->magentoRootOverride !== null) {
            return $this->magentoRootOverride;
        }

        $root = MagentoBootstrap::getMagentoRoot();
        return $root !== null ? rtrim($root, '/\\') : null;
    }
}
