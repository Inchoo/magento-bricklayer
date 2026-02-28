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

class ApplicationTools
{
    use RequiresMagento;

    #[McpTool(
        name: 'application-info',
        description: 'Returns Magento version, PHP version, deploy mode, and installation summary. Use include=stores for store hierarchy.',
        meta: ['hidden' => true]
    )]
    public function getApplicationInfo(string $include = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $metadata = MagentoBootstrap::get(\Magento\Framework\App\ProductMetadataInterface::class);
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $moduleList = MagentoBootstrap::get(\Magento\Framework\Module\ModuleListInterface::class);
            $fullModuleList = MagentoBootstrap::get(\Magento\Framework\Module\FullModuleList::class);

            $allModules = $fullModuleList->getNames();
            $enabledModules = array_keys($moduleList->getAll());
            $customModules = array_filter($enabledModules, fn($name) => !str_starts_with($name, 'Magento_'));

            $storeManager = MagentoBootstrap::get(\Magento\Store\Model\StoreManagerInterface::class);
            $cacheTypeList = MagentoBootstrap::get(\Magento\Framework\App\Cache\TypeListInterface::class);
            $cacheTypes = $cacheTypeList->getTypes();

            $dbInfo = $this->getDatabaseInfo();

            $result = [
                'magento_version' => $metadata->getVersion(),
                'edition' => strtolower($metadata->getEdition()),
                'php_version' => PHP_VERSION,
                'deploy_mode' => $state->getMode(),
                'database' => $dbInfo,
                'modules' => [
                    'total' => count($allModules),
                    'enabled' => count($enabledModules),
                    'disabled' => count($allModules) - count($enabledModules),
                    'custom' => count($customModules),
                ],
                'stores' => [
                    'websites' => count($storeManager->getWebsites()),
                    'stores' => count($storeManager->getGroups()),
                    'store_views' => count($storeManager->getStores()),
                ],
                'cache' => [
                    'types_total' => count($cacheTypes),
                    'types_enabled' => count(array_filter($cacheTypes, fn($t) => $t->getStatus())),
                ],
            ];

            if ($include === 'stores') {
                $result['store_hierarchy'] = $this->getStoreHierarchy();
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getStoreHierarchy(): array
    {
        try {
            $storeManager = MagentoBootstrap::get(\Magento\Store\Model\StoreManagerInterface::class);

            $websites = [];
            foreach ($storeManager->getWebsites() as $website) {
                $websiteData = [
                    'id' => (int) $website->getId(),
                    'code' => $website->getCode(),
                    'name' => $website->getName(),
                    'is_default' => (bool) $website->getIsDefault(),
                    'stores' => [],
                ];

                foreach ($website->getGroups() as $group) {
                    $groupData = [
                        'id' => (int) $group->getId(),
                        'code' => $group->getCode(),
                        'name' => $group->getName(),
                        'root_category_id' => (int) $group->getRootCategoryId(),
                        'store_views' => [],
                    ];

                    foreach ($group->getStores() as $store) {
                        $groupData['store_views'][] = [
                            'id' => (int) $store->getId(),
                            'code' => $store->getCode(),
                            'name' => $store->getName(),
                            'is_active' => (bool) $store->getIsActive(),
                            'locale' => $store->getConfig('general/locale/code'),
                            'base_url' => $store->getBaseUrl(),
                        ];
                    }

                    $websiteData['stores'][] = $groupData;
                }

                $websites[] = $websiteData;
            }

            return $websites;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getDatabaseInfo(): array
    {
        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();

            $serverInfo = $connection->fetchOne('SELECT VERSION()');
            $dbName = $connection->fetchOne('SELECT DATABASE()');

            return [
                'type' => 'mysql',
                'version' => $serverInfo,
                'database' => $dbName,
            ];
        } catch (\Throwable $e) {
            return [
                'type' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }
}
