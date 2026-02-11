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
 * Routing Tools
 *
 * Provides MCP tools for inspecting Magento routes and API endpoints.
 */
class RoutingTools
{
    /**
     * Lists all configured frontend routes.
     *
     * @param string $area Filter by area (frontend, adminhtml)
     * @return array<string, mixed> List of routes
     */
    #[McpTool(
        name: 'route-list',
        description: 'Lists all configured Magento frontend and admin routes'
    )]
    public function getRouteList(string $area = 'frontend'): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
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

            // Sort by route_id
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

    /**
     * Lists all REST API endpoints.
     *
     * @param string $method Filter by HTTP method (GET, POST, PUT, DELETE)
     * @param string $path Filter by path pattern
     * @return array<string, mixed> List of API endpoints
     */
    #[McpTool(
        name: 'api-endpoints',
        description: 'Lists all configured REST API endpoints'
    )]
    public function getApiEndpoints(string $method = '', string $path = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $webapiConfig = MagentoBootstrap::get(\Magento\Webapi\Model\Config::class);
            $services = $webapiConfig->getServices();

            $endpoints = [];

            foreach ($services['routes'] ?? [] as $routePath => $routeMethods) {
                foreach ($routeMethods as $httpMethod => $routeData) {
                    // Apply filters
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

            // Sort by path and method
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

    /**
     * Returns controller information for a given route.
     *
     * @param string $frontName The route front name (e.g., "catalog", "checkout")
     * @param string $area The area (frontend or adminhtml)
     * @return array<string, mixed> Controller information
     */
    #[McpTool(
        name: 'route-info',
        description: 'Returns detailed information about a specific route'
    )]
    public function getRouteInfo(string $frontName, string $area = 'frontend'): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $routeConfig = MagentoBootstrap::get(\Magento\Framework\App\Route\ConfigInterface::class);
            $moduleDir = MagentoBootstrap::get(\Magento\Framework\Module\Dir::class);

            // Get modules for this route
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
                    // Module path not found, skip
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

    /**
     * Lists URL rewrites.
     *
     * @param string $requestPath Filter by request path pattern
     * @param int $storeId Filter by store ID
     * @param int $limit Maximum number of results
     * @return array<string, mixed> List of URL rewrites
     */
    #[McpTool(
        name: 'url-rewrites',
        description: 'Lists URL rewrites with optional filtering'
    )]
    public function getUrlRewrites(string $requestPath = '', int $storeId = 0, int $limit = 100): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
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

    /**
     * Scan controller directory for action classes
     *
     * @param string $path Controller directory path
     * @param string $moduleName Module name
     * @param string $area Area code
     * @return array<array<string, mixed>>
     */
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

            // Build class name
            $namespace = str_replace('_', '\\', $moduleName) . '\\Controller';
            if ($area === 'adminhtml') {
                $namespace .= '\\Adminhtml';
            }
            $className = $namespace . '\\' . $relativePath;

            // Build action path
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
