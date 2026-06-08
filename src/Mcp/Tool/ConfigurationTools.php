<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\MasksSensitiveConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Mcp\Capability\Attribute\McpTool;

/**
 * Configuration Tools
 *
 * Provides MCP tools for inspecting Magento configuration.
 */
class ConfigurationTools
{
    use MasksSensitiveConfig;
    use RequiresMagento;
    use RespondsWithErrors;

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
        description: 'Retrieves system configuration value for a given path',
        meta: ['hidden' => true]
    )]
    public function getConfiguration(
        string $path,
        string $scopeType = 'default',
        int $scopeCode = 0
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
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
                    $value = $this->maskValue();
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
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'check-class',
        description: 'Essential pre-check before modifying any class — returns combined plugin list, DI configuration, and preferences in one call. Shows the full runtime picture that file reading misses.'
    )]
    public function checkClass(string $className): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($className === '') {
            return ['error' => true, 'message' => 'className is required'];
        }

        $result = [
            'class' => $className,
        ];

        // Plugins
        try {
            $plugins = $this->getPluginList($className);
            if (!isset($plugins['error'])) {
                unset($plugins['_skill_hint']);
                $result['plugins'] = $plugins;
            }
        } catch (\Throwable $e) {
            $result['plugins'] = ['error' => $e->getMessage()];
        }

        // DI configuration
        try {
            $di = $this->getDiConfiguration($className);
            if (!isset($di['error'])) {
                unset($di['_skill_hint']);
                $result['di_configuration'] = $di;
            }
        } catch (\Throwable $e) {
            $result['di_configuration'] = ['error' => $e->getMessage()];
        }

        // Preferences
        try {
            $preferences = $this->getPreferenceList($className);
            if (!isset($preferences['error'])) {
                unset($preferences['_skill_hint']);
                $result['preferences'] = $preferences;
            }
        } catch (\Throwable $e) {
            $result['preferences'] = ['error' => $e->getMessage()];
        }

        $result['_skill_hint'] = 'Load relevant development guidelines with development-context based on what you plan to modify.';

        return $result;
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
        description: 'Check BEFORE modifying DI — shows runtime-resolved config for a class including preferences, arguments, and virtual types from all modules. File reading misses cross-module overrides.'
    )]
    public function getDiConfiguration(string $className, string $area = 'global'): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
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

            $config['_skill_hint'] = 'For DI configuration patterns: development-context category=module';

            return $config;
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
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
        description: 'Check BEFORE writing a plugin — lists existing plugins on a class with sortOrder. Prevents sortOrder conflicts and reveals the full interceptor chain across all modules.'
    )]
    public function getPluginList(string $className, string $method = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
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

            $result = [
                'class' => $className,
                'method' => $method,
                'plugins' => $plugins,
            ];

            $result['_skill_hint'] = 'For plugin development patterns and sortOrder best practices: development-context category=plugin';

            return $result;
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
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
        description: 'Lists events and their observers in the Magento system',
        meta: ['hidden' => true]
    )]
    public function getEventList(string $eventName = '', string $area = 'global'): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        $validAreas = ['global', 'frontend', 'adminhtml', 'webapi_rest', 'webapi_soap', 'graphql', 'crontab'];
        if (!in_array($area, $validAreas, true)) {
            return [
                'error' => true,
                'message' => sprintf('Invalid area "%s". Available: %s', $area, implode(', ', $validAreas)),
            ];
        }

        $root = defined('BP') ? BP : getcwd();
        $searchDirs = [
            $root . '/vendor',
            $root . '/app/code',
        ];

        $subPath = $area === 'global' ? 'etc/events.xml' : "etc/{$area}/events.xml";
        $events = [];

        foreach ($searchDirs as $searchDir) {
            if (!is_dir($searchDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($searchDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getFilename() !== 'events.xml') {
                    continue;
                }

                $relativePath = str_replace($root . '/', '', $file->getPathname());

                // Match area: global = etc/events.xml (not inside area subfolder)
                if ($area === 'global') {
                    if (!preg_match('#/etc/events\.xml$#', $relativePath)
                        || preg_match('#/etc/(frontend|adminhtml|webapi_rest|webapi_soap|graphql|crontab)/#', $relativePath)) {
                        continue;
                    }
                } else {
                    if (!str_contains($relativePath, "etc/{$area}/events.xml")) {
                        continue;
                    }
                }

                $this->parseEventsXml($file->getPathname(), $relativePath, $events);
            }
        }

        if ($eventName !== '') {
            $events = array_filter($events, function (array $event) use ($eventName): bool {
                return stripos($event['event'], $eventName) !== false;
            });
            $events = array_values($events);
        }

        usort($events, fn(array $a, array $b) => strcmp($a['event'], $b['event']));

        $result = [
            'area' => $area,
            'filter' => $eventName ?: null,
            'total' => count($events),
            'events' => $events,
        ];

        $result['_skill_hint'] = 'For observer development patterns: development-context category=observer';

        return $result;
    }

    /**
     * Parse a single events.xml file and append results to the events array.
     *
     * @param string $filePath Absolute path to events.xml
     * @param string $relativePath Relative path for source attribution
     * @param array<array<string, mixed>> $events Events array (modified by reference)
     */
    private function parseEventsXml(string $filePath, string $relativePath, array &$events): void
    {
        try {
            $xml = @simplexml_load_file($filePath);
            if ($xml === false) {
                return;
            }

            foreach ($xml->event as $eventNode) {
                $name = (string) ($eventNode['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                foreach ($eventNode->observer as $observerNode) {
                    $events[] = [
                        'event' => $name,
                        'observer' => (string) ($observerNode['name'] ?? ''),
                        'class' => (string) ($observerNode['instance'] ?? ''),
                        'disabled' => ((string) ($observerNode['disabled'] ?? 'false')) === 'true',
                        'source' => $relativePath,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Skip unparseable files
        }
    }

    /**
     * Lists available configuration paths for a section.
     *
     * @param string $section Configuration section to list (e.g., "general", "catalog")
     * @return array<string, mixed> List of configuration paths
     */
    #[McpTool(
        name: 'configuration-list',
        description: 'Lists available configuration paths for a section',
        meta: ['hidden' => true]
    )]
    public function listConfiguration(string $section = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
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
            return $this->errorResponse($e->getMessage());
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
        description: 'Check BEFORE overriding a class — lists all preferences (rewrites). Reveals if another module already replaces the target class, preventing conflicts.'
    )]
    public function getPreferenceList(string $interface = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
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

            $result = [
                'filter' => $interface ?: 'common interfaces',
                'count' => count($preferences),
                'preferences' => $preferences,
            ];

            $result['_skill_hint'] = 'For preference and class override patterns: development-context category=preference';

            return $result;
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
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
                $result[$key] = $this->maskValue();
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
