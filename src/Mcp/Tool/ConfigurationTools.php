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
 * Configuration Tools
 *
 * Provides MCP tools for inspecting Magento configuration.
 */
class ConfigurationTools
{
    /**
     * Sensitive configuration paths that should be masked
     */
    private const SENSITIVE_PATHS = [
        'payment',
        'carriers',
        'system/smtp',
        'trans_email',
        'oauth',
        'admin/security',
        'catalog/search/elasticsearch',
        'catalog/search/opensearch',
    ];

    /**
     * Retrieves system configuration value for a given path.
     *
     * @param string $path The configuration path (e.g., "general/locale/code")
     * @param string $scopeType Scope type: default, websites, stores
     * @param int $scopeCode Scope code (website or store ID)
     * @return array<string, mixed> Configuration value
     */
    #[McpTool(
        name: 'configuration-get',
        description: 'Retrieves system configuration value for a given path'
    )]
    public function getConfiguration(
        string $path,
        string $scopeType = 'default',
        int $scopeCode = 0
    ): array {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $scopeConfig = MagentoBootstrap::get(\Magento\Framework\App\Config\ScopeConfigInterface::class);

            $scope = match ($scopeType) {
                'websites' => \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                'stores' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                default => \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            };

            $value = $scopeConfig->getValue($path, $scope, $scopeCode);

            // Mask sensitive values
            if ($this->isSensitivePath($path)) {
                if (is_string($value) && strlen($value) > 0) {
                    $value = '***MASKED***';
                } elseif (is_array($value)) {
                    $value = $this->maskSensitiveArray($value);
                }
            }

            return [
                'path' => $path,
                'scope' => $scopeType,
                'scope_code' => $scopeCode,
                'value' => $value,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns DI configuration for a class/interface.
     *
     * @param string $className The fully qualified class or interface name
     * @param string $area Area code (global, frontend, adminhtml)
     * @return array<string, mixed> DI configuration
     */
    #[McpTool(
        name: 'di-configuration',
        description: 'Returns dependency injection configuration for a class or interface'
    )]
    public function getDiConfiguration(string $className, string $area = 'global'): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $config = [];

            // Get preference (class rewrite)
            $objectManager = MagentoBootstrap::getObjectManager();
            $preferenceClass = null;

            if (interface_exists($className) || class_exists($className)) {
                try {
                    $instance = $objectManager->get($className);
                    $actualClass = get_class($instance);
                    if ($actualClass !== $className) {
                        $preferenceClass = $actualClass;
                    }
                } catch (\Throwable $e) {
                    // Class cannot be instantiated
                }
            }

            $config['class'] = $className;
            $config['preference'] = $preferenceClass;

            // Get plugins
            $plugins = $this->getPluginsForClass($className, $area);
            $config['plugins'] = $plugins;

            return $config;
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists all plugins for a class.
     *
     * @param string $className The fully qualified class name
     * @param string $method Filter by method name (optional)
     * @return array<string, mixed> List of plugins
     */
    #[McpTool(
        name: 'plugin-list',
        description: 'Lists all plugins (interceptors) for a specified class'
    )]
    public function getPluginList(string $className, string $method = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $plugins = $this->getPluginsForClass($className, 'global');

            if ($method !== '') {
                // Filter plugins that affect this method
                $filtered = [];
                foreach ($plugins as $plugin) {
                    // Check if plugin has before/after/around methods for this method
                    $pluginClass = $plugin['instance'];
                    if (class_exists($pluginClass)) {
                        $reflection = new \ReflectionClass($pluginClass);
                        $methodNames = [
                            'before' . ucfirst($method),
                            'after' . ucfirst($method),
                            'around' . ucfirst($method),
                        ];

                        foreach ($methodNames as $pluginMethod) {
                            if ($reflection->hasMethod($pluginMethod)) {
                                $plugin['method'] = $pluginMethod;
                                $filtered[] = $plugin;
                                break;
                            }
                        }
                    } else {
                        $filtered[] = $plugin;
                    }
                }
                $plugins = $filtered;
            }

            return [
                'class' => $className,
                'method' => $method,
                'plugins' => $plugins,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists all events with their observers.
     *
     * @param string $eventName Filter by event name pattern (optional)
     * @param string $area Area code (global, frontend, adminhtml)
     * @return array<string, mixed> List of events and observers
     */
    #[McpTool(
        name: 'event-list',
        description: 'Lists events and their observers in the Magento system'
    )]
    public function getEventList(string $eventName = '', string $area = 'global'): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        return [
            'area' => $area,
            'filter' => $eventName,
            'message' => 'Event list requires parsing events.xml files. Use module-structure tool to inspect specific module configuration.',
        ];
    }

    /**
     * Lists available configuration paths for a section.
     *
     * @param string $section Configuration section to list (e.g., "general", "catalog")
     * @return array<string, mixed> List of configuration paths
     */
    #[McpTool(
        name: 'configuration-list',
        description: 'Lists available configuration paths for a section'
    )]
    public function listConfiguration(string $section = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $objectManager = MagentoBootstrap::getObjectManager();

            /** @var \Magento\Config\Model\Config\Structure $configStructure */
            $configStructure = $objectManager->get(\Magento\Config\Model\Config\Structure::class);

            $paths = [];

            if ($section !== '') {
                // Get paths for specific section
                $sectionData = $configStructure->getElement($section);
                if ($sectionData && $sectionData->hasChildren()) {
                    foreach ($sectionData->getChildren() as $group) {
                        if ($group->hasChildren()) {
                            foreach ($group->getChildren() as $field) {
                                $paths[] = [
                                    'path' => $section . '/' . $group->getId() . '/' . $field->getId(),
                                    'label' => $field->getLabel() ?? $field->getId(),
                                    'type' => $field->getType() ?? 'text',
                                ];
                            }
                        }
                    }
                }
            } else {
                // List all sections
                $tabs = $configStructure->getTabs();
                foreach ($tabs as $tab) {
                    if ($tab->hasChildren()) {
                        foreach ($tab->getChildren() as $sectionElement) {
                            $paths[] = [
                                'section' => $sectionElement->getId(),
                                'label' => $sectionElement->getLabel() ?? $sectionElement->getId(),
                                'tab' => $tab->getId(),
                            ];
                        }
                    }
                }
            }

            return [
                'section' => $section ?: 'all',
                'count' => count($paths),
                'paths' => $paths,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists all preferences (class rewrites) in the system.
     *
     * @param string $interface Filter by interface/class name pattern (optional)
     * @return array<string, mixed> List of preferences
     */
    #[McpTool(
        name: 'preference-list',
        description: 'Lists all preferences (class rewrites) configured in the system'
    )]
    public function getPreferenceList(string $interface = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $objectManager = MagentoBootstrap::getObjectManager();

            // Get the ObjectManager configuration
            $omConfig = $objectManager->get(\Magento\Framework\ObjectManager\ConfigInterface::class);

            $preferences = [];

            // Common interfaces to check for preferences
            $commonInterfaces = [
                \Magento\Catalog\Api\ProductRepositoryInterface::class,
                \Magento\Catalog\Api\CategoryRepositoryInterface::class,
                \Magento\Customer\Api\CustomerRepositoryInterface::class,
                \Magento\Sales\Api\OrderRepositoryInterface::class,
                \Magento\Quote\Api\CartRepositoryInterface::class,
                \Magento\Framework\App\Config\ScopeConfigInterface::class,
                \Magento\Store\Api\StoreRepositoryInterface::class,
            ];

            if ($interface !== '') {
                // Check specific interface
                if (interface_exists($interface) || class_exists($interface)) {
                    $preference = $omConfig->getPreference($interface);
                    if ($preference !== $interface) {
                        $preferences[] = [
                            'interface' => $interface,
                            'preference' => $preference,
                        ];
                    }
                }
            } else {
                // Check common interfaces
                foreach ($commonInterfaces as $commonInterface) {
                    if (interface_exists($commonInterface)) {
                        $preference = $omConfig->getPreference($commonInterface);
                        if ($preference !== $commonInterface) {
                            $preferences[] = [
                                'interface' => $commonInterface,
                                'preference' => $preference,
                            ];
                        }
                    }
                }
            }

            return [
                'filter' => $interface ?: 'common interfaces',
                'count' => count($preferences),
                'preferences' => $preferences,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if a configuration path is sensitive
     *
     * @param string $path
     * @return bool
     */
    private function isSensitivePath(string $path): bool
    {
        foreach (self::SENSITIVE_PATHS as $sensitive) {
            if (str_starts_with($path, $sensitive)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mask sensitive values in an array
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function maskSensitiveArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if ($this->isSensitiveKey((string) $key) && is_string($value) && $value !== '') {
                $result[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $result[$key] = $this->maskSensitiveArray($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if an array key matches any sensitive keyword
     */
    private function isSensitiveKey(string $key): bool
    {
        $sensitiveKeywords = ['password', 'key', 'secret', 'token', 'api_key', 'private'];

        foreach ($sensitiveKeywords as $keyword) {
            if (stripos($key, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get plugins for a class
     *
     * @param string $className
     * @param string $area
     * @return array<array<string, mixed>>
     */
    private function getPluginsForClass(string $className, string $area): array
    {
        // This is a simplified implementation
        // Full implementation would use Magento's interception configuration
        try {
            $objectManager = MagentoBootstrap::getObjectManager();
            $pluginList = $objectManager->get(\Magento\Framework\Interception\PluginListInterface::class);

            if (method_exists($pluginList, 'get')) {
                $plugins = $pluginList->get($className, []);
                return is_array($plugins) ? $plugins : [];
            }
        } catch (\Throwable $e) {
            // Plugin list not available
        }

        return [];
    }
}
