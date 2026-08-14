<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Bootstrap;

/**
 * Detects Magento root directory by searching for marker files.
 * Supports detection from any subdirectory within a Magento project.
 */
class MagentoDetector
{
    private const MARKER_FILES = [
        'app/bootstrap.php',
        'app/etc/env.php',
        'bin/magento',
    ];

    private const MAX_SEARCH_DEPTH = 15;

    public function detect(?string $startPath = null): ?string
    {
        $currentDir = $startPath ?? getcwd();

        if ($currentDir === false) {
            return null;
        }

        $currentDir = realpath($currentDir);
        if ($currentDir === false) {
            return null;
        }

        for ($depth = 0; $depth < self::MAX_SEARCH_DEPTH; $depth++) {
            if ($this->isValidMagentoRoot($currentDir)) {
                return $currentDir;
            }

            $parentDir = dirname($currentDir);

            if ($parentDir === $currentDir) {
                break;
            }

            $currentDir = $parentDir;
        }

        return null;
    }

    public function isValidMagentoRoot(string $path): bool
    {
        foreach (self::MARKER_FILES as $marker) {
            if (!file_exists($path . DIRECTORY_SEPARATOR . $marker)) {
                return false;
            }
        }

        return true;
    }

    public function getVersion(string $magentoRoot): ?string
    {
        $require = $this->getComposerRequire($magentoRoot);
        if ($require === null) {
            return null;
        }

        foreach (['magento/product-community-edition', 'magento/product-enterprise-edition'] as $package) {
            if (isset($require[$package])) {
                return $this->parseVersionConstraint($require[$package]);
            }
        }

        if (isset($require['magento/framework'])) {
            return $this->parseVersionConstraint($require['magento/framework']);
        }

        return null;
    }

    /**
     * @return string 'community', 'enterprise', or 'unknown'
     */
    public function getEdition(string $magentoRoot): string
    {
        $require = $this->getComposerRequire($magentoRoot);
        if ($require === null) {
            return 'unknown';
        }

        if (isset($require['magento/product-enterprise-edition'])) {
            return 'enterprise';
        }

        if (isset($require['magento/product-community-edition'])) {
            return 'community';
        }

        if (isset($require['magento/module-b2b'])) {
            return 'enterprise';
        }

        return 'unknown';
    }

    /**
     * @return string 'ddev', 'hooli', 'warden', 'docker-compose', 'docker', or 'native'
     */
    public function getEnvironmentType(string $magentoRoot): string
    {
        if (file_exists($magentoRoot . '/.ddev/config.yaml')) {
            return 'ddev';
        }

        $parentDir = dirname($magentoRoot);
        if (file_exists($parentDir . '/hooli') && file_exists($parentDir . '/docker-compose.yml')) {
            return 'hooli';
        }

        if (
            file_exists($magentoRoot . '/.warden/warden-env.yml') ||
            file_exists($magentoRoot . '/.env.warden')
        ) {
            return 'warden';
        }

        if (
            file_exists($magentoRoot . '/docker-compose.yml') ||
            file_exists($magentoRoot . '/compose.yml') ||
            file_exists($magentoRoot . '/docker-compose.yaml')
        ) {
            return 'docker-compose';
        }

        if (file_exists('/.dockerenv') || getenv('DOCKER_CONTAINER') !== false) {
            return 'docker';
        }

        return 'native';
    }

    /**
     * @return array<string, string>|null
     */
    private function getComposerRequire(string $magentoRoot): ?array
    {
        $composerPath = $magentoRoot . '/composer.json';
        if (!file_exists($composerPath)) {
            return null;
        }

        $content = file_get_contents($composerPath);
        if ($content === false) {
            return null;
        }

        $composer = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $composer['require'] ?? null;
    }

    private function parseVersionConstraint(string $constraint): string
    {
        $version = preg_replace('/^[\^~>=<|]+/', '', $constraint);

        $version = explode(' ', $version)[0];
        $version = explode(',', $version)[0];
        $version = explode('|', $version)[0];

        return trim($version);
    }
}
