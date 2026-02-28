<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

class RoutingTools
{
    use RequiresMagento;

    #[McpTool(
        name: 'route-list',
        description: 'Lists all configured Magento frontend and admin routes',
        meta: ['hidden' => true]
    )]
    public function getRouteList(string $area = 'frontend'): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $reader = MagentoBootstrap::get(\Magento\Framework\App\Route\Config\Reader::class);

            $routes = [];
            $routers = $reader->read($area);

            foreach ($routers as $routerId => $routerData) {
                foreach ($routerData['routes'] ?? [] as $routeId => $routeData) {
                    $routes[] = [
                        'route_id' => $routeId,
                        'front_name' => $routeData['frontName'] ?? $routeId,
                        'modules' => $routeData['modules'] ?? [],
                        'area' => $area,
                        'router' => $routerId,
                    ];
                }
            }

            usort($routes, fn($a, $b) => strcmp($a['route_id'], $b['route_id']));

            return [
                'area' => $area,
                'total' => count($routes),
                'routes' => $routes,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'api-endpoints',
        description: 'Lists all configured REST API endpoints',
        meta: ['hidden' => true]
    )]
    public function getApiEndpoints(string $method = '', string $path = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $webapiConfig = MagentoBootstrap::get(\Magento\Webapi\Model\Config::class);
            $services = $webapiConfig->getServices();

            $endpoints = [];

            foreach ($services['routes'] ?? [] as $routePath => $routeMethods) {
                foreach ($routeMethods as $httpMethod => $routeData) {
                    if ($method !== '' && strtoupper($method) !== strtoupper($httpMethod)) {
                        continue;
                    }
                    if ($path !== '' && stripos($routePath, $path) === false) {
                        continue;
                    }

                    $endpoints[] = [
                        'path' => $routePath,
                        'method' => strtoupper($httpMethod),
                        'service' => [
                            'class' => $routeData['service']['class'] ?? null,
                            'method' => $routeData['service']['method'] ?? null,
                        ],
                        'resources' => $routeData['resources'] ?? [],
                        'secure' => !isset($routeData['resources']['anonymous']),
                    ];
                }
            }

            usort($endpoints, fn($a, $b) =>
                strcmp($a['path'], $b['path']) ?: strcmp($a['method'], $b['method'])
            );

            return [
                'total' => count($endpoints),
                'filter_method' => $method ?: 'all',
                'filter_path' => $path ?: 'all',
                'endpoints' => $endpoints,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'route-info',
        description: 'Returns detailed information about a specific route',
        meta: ['hidden' => true]
    )]
    public function getRouteInfo(string $frontName, string $area = 'frontend'): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $routeConfig = MagentoBootstrap::get(\Magento\Framework\App\Route\ConfigInterface::class);
            $moduleDir = MagentoBootstrap::get(\Magento\Framework\Module\Dir::class);

            $modules = $routeConfig->getModulesByFrontName($frontName, $area);

            if (empty($modules)) {
                return ['error' => true, 'message' => "Route not found: $frontName"];
            }

            $controllers = [];

            foreach ($modules as $moduleName) {
                try {
                    $modulePath = $moduleDir->getDir($moduleName);
                    $controllerPath = $modulePath . '/Controller';

                    if ($area === 'adminhtml') {
                        $controllerPath .= '/Adminhtml';
                    }

                    if (is_dir($controllerPath)) {
                        $foundControllers = $this->scanControllers($controllerPath, $moduleName, $area);
                        $controllers = array_merge($controllers, $foundControllers);
                    }
                } catch (\Throwable $e) {
                }
            }

            return [
                'front_name' => $frontName,
                'area' => $area,
                'modules' => $modules,
                'controllers' => $controllers,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'url-rewrites',
        description: 'Lists URL rewrites with optional filtering',
        meta: ['hidden' => true]
    )]
    public function getUrlRewrites(string $requestPath = '', int $storeId = 0, int $limit = 100): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('url_rewrite');

            $select = $connection->select()
                ->from($tableName)
                ->limit($limit);

            if ($requestPath !== '') {
                $select->where('request_path LIKE ?', "%$requestPath%");
            }
            if ($storeId > 0) {
                $select->where('store_id = ?', $storeId);
            }

            $select->order('request_path ASC');

            $rows = $connection->fetchAll($select);

            $rewrites = [];
            foreach ($rows as $row) {
                $rewrites[] = [
                    'url_rewrite_id' => (int) $row['url_rewrite_id'],
                    'request_path' => $row['request_path'],
                    'target_path' => $row['target_path'],
                    'redirect_type' => (int) $row['redirect_type'],
                    'store_id' => (int) $row['store_id'],
                    'entity_type' => $row['entity_type'],
                    'entity_id' => (int) $row['entity_id'],
                ];
            }

            return [
                'total' => count($rewrites),
                'filter_path' => $requestPath ?: 'all',
                'filter_store' => $storeId ?: 'all',
                'rewrites' => $rewrites,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function scanControllers(string $path, string $moduleName, string $area): array
    {
        $controllers = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($path) + 1);
            $relativePath = str_replace('.php', '', $relativePath);
            $relativePath = str_replace('/', '\\', $relativePath);

            $namespace = str_replace('_', '\\', $moduleName) . '\\Controller';
            if ($area === 'adminhtml') {
                $namespace .= '\\Adminhtml';
            }
            $className = $namespace . '\\' . $relativePath;

            $actionPath = strtolower(str_replace('\\', '/', $relativePath));

            $controllers[] = [
                'class' => $className,
                'action_path' => $actionPath,
                'file' => $file->getPathname(),
            ];
        }

        return $controllers;
    }
}
