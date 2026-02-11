<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Bootstrap;

/**
 * Magento Installation Detector
 *
 * Detects Magento root directory by searching for marker files.
 * Supports detection from any subdirectory within a Magento project.
 */
class MagentoDetector
{
    /**
     * Marker files that must exist in a Magento root directory
     */
    private const MARKER_FILES = [
        'app/bootstrap.php',
        'app/etc/env.php',
        'bin/magento',
    ];

    /**
     * Additional marker files for validation (not all required)
     */
    private const OPTIONAL_MARKERS = [
        'app/etc/config.php',
        'composer.json',
        'pub/index.php',
    ];

    /**
     * Maximum directory depth to search upward
     */
    private const MAX_SEARCH_DEPTH = 15;

    /**
     * Detect Magento root directory
     *
     * Searches from the current directory upward until finding a valid Magento installation.
     *
     * @param string|null $startPath Path to start searching from (defaults to cwd)
     * @return string|null The Magento root path, or null if not found
     */
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

            // Reached filesystem root
            if ($parentDir === $currentDir) {
                break;
            }

            $currentDir = $parentDir;
        }

        return null;
    }

    /**
     * Check if a directory is a valid Magento root
     *
     * @param string $path The directory path to check
     * @return bool True if the path is a valid Magento root
     */
    public function isValidMagentoRoot(string $path): bool
    {
        foreach (self::MARKER_FILES as $marker) {
            $markerPath = $path . DIRECTORY_SEPARATOR . $marker;
            if (!file_exists($markerPath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get Magento version from composer.json
     *
     * @param string $magentoRoot The Magento root directory
     * @return string|null The version string or null if not determinable
     */
    public function getVersion(string $magentoRoot): ?string
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

        // Check for magento/product-community-edition or magento/product-enterprise-edition
        $require = $composer['require'] ?? [];

        foreach (['magento/product-community-edition', 'magento/product-enterprise-edition'] as $package) {
            if (isset($require[$package])) {
                return $this->parseVersionConstraint($require[$package]);
            }
        }

        // Fallback: check magento/framework version
        if (isset($require['magento/framework'])) {
            return $this->parseVersionConstraint($require['magento/framework']);
        }

        return null;
    }

    /**
     * Get Magento edition from composer.json
     *
     * @param string $magentoRoot The Magento root directory
     * @return string 'community', 'enterprise', or 'unknown'
     */
    public function getEdition(string $magentoRoot): string
    {
        $composerPath = $magentoRoot . '/composer.json';
        if (!file_exists($composerPath)) {
            return 'unknown';
        }

        $content = file_get_contents($composerPath);
        if ($content === false) {
            return 'unknown';
        }

        $composer = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'unknown';
        }

        $require = $composer['require'] ?? [];

        if (isset($require['magento/product-enterprise-edition'])) {
            return 'enterprise';
        }

        if (isset($require['magento/product-community-edition'])) {
            return 'community';
        }

        // Check for B2B module as indicator of enterprise
        if (isset($require['magento/module-b2b'])) {
            return 'enterprise';
        }

        return 'unknown';
    }

    /**
     * Parse a version constraint to extract the actual version
     *
     * @param string $constraint The composer version constraint
     * @return string The parsed version
     */
    private function parseVersionConstraint(string $constraint): string
    {
        // Remove common constraint operators
        $version = preg_replace('/^[\^~>=<|]+/', '', $constraint);

        // Take only the first version if multiple are specified
        $version = explode(' ', $version)[0];
        $version = explode(',', $version)[0];
        $version = explode('|', $version)[0];

        return trim($version);
    }

    /**
     * Get runtime environment type
     *
     * @param string $magentoRoot The Magento root directory
     * @return string 'ddev', 'hooli', 'warden', 'docker-compose', 'docker', or 'native'
     */
    public function getEnvironmentType(string $magentoRoot): string
    {
        // Check for DDEV
        if (file_exists($magentoRoot . '/.ddev/config.yaml')) {
            return 'ddev';
        }

        // Check for Hooli (Magento root is in html/ subdirectory, hooli executable is in parent)
        $parentDir = dirname($magentoRoot);
        if (file_exists($parentDir . '/hooli') && file_exists($parentDir . '/docker-compose.yml')) {
            return 'hooli';
        }

        // Check for Warden
        if (file_exists($magentoRoot . '/.warden/warden-env.yml') ||
            file_exists($magentoRoot . '/.env.warden')) {
            return 'warden';
        }

        // Check for Docker Compose
        if (file_exists($magentoRoot . '/docker-compose.yml') ||
            file_exists($magentoRoot . '/compose.yml') ||
            file_exists($magentoRoot . '/docker-compose.yaml')) {
            return 'docker-compose';
        }

        // Check if running inside a Docker container
        if (file_exists('/.dockerenv') || getenv('DOCKER_CONTAINER') !== false) {
            return 'docker';
        }

        return 'native';
    }
}
