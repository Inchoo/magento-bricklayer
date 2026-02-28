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

    /** @var array<string, int|false> mtime snapshot of sentinel files at last (re)init */
    private static array $sentinelMtimes = [];

    /**
     * Files that indicate Magento application state has changed.
     * If any mtime differs from the snapshot taken at bootstrap, the
     * ObjectManager is stale and needs reinitializing.
     */
    private const SENTINEL_FILES = [
        'app/etc/config.php',           // module list — changes on setup:upgrade, module:enable/disable
        'generated/metadata/global.php', // compiled DI — changes on setup:di:compile
    ];

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

            self::snapshotSentinels();

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

    /**
     * Check whether sentinel files have changed since the last (re)init.
     *
     * A single filemtime() per file — negligible cost per tool call.
     */
    public static function isStale(): bool
    {
        if (self::$magentoRoot === null || self::$sentinelMtimes === []) {
            return false;
        }

        foreach (self::SENTINEL_FILES as $relative) {
            $path = self::$magentoRoot . '/' . $relative;
            $currentMtime = @filemtime($path);

            // Compare with snapshot; treat "file appeared" and "mtime changed" as stale
            $snapshotMtime = self::$sentinelMtimes[$relative] ?? false;
            if ($currentMtime !== $snapshotMtime) {
                return true;
            }
        }

        return false;
    }

    /**
     * If stale, reinitialize automatically. Returns true if reinit happened.
     */
    public static function reinitializeIfStale(): bool
    {
        if (!self::isStale()) {
            return false;
        }

        self::reinitialize();
        return true;
    }

    /**
     * Record current mtimes of sentinel files.
     */
    private static function snapshotSentinels(): void
    {
        self::$sentinelMtimes = [];

        if (self::$magentoRoot === null) {
            return;
        }

        foreach (self::SENTINEL_FILES as $relative) {
            $path = self::$magentoRoot . '/' . $relative;
            self::$sentinelMtimes[$relative] = @filemtime($path); // false if missing
        }
    }

    /**
     * Reinitialize Magento with a fresh ObjectManager.
     *
     * Call this after external changes that invalidate the in-memory state:
     * setup:upgrade, setup:di:compile, module:enable, cache:flush, etc.
     *
     * Creates a completely new ObjectManager from current disk state
     * (app/etc/config.php, generated/, merged config.xml, etc.).
     *
     * @throws BootstrapException When Magento was never initialized or reinit fails
     */
    public static function reinitialize(): object
    {
        $magentoRoot = self::$magentoRoot;

        if ($magentoRoot === null) {
            throw BootstrapException::objectManagerNotInitialized();
        }

        // Clear cached ObjectManager so initialize() will re-create it
        self::$objectManager = null;
        self::$areaEmulator = null;
        // Keep $magentoRoot and $detector — they're still valid

        return self::initialize($magentoRoot);
    }

    public static function reset(): void
    {
        self::$objectManager = null;
        self::$magentoRoot = null;
        self::$detector = null;
        self::$areaEmulator = null;
    }
}
