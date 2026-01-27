<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Bootstrap;

/**
 * Area Code Emulator
 *
 * Manages Magento area code initialization for proper class resolution.
 * Different areas (adminhtml, frontend, webapi) may have different
 * DI configurations and class preferences.
 */
class AreaEmulator
{
    /**
     * Known Magento area codes
     */
    public const AREA_GLOBAL = 'global';
    public const AREA_ADMINHTML = 'adminhtml';
    public const AREA_FRONTEND = 'frontend';
    public const AREA_WEBAPI_REST = 'webapi_rest';
    public const AREA_WEBAPI_SOAP = 'webapi_soap';
    public const AREA_GRAPHQL = 'graphql';
    public const AREA_CRONTAB = 'crontab';

    /**
     * Default area code for Bricklayer operations
     */
    private const DEFAULT_AREA = self::AREA_ADMINHTML;

    /**
     * @var string|null Current area code
     */
    private ?string $currentArea = null;

    /**
     * @var bool Whether area has been set
     */
    private bool $areaSet = false;

    /**
     * Set the area code
     *
     * @param string $areaCode The area code to set
     * @return void
     */
    public function setArea(string $areaCode): void
    {
        if ($this->areaSet && $this->currentArea === $areaCode) {
            return;
        }

        $objectManager = MagentoBootstrap::getObjectManager();

        try {
            /** @var \Magento\Framework\App\State $state */
            $state = $objectManager->get(\Magento\Framework\App\State::class);
            $state->setAreaCode($areaCode);

            $this->currentArea = $areaCode;
            $this->areaSet = true;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set - get current area
            $this->currentArea = $state->getAreaCode();
            $this->areaSet = true;
        }
    }

    /**
     * Get the current area code
     *
     * @return string|null The current area code, or null if not set
     */
    public function getCurrentArea(): ?string
    {
        if ($this->currentArea !== null) {
            return $this->currentArea;
        }

        try {
            $objectManager = MagentoBootstrap::getObjectManager();
            /** @var \Magento\Framework\App\State $state */
            $state = $objectManager->get(\Magento\Framework\App\State::class);

            try {
                $this->currentArea = $state->getAreaCode();
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                // Area not set yet
                $this->currentArea = null;
            }
        } catch (\Throwable $e) {
            $this->currentArea = null;
        }

        return $this->currentArea;
    }

    /**
     * Get the default area code
     *
     * @return string
     */
    public function getDefaultArea(): string
    {
        return self::DEFAULT_AREA;
    }

    /**
     * Check if an area code is valid
     *
     * @param string $areaCode The area code to check
     * @return bool True if the area code is valid
     */
    public function isValidArea(string $areaCode): bool
    {
        return in_array($areaCode, [
            self::AREA_GLOBAL,
            self::AREA_ADMINHTML,
            self::AREA_FRONTEND,
            self::AREA_WEBAPI_REST,
            self::AREA_WEBAPI_SOAP,
            self::AREA_GRAPHQL,
            self::AREA_CRONTAB,
        ], true);
    }

    /**
     * Execute a callback within a specific area context
     *
     * @template T
     * @param string $areaCode The area code to emulate
     * @param callable(): T $callback The callback to execute
     * @return T The callback result
     */
    public function emulateArea(string $areaCode, callable $callback): mixed
    {
        $previousArea = $this->currentArea;

        try {
            $this->setArea($areaCode);
            return $callback();
        } finally {
            if ($previousArea !== null && $previousArea !== $areaCode) {
                // Note: Magento doesn't support changing area code after it's set
                // This is here for documentation purposes
                $this->currentArea = $previousArea;
            }
        }
    }

    /**
     * Get all available area codes
     *
     * @return array<string>
     */
    public function getAvailableAreas(): array
    {
        return [
            self::AREA_GLOBAL,
            self::AREA_ADMINHTML,
            self::AREA_FRONTEND,
            self::AREA_WEBAPI_REST,
            self::AREA_WEBAPI_SOAP,
            self::AREA_GRAPHQL,
            self::AREA_CRONTAB,
        ];
    }
}
