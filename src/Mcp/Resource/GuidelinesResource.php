<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;

/**
 * Guidelines Resource
 *
 * Provides Magento development guidelines as MCP resources.
 */
class GuidelinesResource
{
    use FileLoaderTrait;

    /**
     * Returns security guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/security',
        name: 'security_guidelines',
        description: 'Magento 2 security best practices and guidelines',
        mimeType: 'text/markdown'
    )]
    public function getSecurityGuidelines(): string
    {
        return $this->loadGuideline('core/security.md');
    }

    /**
     * Returns plugin pattern guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/plugin',
        name: 'plugin_guidelines',
        description: 'Magento 2 plugin (interceptor) pattern guidelines',
        mimeType: 'text/markdown'
    )]
    public function getPluginGuidelines(): string
    {
        return $this->loadGuideline('patterns/plugin.md');
    }

    /**
     * Returns repository pattern guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/repository',
        name: 'repository_guidelines',
        description: 'Magento 2 repository pattern guidelines',
        mimeType: 'text/markdown'
    )]
    public function getRepositoryGuidelines(): string
    {
        return $this->loadGuideline('patterns/repository.md');
    }

    /**
     * Returns module structure guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/module-structure',
        name: 'module_structure_guidelines',
        description: 'Magento 2 module structure and organization guidelines',
        mimeType: 'text/markdown'
    )]
    public function getModuleStructureGuidelines(): string
    {
        return $this->loadGuideline('modules/structure.md');
    }

    /**
     * Returns performance guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/performance',
        name: 'performance_guidelines',
        description: 'Magento 2 performance optimization guidelines',
        mimeType: 'text/markdown'
    )]
    public function getPerformanceGuidelines(): string
    {
        return $this->loadGuideline('core/performance.md');
    }

    /**
     * Returns testing guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/testing',
        name: 'testing_guidelines',
        description: 'Magento 2 testing guidelines and best practices',
        mimeType: 'text/markdown'
    )]
    public function getTestingGuidelines(): string
    {
        return $this->loadGuideline('core/testing.md');
    }

    /**
     * Returns declarative schema guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/declarative-schema',
        name: 'declarative_schema_guidelines',
        description: 'Magento 2 declarative schema (db_schema.xml) guidelines',
        mimeType: 'text/markdown'
    )]
    public function getDeclarativeSchemaGuidelines(): string
    {
        return $this->loadGuideline('database/declarative-schema.md');
    }

    /**
     * Returns data patches guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/data-patches',
        name: 'data_patches_guidelines',
        description: 'Magento 2 data patches guidelines',
        mimeType: 'text/markdown'
    )]
    public function getDataPatchesGuidelines(): string
    {
        return $this->loadGuideline('database/data-patches.md');
    }

    /**
     * Returns frontend development guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/frontend',
        name: 'frontend_guidelines',
        description: 'Magento 2 frontend development guidelines',
        mimeType: 'text/markdown'
    )]
    public function getFrontendGuidelines(): string
    {
        return $this->loadGuideline('areas/frontend.md');
    }

    /**
     * Returns admin development guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/adminhtml',
        name: 'adminhtml_guidelines',
        description: 'Magento 2 admin panel development guidelines',
        mimeType: 'text/markdown'
    )]
    public function getAdminhtmlGuidelines(): string
    {
        return $this->loadGuideline('areas/adminhtml.md');
    }

    /**
     * Returns WebAPI development guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/webapi',
        name: 'webapi_guidelines',
        description: 'Magento 2 REST/SOAP WebAPI development guidelines',
        mimeType: 'text/markdown'
    )]
    public function getWebapiGuidelines(): string
    {
        return $this->loadGuideline('areas/webapi.md');
    }

    /**
     * Returns GraphQL development guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/graphql',
        name: 'graphql_guidelines',
        description: 'Magento 2 GraphQL development guidelines',
        mimeType: 'text/markdown'
    )]
    public function getGraphqlGuidelines(): string
    {
        return $this->loadGuideline('areas/graphql.md');
    }

    /**
     * Returns module registration guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/module-registration',
        name: 'module_registration_guidelines',
        description: 'Magento 2 module registration.php guidelines',
        mimeType: 'text/markdown'
    )]
    public function getModuleRegistrationGuidelines(): string
    {
        return $this->loadGuideline('modules/registration.md');
    }

    /**
     * Returns module dependencies guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/module-dependencies',
        name: 'module_dependencies_guidelines',
        description: 'Magento 2 module dependencies and sequence guidelines',
        mimeType: 'text/markdown'
    )]
    public function getModuleDependenciesGuidelines(): string
    {
        return $this->loadGuideline('modules/dependencies.md');
    }

    /**
     * Returns module versioning guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/module-versioning',
        name: 'module_versioning_guidelines',
        description: 'Magento 2 module versioning and semantic versioning guidelines',
        mimeType: 'text/markdown'
    )]
    public function getModuleVersioningGuidelines(): string
    {
        return $this->loadGuideline('modules/versioning.md');
    }

    /**
     * Returns service contract guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/service-contract',
        name: 'service_contract_guidelines',
        description: 'Magento 2 service contract and API interface guidelines',
        mimeType: 'text/markdown'
    )]
    public function getServiceContractGuidelines(): string
    {
        return $this->loadGuideline('patterns/service-contract.md');
    }

    /**
     * Returns observer pattern guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/observer',
        name: 'observer_guidelines',
        description: 'Magento 2 event observer pattern guidelines',
        mimeType: 'text/markdown'
    )]
    public function getObserverGuidelines(): string
    {
        return $this->loadGuideline('patterns/observer.md');
    }

    /**
     * Returns preference pattern guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/preference',
        name: 'preference_guidelines',
        description: 'Magento 2 preference (class rewrite) guidelines',
        mimeType: 'text/markdown'
    )]
    public function getPreferenceGuidelines(): string
    {
        return $this->loadGuideline('patterns/preference.md');
    }

    /**
     * Returns factory pattern guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/factory',
        name: 'factory_guidelines',
        description: 'Magento 2 factory pattern guidelines',
        mimeType: 'text/markdown'
    )]
    public function getFactoryGuidelines(): string
    {
        return $this->loadGuideline('patterns/factory.md');
    }

    /**
     * Returns EAV guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/eav',
        name: 'eav_guidelines',
        description: 'Magento 2 EAV (Entity-Attribute-Value) guidelines',
        mimeType: 'text/markdown'
    )]
    public function getEavGuidelines(): string
    {
        return $this->loadGuideline('database/eav.md');
    }

    /**
     * Returns indexer guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/indexers',
        name: 'indexers_guidelines',
        description: 'Magento 2 indexer development guidelines',
        mimeType: 'text/markdown'
    )]
    public function getIndexersGuidelines(): string
    {
        return $this->loadGuideline('database/indexers.md');
    }

    /**
     * Returns Hyvä theme guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/hyva',
        name: 'hyva_guidelines',
        description: 'Hyvä theme development guidelines',
        mimeType: 'text/markdown'
    )]
    public function getHyvaGuidelines(): string
    {
        return $this->loadGuideline('ecosystem/hyva.md');
    }

    /**
     * Returns Mage-OS guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/mage-os',
        name: 'mage_os_guidelines',
        description: 'Mage-OS community distribution guidelines',
        mimeType: 'text/markdown'
    )]
    public function getMageOsGuidelines(): string
    {
        return $this->loadGuideline('ecosystem/mage-os.md');
    }

    /**
     * Returns Adobe Commerce guidelines.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://guidelines/adobe-commerce',
        name: 'adobe_commerce_guidelines',
        description: 'Adobe Commerce (enterprise) specific guidelines',
        mimeType: 'text/markdown'
    )]
    public function getAdobeCommerceGuidelines(): string
    {
        return $this->loadGuideline('ecosystem/adobe-commerce.md');
    }

    /**
     * Load guideline content from file
     *
     * @param string $relativePath Relative path within guidelines directory
     * @return string Content or placeholder message
     */
    private function loadGuideline(string $relativePath): string
    {
        return $this->loadConfigFile('guidelines', $relativePath);
    }
}
