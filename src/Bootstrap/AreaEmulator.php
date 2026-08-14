<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Bootstrap;

/**
 * Manages Magento area code initialization for proper class resolution.
 */
class AreaEmulator
{
    public const AREA_GLOBAL = 'global';
    public const AREA_ADMINHTML = 'adminhtml';
    public const AREA_FRONTEND = 'frontend';
    public const AREA_WEBAPI_REST = 'webapi_rest';
    public const AREA_WEBAPI_SOAP = 'webapi_soap';
    public const AREA_GRAPHQL = 'graphql';
    public const AREA_CRONTAB = 'crontab';

    private const VALID_AREAS = [
        self::AREA_GLOBAL,
        self::AREA_ADMINHTML,
        self::AREA_FRONTEND,
        self::AREA_WEBAPI_REST,
        self::AREA_WEBAPI_SOAP,
        self::AREA_GRAPHQL,
        self::AREA_CRONTAB,
    ];

    private ?string $currentArea = null;

    public function setArea(string $areaCode): void
    {
        if ($this->currentArea === $areaCode) {
            return;
        }

        $objectManager = MagentoBootstrap::getObjectManager();

        /** @var \Magento\Framework\App\State|null $state */
        $state = null;
        try {
            $state = $objectManager->get(\Magento\Framework\App\State::class);
            $state->setAreaCode($areaCode);
            $this->currentArea = $areaCode;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set - get current area
            $this->currentArea = $state !== null ? $state->getAreaCode() : null;
        }
    }

    public function isValidArea(string $areaCode): bool
    {
        return in_array($areaCode, self::VALID_AREAS, true);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function emulateArea(string $areaCode, callable $callback): mixed
    {
        $this->setArea($areaCode);
        return $callback();
    }

    /**
     * Run $callback inside a temporary area code, restoring the previous area afterward.
     *
     * Unlike setArea()/emulateArea() (which use the one-shot State::setAreaCode and
     * therefore cannot switch area twice in a long-running process), this delegates to
     * State::emulateAreaCode, which swaps the area code for the duration of the callback
     * and restores it on exit. Required for tools that resolve frontend layout while the
     * MCP server process itself is locked to the adminhtml area.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function emulateAreaCode(string $areaCode, callable $callback): mixed
    {
        $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);

        /** @var T */
        return $state->emulateAreaCode($areaCode, $callback);
    }

    /**
     * @return array<string>
     */
    public function getAvailableAreas(): array
    {
        return self::VALID_AREAS;
    }
}
