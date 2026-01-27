<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Mcp\Capability\Attribute\McpTool;

/**
 * Module Tools
 *
 * Provides MCP tools for inspecting Magento modules.
 */
class ModuleTools
{
    /**
     * Lists all installed modules with version, status, and vendor.
     *
     * @param bool $enabledOnly If true, only show enabled modules
     * @param string $vendor Filter by vendor name (optional)
     * @return array<string, mixed> List of modules
     */
    #[McpTool(
        name: 'module-list',
        description: 'Lists all installed Magento modules with version, status, and vendor'
    )]
    public function listModules(bool $enabledOnly = false, string $vendor = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $moduleList = MagentoBootstrap::get(\Magento\Framework\Module\ModuleListInterface::class);
            $fullModuleList = MagentoBootstrap::get(\Magento\Framework\Module\FullModuleList::class);
            $moduleDir = MagentoBootstrap::get(\Magento\Framework\Module\Dir::class);

            $enabledModules = $moduleList->getAll();
            $allModules = $enabledOnly ? $enabledModules : array_flip($fullModuleList->getNames());

            $modules = [];
            foreach ($allModules as $name => $info) {
                // Parse vendor from module name
                $parts = explode('_', $name, 2);
                $moduleVendor = $parts[0];
                $moduleName = $parts[1] ?? '';

                // Filter by vendor if specified
                if ($vendor !== '' && strcasecmp($moduleVendor, $vendor) !== 0) {
                    continue;
                }

                $isEnabled = isset($enabledModules[$name]);
                $version = is_array($info) && isset($info['setup_version']) ? $info['setup_version'] : null;

                // Try to get module path
                $path = null;
                try {
                    $path = $moduleDir->getDir($name);
                    // Make path relative
                    $magentoRoot = MagentoBootstrap::getMagentoRoot();
                    if ($magentoRoot && str_starts_with($path, $magentoRoot)) {
                        $path = substr($path, strlen($magentoRoot) + 1);
                    }
                } catch (\Throwable $e) {
                    // Module path not found
                }

                $modules[] = [
                    'name' => $name,
                    'vendor' => $moduleVendor,
                    'module' => $moduleName,
                    'enabled' => $isEnabled,
                    'version' => $version,
                    'path' => $path,
                    'is_magento' => $moduleVendor === 'Magento',
                ];
            }

            // Sort by name
            usort($modules, fn($a, $b) => strcmp($a['name'], $b['name']));

            return [
                'total' => count($modules),
                'modules' => $modules,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns the file/folder structure of a specific module.
     *
     * @param string $moduleName The full module name (e.g., "Vendor_Module")
     * @return array<string, mixed> Module structure
     */
    #[McpTool(
        name: 'module-structure',
        description: 'Returns the file/folder structure of a specific Magento module'
    )]
    public function getModuleStructure(string $moduleName): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $moduleDir = MagentoBootstrap::get(\Magento\Framework\Module\Dir::class);

            try {
                $path = $moduleDir->getDir($moduleName);
            } catch (\Throwable $e) {
                return ['error' => true, 'message' => "Module not found: $moduleName"];
            }

            if (!is_dir($path)) {
                return ['error' => true, 'message' => "Module directory not found: $path"];
            }

            $structure = $this->scanDirectory($path, $path);

            // Get module.xml info
            $moduleXmlPath = $path . '/etc/module.xml';
            $moduleInfo = [];
            if (file_exists($moduleXmlPath)) {
                $moduleInfo = $this->parseModuleXml($moduleXmlPath);
            }

            return [
                'module' => $moduleName,
                'path' => $path,
                'info' => $moduleInfo,
                'structure' => $structure,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Scan a directory and return its structure
     *
     * @param string $dir Directory to scan
     * @param string $baseDir Base directory for relative paths
     * @param int $depth Current depth
     * @return array<string, mixed>
     */
    private function scanDirectory(string $dir, string $baseDir, int $depth = 0): array
    {
        if ($depth > 5) {
            return ['truncated' => true];
        }

        $items = [];
        $entries = scandir($dir);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            $relativePath = substr($path, strlen($baseDir) + 1);

            if (is_dir($path)) {
                $items[$entry] = [
                    'type' => 'directory',
                    'path' => $relativePath,
                    'children' => $this->scanDirectory($path, $baseDir, $depth + 1),
                ];
            } else {
                $items[$entry] = [
                    'type' => 'file',
                    'path' => $relativePath,
                    'size' => filesize($path),
                ];
            }
        }

        return $items;
    }

    /**
     * Parse module.xml to get module info
     *
     * @param string $path Path to module.xml
     * @return array<string, mixed>
     */
    private function parseModuleXml(string $path): array
    {
        try {
            $xml = simplexml_load_file($path);
            if ($xml === false) {
                return [];
            }

            $module = $xml->module ?? null;
            if ($module === null) {
                return [];
            }

            $info = [
                'name' => (string) ($module['name'] ?? ''),
                'setup_version' => (string) ($module['setup_version'] ?? ''),
            ];

            // Parse dependencies
            $dependencies = [];
            $sequence = $module->sequence ?? null;
            if ($sequence) {
                foreach ($sequence->module as $dep) {
                    $dependencies[] = (string) $dep['name'];
                }
            }

            if (!empty($dependencies)) {
                $info['dependencies'] = $dependencies;
            }

            return $info;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
