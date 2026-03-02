<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool\Concern;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Config\ConfigLoader;

trait ChecksConfig
{
    private ?ConfigLoader $configLoader = null;

    /**
     * Check if a tool is enabled in configuration. Returns error array if disabled, null if enabled.
     *
     * @return array{error: true, message: string}|null
     */
    private function requireToolEnabled(string $toolName): ?array
    {
        try {
            if (!$this->getConfigLoader()->isToolEnabled($toolName)) {
                return ['error' => true, 'message' => "$toolName is disabled in configuration."];
            }
        } catch (\Throwable $e) {
            // Config loading failed — allow tool to proceed with defaults
        }

        return null;
    }

    /**
     * Check if the current deploy mode is production.
     */
    private function isProductionMode(): bool
    {
        try {
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            return $state->getMode() === \Magento\Framework\App\State::MODE_PRODUCTION;
        } catch (\Throwable $e) {
            // Fail closed: if we can't determine mode, assume production to block destructive tools
            return true;
        }
    }

    /**
     * Block a tool in production mode unless explicitly enabled in config.
     * Returns error array if blocked, null if allowed.
     *
     * @return array{error: true, message: string}|null
     */
    private function requireNonProduction(string $toolName): ?array
    {
        if (!$this->isProductionMode()) {
            return null;
        }

        // Allow if explicitly enabled in config despite production mode
        try {
            $explicitlyEnabled = $this->getConfigLoader()->get("tools.$toolName.enabled", null);
            if ($explicitlyEnabled === true) {
                return null;
            }
        } catch (\Throwable $e) {
            // Config loading failed — block in production by default
        }

        return [
            'error' => true,
            'message' => "$toolName is disabled in production mode. "
                . "Set tools.$toolName.enabled=true in .bricklayer.json to override.",
        ];
    }

    private function getConfigLoader(): ConfigLoader
    {
        return $this->configLoader ??= new ConfigLoader();
    }
}
