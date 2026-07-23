<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Support;

/**
 * Keeps Magento's process-global component registry in sync with disk.
 *
 * Component registration (registration.php files) runs once per PHP process
 * via Composer's autoload "files" hook. A long-lived MCP server therefore
 * never sees modules created after startup: a fresh ObjectManager reuses the
 * stale ComponentRegistrar static registry, config readers silently skip the
 * new module's etc/ files, and regenerated merged-config caches poison the
 * shared cache storage for other processes.
 *
 * This class re-runs the registration file globs idempotently (require_once
 * skips files loaded earlier, executes only new ones) and detects
 * enabled-vs-registered mismatches that require a full process restart.
 */
class ComponentRegistration
{
    private const GLOBLIST_FILE = 'app/etc/registration_globlist.php';
    private const CONFIG_FILE = 'app/etc/config.php';
    private const VENDOR_AUTOLOAD_FILES = 'vendor/composer/autoload_files.php';

    /**
     * Require registration files matching the Magento globlist that have not
     * been loaded in this process yet.
     *
     * @return string[] Absolute paths of newly loaded files
     */
    public function registerNewComponents(string $magentoRoot): array
    {
        return $this->requireNewFiles($this->collectGloblistFiles($magentoRoot));
    }

    /**
     * Require Composer autoload "files" entries that have not been loaded in
     * this process yet (picks up components installed via Composer after the
     * process started).
     *
     * @return string[] Absolute paths of newly loaded files
     */
    public function registerNewVendorComponents(string $magentoRoot): array
    {
        $autoloadFiles = $magentoRoot . '/' . self::VENDOR_AUTOLOAD_FILES;
        if (!is_file($autoloadFiles)) {
            return [];
        }

        $map = require $autoloadFiles;
        if (!is_array($map)) {
            return [];
        }

        return $this->requireNewFiles(array_values($map));
    }

    /**
     * Module names enabled in app/etc/config.php.
     *
     * @return string[]
     */
    public function getEnabledModules(string $magentoRoot): array
    {
        $configPath = $magentoRoot . '/' . self::CONFIG_FILE;
        if (!is_file($configPath)) {
            return [];
        }

        $config = include $configPath;
        if (!is_array($config) || !isset($config['modules']) || !is_array($config['modules'])) {
            return [];
        }

        return array_keys(array_filter($config['modules']));
    }

    /**
     * Module names (and their paths) registered in the process-global
     * ComponentRegistrar. Empty when the Magento framework is not loaded.
     *
     * @return array<string, string> Module name => absolute path
     */
    public function getRegisteredModules(): array
    {
        if (!class_exists(\Magento\Framework\Component\ComponentRegistrar::class)) {
            return [];
        }

        $registrar = new \Magento\Framework\Component\ComponentRegistrar();

        return $registrar->getPaths(\Magento\Framework\Component\ComponentRegistrar::MODULE);
    }

    /**
     * Modules enabled in config.php but missing from the component registry.
     * Non-empty after registerNewComponents() means the process registry is
     * stale in a way only a restart can fix.
     *
     * @return string[]
     */
    public function getUnregisteredEnabledModules(string $magentoRoot): array
    {
        $registered = $this->getRegisteredModules();
        if ($registered === []) {
            // Magento framework not loaded — cannot judge, do not cry wolf.
            return [];
        }

        return array_values(array_diff(
            $this->getEnabledModules($magentoRoot),
            array_keys($registered)
        ));
    }

    /**
     * Modules still present in the component registry whose directory no
     * longer exists on disk. Registration cannot be undone in-process, so
     * these require a restart.
     *
     * @return string[]
     */
    public function getRemovedRegisteredModules(): array
    {
        $removed = [];
        foreach ($this->getRegisteredModules() as $name => $path) {
            if (!is_dir($path)) {
                $removed[] = $name;
            }
        }

        return $removed;
    }

    /**
     * Removed modules that are still enabled in config.php — the only removed
     * modules that can corrupt regenerated config. A registered-but-removed
     * module that is disabled is ignored by Magento's config readers and is
     * therefore harmless.
     *
     * @return string[]
     */
    public function getRemovedEnabledModules(string $magentoRoot): array
    {
        return array_values(array_intersect(
            $this->getRemovedRegisteredModules(),
            $this->getEnabledModules($magentoRoot)
        ));
    }

    /**
     * @return string[] Absolute paths matched by the registration globlist
     */
    private function collectGloblistFiles(string $magentoRoot): array
    {
        $globlistPath = $magentoRoot . '/' . self::GLOBLIST_FILE;
        if (!is_file($globlistPath)) {
            return [];
        }

        $patterns = require $globlistPath;
        if (!is_array($patterns)) {
            return [];
        }

        $files = [];
        foreach ($patterns as $pattern) {
            $matches = glob($magentoRoot . '/' . $pattern, GLOB_NOSORT);
            foreach ($matches !== false ? $matches : [] as $match) {
                $files[] = $match;
            }
        }

        return $files;
    }

    /**
     * require_once every file not already included in this process.
     *
     * require_once is naturally idempotent per file: previously loaded files
     * are skipped (no duplicate ComponentRegistrar::register() LogicException
     * possible), new files execute and register their components.
     *
     * @param string[] $files
     * @return string[] Absolute paths of newly loaded files
     */
    private function requireNewFiles(array $files): array
    {
        $included = array_flip(get_included_files());
        $new = [];

        foreach ($files as $file) {
            $real = realpath($file);
            if ($real === false || isset($included[$real])) {
                continue;
            }

            require_once $real;
            $new[] = $real;
        }

        return $new;
    }
}
