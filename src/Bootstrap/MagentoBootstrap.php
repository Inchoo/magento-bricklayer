<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Bootstrap;

use Inchoo\MagentoBricklayer\Exception\BootstrapException;
use Inchoo\MagentoBricklayer\Exception\MagentoNotFoundException;
use Inchoo\MagentoBricklayer\Support\ComponentRegistration;

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

    /** @var array<string, int|false> mtime snapshot of sentinel files at last (re)init */
    private static array $sentinelMtimes = [];

    /** @var array<string, string[]> files loaded by component re-registration at last reinit */
    private static array $lastReinitStats = [];

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

            // Pick up registration.php files created after this process started.
            // No-op on first init (the autoloader just ran them all); on reinit
            // this is what makes new app/code modules visible to the fresh
            // ObjectManager — Bootstrap::create() alone reuses the process-global
            // ComponentRegistrar registry.
            (new ComponentRegistration())->registerNewComponents($magentoRoot);

            $params = $_SERVER;
            $params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;
            $params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_IS_INSTALLED] = true;

            $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $params);
            self::$objectManager = $bootstrap->getObjectManager();

            $areaEmulator = new AreaEmulator();
            $areaEmulator->setArea($areaCode);

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
     * Before creating the new ObjectManager this re-runs component
     * registration (new registration.php files and Composer autoload files
     * are require_once'd — idempotent, only new files execute) and verifies
     * the registry is consistent with app/etc/config.php. If it is not
     * (e.g. a registered module was deleted from disk — registration cannot
     * be undone in-process), it aborts BEFORE touching any shared cache and
     * keeps the previous ObjectManager, because regenerating merged config
     * from a stale registry would poison the cache for every other process.
     *
     * @throws BootstrapException When Magento was never initialized, the
     *         component registry is irrecoverably stale, or reinit fails
     */
    public static function reinitialize(): object
    {
        $magentoRoot = self::$magentoRoot;

        if ($magentoRoot === null) {
            throw BootstrapException::objectManagerNotInitialized();
        }

        // Must run before anything below re-reads file content: the registry
        // guard includes app/etc/config.php, and the fresh ObjectManager
        // re-includes it plus every generated/compiled file.
        self::invalidateOpcache();

        $registration = new ComponentRegistration();
        self::$lastReinitStats = [
            'registration_files' => $registration->registerNewComponents($magentoRoot),
            'vendor_files' => $registration->registerNewVendorComponents($magentoRoot),
        ];

        $unregistered = $registration->getUnregisteredEnabledModules($magentoRoot);
        $removed = $registration->getRemovedEnabledModules($magentoRoot);

        if ($unregistered !== [] || $removed !== []) {
            // Abort with the old ObjectManager intact — better a loud refusal
            // than silently regenerating shared caches from a stale registry.
            throw BootstrapException::staleComponentRegistry($unregistered, $removed);
        }

        // Clear cached ObjectManager so initialize() will re-create it
        self::$objectManager = null;
        // Keep $magentoRoot — still valid; $detector is re-created by initialize()

        // Note: initialize() uses require_once for the bootstrap file, which won't
        // re-execute on reinit. That is fine — autoloading from the first require
        // persists, new components were registered above, and Bootstrap::create()
        // builds the fresh ObjectManager.
        return self::initialize($magentoRoot);
    }

    /**
     * Drop this process's opcache and stat/realpath caches so reinit reads
     * current file content, not boot-time compiles.
     *
     * In a long-running CLI process opcache never revalidates timestamps
     * when opcache.revalidate_freq > 0: the revalidate window is measured
     * against the request start time, which is frozen at process start, so
     * the window never elapses. app/etc/config.php (and any PHP file edited
     * after it was first included — generated metadata, interceptors,
     * module classes) would otherwise be served in their boot-time versions
     * forever, making a fresh ObjectManager blind to modules enabled
     * mid-session. CLI opcache memory is per-process; invalidating it
     * cannot affect php-fpm or other CLI processes.
     *
     * Every cached script is invalidated individually: opcache_reset()
     * only schedules a restart for the next request, which in a daemon
     * never comes — it returns true and changes nothing. Forced
     * opcache_invalidate() takes effect immediately. Already-loaded class
     * definitions are unaffected either way; only files that get
     * re-included (configs, generated metadata) recompile.
     */
    private static function invalidateOpcache(): void
    {
        if (function_exists('opcache_invalidate') && function_exists('opcache_get_status')) {
            $status = opcache_get_status(true);
            foreach (array_keys(is_array($status) ? ($status['scripts'] ?? []) : []) as $script) {
                opcache_invalidate((string) $script, true);
            }
        }

        clearstatcache(true);
    }

    /**
     * Files loaded by component re-registration during the last reinitialize().
     *
     * @return array<string, string[]> Keys: registration_files, vendor_files
     */
    public static function getLastReinitStats(): array
    {
        return self::$lastReinitStats;
    }

    public static function reset(): void
    {
        self::$objectManager = null;
        self::$magentoRoot = null;
        self::$detector = null;
        self::$sentinelMtimes = [];
        self::$lastReinitStats = [];
    }
}
