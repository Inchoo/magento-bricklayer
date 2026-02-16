<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Mcp\Capability\Attribute\McpTool;

class ModuleTools
{
    #[McpTool(
        name: 'module-list',
        description: 'Lists all installed Magento modules. Use verbosity (minimal/standard/detailed) to control response size. Set count_only=true to get total count without data.'
    )]
    public function listModules(bool $enabledOnly = false, string $vendor = '', bool $count_only = false, string $verbosity = 'standard'): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        if (!in_array($verbosity, ['minimal', 'standard', 'detailed'], true)) {
            return ['error' => true, 'message' => 'verbosity must be one of: minimal, standard, detailed'];
        }

        try {
            $moduleList = MagentoBootstrap::get(\Magento\Framework\Module\ModuleListInterface::class);
            $fullModuleList = MagentoBootstrap::get(\Magento\Framework\Module\FullModuleList::class);
            $moduleDir = MagentoBootstrap::get(\Magento\Framework\Module\Dir::class);

            $enabledModules = $moduleList->getAll();
            $allModules = $enabledOnly ? $enabledModules : array_flip($fullModuleList->getNames());

            $modules = [];
            foreach ($allModules as $name => $info) {
                $parts = explode('_', $name, 2);
                $moduleVendor = $parts[0];
                $moduleName = $parts[1] ?? '';

                if ($vendor !== '' && strcasecmp($moduleVendor, $vendor) !== 0) {
                    continue;
                }

                $isEnabled = isset($enabledModules[$name]);
                $version = is_array($info) && isset($info['setup_version']) ? $info['setup_version'] : null;

                $path = null;
                try {
                    $path = $moduleDir->getDir($name);
                    $magentoRoot = MagentoBootstrap::getMagentoRoot();
                    if ($magentoRoot && str_starts_with($path, $magentoRoot)) {
                        $path = substr($path, strlen($magentoRoot) + 1);
                    }
                } catch (\Throwable $e) {
                }

                $moduleData = match ($verbosity) {
                    'minimal' => [
                        'name' => $name,
                        'enabled' => $isEnabled,
                    ],
                    'detailed' => [
                        'name' => $name,
                        'vendor' => $moduleVendor,
                        'module' => $moduleName,
                        'enabled' => $isEnabled,
                        'version' => $version,
                        'path' => $path,
                        'is_magento' => $moduleVendor === 'Magento',
                        'has_etc' => $path !== null && is_dir(MagentoBootstrap::getMagentoRoot() . '/' . $path . '/etc'),
                        'has_setup' => $path !== null && is_dir(MagentoBootstrap::getMagentoRoot() . '/' . $path . '/Setup'),
                        'has_api' => $path !== null && is_dir(MagentoBootstrap::getMagentoRoot() . '/' . $path . '/Api'),
                    ],
                    default => [
                        'name' => $name,
                        'vendor' => $moduleVendor,
                        'module' => $moduleName,
                        'enabled' => $isEnabled,
                        'version' => $version,
                        'path' => $path,
                        'is_magento' => $moduleVendor === 'Magento',
                    ],
                };

                $modules[] = $moduleData;
            }

            if ($count_only) {
                return [
                    'total' => count($modules),
                    'count_only' => true,
                    'filter' => array_filter([
                        'enabled_only' => $enabledOnly ?: null,
                        'vendor' => $vendor ?: null,
                    ]),
                ];
            }

            usort($modules, fn($a, $b) => strcmp($a['name'], $b['name']));

            return [
                'total' => count($modules),
                'modules' => $modules,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

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
