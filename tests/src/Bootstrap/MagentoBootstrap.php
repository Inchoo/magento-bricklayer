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
 * Magento Bootstrap
 *
 * Initializes Magento's ObjectManager from outside the module system.
 * This enables full access to Magento's DI container without requiring
 * module registration, following the pattern established by n98-magerun2.
 */
class MagentoBootstrap
{
    /**
     * @var object|null Magento ObjectManager instance
     */
    private static ?object $objectManager = null;

    /**
     * @var string|null Path to Magento root directory
     */
    private static ?string $magentoRoot = null;

    /**
     * @var bool Whether Magento has been initialized
     */
    private static bool $initialized = false;

    /**
     * @var MagentoDetector|null
     */
    private static ?MagentoDetector $detector = null;

    /**
     * @var AreaEmulator|null
     */
    private static ?AreaEmulator $areaEmulator = null;

    /**
     * Initialize Magento and return ObjectManager
     *
     * @param string|null $magentoRoot Path to Magento root directory (auto-detected if null)
     * @param string $areaCode Area code for class resolution
     * @return object The ObjectManager instance
     * @throws MagentoNotFoundException When Magento installation cannot be found
     * @throws BootstrapException When bootstrap fails
     */
    public static function initialize(
        ?string $magentoRoot = null,
        string $areaCode = 'adminhtml'
    ): object {
        if (self::$initialized && self::$objectManager !== null) {
            return self::$objectManager;
        }

        self::$detector = new MagentoDetector();

        // Detect or validate Magento root
        $magentoRoot = $magentoRoot ?? self::$detector->detect();

        if ($magentoRoot === null) {
            throw MagentoNotFoundException::noMagentoRoot(getcwd() ?: null);
        }

        if (!self::$detector->isValidMagentoRoot($magentoRoot)) {
            throw MagentoNotFoundException::noMagentoRoot($magentoRoot);
        }

        self::$magentoRoot = $magentoRoot;

        // Verify bootstrap file exists
        $bootstrapPath = $magentoRoot . '/app/bootstrap.php';
        if (!file_exists($bootstrapPath)) {
            throw MagentoNotFoundException::noBootstrapFile($bootstrapPath);
        }

        // Initialize Magento
        try {
            // Require Magento bootstrap
            require_once $bootstrapPath;

            // Set up bootstrap parameters
            $params = $_SERVER;
            $params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;
            $params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_IS_INSTALLED] = true;

            // Create bootstrap and get ObjectManager
            $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $params);
            self::$objectManager = $bootstrap->getObjectManager();

            // Initialize area emulator and set area code
            self::$areaEmulator = new AreaEmulator();
            self::$areaEmulator->setArea($areaCode);

            self::$initialized = true;

            return self::$objectManager;
        } catch (\Throwable $e) {
            throw BootstrapException::failed($e->getMessage(), $e);
        }
    }

    /**
     * Get ObjectManager instance
     *
     * @return object The ObjectManager instance
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
     * Get a service from the ObjectManager
     *
     * @template T of object
     * @param class-string<T> $className The class/interface to retrieve
     * @return T The service instance
     * @throws BootstrapException When Magento has not been initialized
     */
    public static function get(string $className): object
    {
        return self::getObjectManager()->get($className);
    }

    /**
     * Create a new instance using the ObjectManager
     *
     * @template T of object
     * @param class-string<T> $className The class to instantiate
     * @param array<string, mixed> $arguments Constructor arguments
     * @return T The new instance
     * @throws BootstrapException When Magento has not been initialized
     */
    public static function create(string $className, array $arguments = []): object
    {
        return self::getObjectManager()->create($className, $arguments);
    }

    /**
     * Get Magento root directory
     *
     * @return string|null The Magento root path, or null if not initialized
     */
    public static function getMagentoRoot(): ?string
    {
        return self::$magentoRoot;
    }

    /**
     * Check if Magento has been initialized
     *
     * @return bool True if initialized
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Get the Magento detector instance
     *
     * @return MagentoDetector
     */
    public static function getDetector(): MagentoDetector
    {
        if (self::$detector === null) {
            self::$detector = new MagentoDetector();
        }

        return self::$detector;
    }

    /**
     * Get the area emulator instance
     *
     * @return AreaEmulator|null The area emulator, or null if not initialized
     */
    public static function getAreaEmulator(): ?AreaEmulator
    {
        return self::$areaEmulator;
    }

    /**
     * Reset the bootstrap state (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$objectManager = null;
        self::$magentoRoot = null;
        self::$initialized = false;
        self::$detector = null;
        self::$areaEmulator = null;
    }
}
