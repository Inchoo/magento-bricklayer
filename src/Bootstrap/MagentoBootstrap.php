<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Bootstrap;

use Inchoo\MagentoBricklayer\Exception\BootstrapException;
use Inchoo\MagentoBricklayer\Exception\MagentoNotFoundException;

/**
 * Initializes Magento's ObjectManager from outside the module system.
 * Enables full access to Magento's DI container without requiring
 * module registration, following the pattern established by n98-magerun2.
 */
class MagentoBootstrap
{
    private static ?object $objectManager = null;
    private static ?string $magentoRoot = null;
    private static ?MagentoDetector $detector = null;
    private static ?AreaEmulator $areaEmulator = null;

    /**
     * @throws MagentoNotFoundException When Magento installation cannot be found
     * @throws BootstrapException When bootstrap fails
     */
    public static function initialize(
        ?string $magentoRoot = null,
        string $areaCode = 'adminhtml'
    ): object {
        if (self::$objectManager !== null) {
            return self::$objectManager;
        }

        self::$detector = new MagentoDetector();

        $magentoRoot = $magentoRoot ?? self::$detector->detect();

        if ($magentoRoot === null) {
            throw MagentoNotFoundException::noMagentoRoot(getcwd() ?: null);
        }

        if (!self::$detector->isValidMagentoRoot($magentoRoot)) {
            throw MagentoNotFoundException::noMagentoRoot($magentoRoot);
        }

        self::$magentoRoot = $magentoRoot;

        $bootstrapPath = $magentoRoot . '/app/bootstrap.php';
        if (!file_exists($bootstrapPath)) {
            throw MagentoNotFoundException::noBootstrapFile($bootstrapPath);
        }

        try {
            require_once $bootstrapPath;

            $params = $_SERVER;
            $params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;
            $params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_IS_INSTALLED] = true;

            $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $params);
            self::$objectManager = $bootstrap->getObjectManager();

            self::$areaEmulator = new AreaEmulator();
            self::$areaEmulator->setArea($areaCode);

            return self::$objectManager;
        } catch (\Throwable $e) {
            throw BootstrapException::failed($e->getMessage(), $e);
        }
    }

    /**
     * @throws BootstrapException When Magento has not been initialized
     */
    public static function getObjectManager(): object
    {
        if (self::$objectManager === null) {
            throw BootstrapException::objectManagerNotInitialized();
        }

        return self::$objectManager;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public static function get(string $className): object
    {
        return self::getObjectManager()->get($className);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $arguments
     * @return T
     */
    public static function create(string $className, array $arguments = []): object
    {
        return self::getObjectManager()->create($className, $arguments);
    }

    public static function getMagentoRoot(): ?string
    {
        return self::$magentoRoot;
    }

    public static function isInitialized(): bool
    {
        return self::$objectManager !== null;
    }

    public static function getDetector(): MagentoDetector
    {
        return self::$detector ??= new MagentoDetector();
    }

    public static function getAreaEmulator(): ?AreaEmulator
    {
        return self::$areaEmulator;
    }

    public static function reset(): void
    {
        self::$objectManager = null;
        self::$magentoRoot = null;
        self::$detector = null;
        self::$areaEmulator = null;
    }
}
