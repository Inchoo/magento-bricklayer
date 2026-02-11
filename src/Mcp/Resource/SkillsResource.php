<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;

/**
 * Skills Resource
 *
 * Provides Magento development skills documentation as MCP resources.
 */
class SkillsResource
{
    use FileLoaderTrait;

    /**
     * Returns plugin development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/plugin',
        name: 'plugin_skill',
        description: 'Magento 2 plugin (interceptor) development skill',
        mimeType: 'text/markdown'
    )]
    public function getPluginSkill(): string
    {
        return $this->loadSkill('plugin');
    }

    /**
     * Returns EAV development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/eav-development',
        name: 'eav_development_skill',
        description: 'Magento 2 EAV (Entity-Attribute-Value) development skill',
        mimeType: 'text/markdown'
    )]
    public function getEavSkill(): string
    {
        return $this->loadSkill('eav-development');
    }

    /**
     * Returns GraphQL development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/graphql-development',
        name: 'graphql_development_skill',
        description: 'Magento 2 GraphQL development skill',
        mimeType: 'text/markdown'
    )]
    public function getGraphqlSkill(): string
    {
        return $this->loadSkill('graphql-development');
    }

    /**
     * Returns REST API development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/rest-api-development',
        name: 'rest_api_skill',
        description: 'Magento 2 REST API development skill',
        mimeType: 'text/markdown'
    )]
    public function getRestApiSkill(): string
    {
        return $this->loadSkill('rest-api-development');
    }

    /**
     * Returns cron development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/cron-development',
        name: 'cron_development_skill',
        description: 'Magento 2 cron job development skill',
        mimeType: 'text/markdown'
    )]
    public function getCronSkill(): string
    {
        return $this->loadSkill('cron-development');
    }

    /**
     * Returns indexer development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/indexer-development',
        name: 'indexer_development_skill',
        description: 'Magento 2 indexer development skill',
        mimeType: 'text/markdown'
    )]
    public function getIndexerSkill(): string
    {
        return $this->loadSkill('indexer-development');
    }

    /**
     * Returns testing skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/testing',
        name: 'testing_skill',
        description: 'Magento 2 testing skill (unit, integration, API tests)',
        mimeType: 'text/markdown'
    )]
    public function getTestingSkill(): string
    {
        return $this->loadSkill('testing');
    }

    /**
     * Returns theme development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/theme-development',
        name: 'theme_development_skill',
        description: 'Magento 2 theme development skill',
        mimeType: 'text/markdown'
    )]
    public function getThemeSkill(): string
    {
        return $this->loadSkill('theme-development');
    }

    /**
     * Returns checkout customization skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/checkout-customization',
        name: 'checkout_skill',
        description: 'Magento 2 checkout customization skill',
        mimeType: 'text/markdown'
    )]
    public function getCheckoutSkill(): string
    {
        return $this->loadSkill('checkout-customization');
    }

    /**
     * Returns payment integration skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/payment-integration',
        name: 'payment_integration_skill',
        description: 'Magento 2 payment method integration skill',
        mimeType: 'text/markdown'
    )]
    public function getPaymentSkill(): string
    {
        return $this->loadSkill('payment-integration');
    }

    /**
     * Returns shipping integration skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/shipping-integration',
        name: 'shipping_integration_skill',
        description: 'Magento 2 shipping carrier integration skill',
        mimeType: 'text/markdown'
    )]
    public function getShippingSkill(): string
    {
        return $this->loadSkill('shipping-integration');
    }

    /**
     * Returns UI component development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/ui-component-development',
        name: 'ui_component_skill',
        description: 'Magento 2 UI component development skill',
        mimeType: 'text/markdown'
    )]
    public function getUiComponentSkill(): string
    {
        return $this->loadSkill('ui-component-development');
    }

    /**
     * Returns message queue skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/message-queue',
        name: 'message_queue_skill',
        description: 'Magento 2 message queue and async processing skill',
        mimeType: 'text/markdown'
    )]
    public function getMessageQueueSkill(): string
    {
        return $this->loadSkill('message-queue');
    }

    /**
     * Returns import/export skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/import-export',
        name: 'import_export_skill',
        description: 'Magento 2 import/export customization skill',
        mimeType: 'text/markdown'
    )]
    public function getImportExportSkill(): string
    {
        return $this->loadSkill('import-export');
    }

    /**
     * Returns Hyva theme development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/hyva-theme-development',
        name: 'hyva_theme_development_skill',
        description: 'Hyva theme development skill (Alpine.js, Tailwind CSS, CSP, ViewModels)',
        mimeType: 'text/markdown'
    )]
    public function getHyvaThemeSkill(): string
    {
        return $this->loadSkill('hyva-theme-development');
    }

    /**
     * Returns Hyva UI component development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/hyva-ui-component-development',
        name: 'hyva_ui_component_skill',
        description: 'Hyva UI component development skill (CSS layers, custom properties, Alpine.js patterns)',
        mimeType: 'text/markdown'
    )]
    public function getHyvaUiComponentSkill(): string
    {
        return $this->loadSkill('hyva-ui-component-development');
    }

    /**
     * Returns Hyva Checkout & Magewire development skill documentation.
     *
     * @return string Markdown content
     */
    #[McpResource(
        uri: 'magento://skills/hyva-checkout-development',
        name: 'hyva_checkout_skill',
        description: 'Hyva Checkout & Magewire development skill (checkout steps, payment/shipping integration, evaluation API)',
        mimeType: 'text/markdown'
    )]
    public function getHyvaCheckoutSkill(): string
    {
        return $this->loadSkill('hyva-checkout-development');
    }

    /**
     * Returns available skills list.
     *
     * @return string Markdown content with skills index
     */
    #[McpResource(
        uri: 'magento://skills/index',
        name: 'skills_index',
        description: 'Index of all available Magento 2 development skills',
        mimeType: 'text/markdown'
    )]
    public function getSkillsIndex(): string
    {
        $skillsDir = dirname(__DIR__, 3) . '/config/skills';
        $skills = [];

        if (is_dir($skillsDir)) {
            $dirs = glob($skillsDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $skillName = basename($dir);
                $skillFile = $dir . '/SKILL.md';
                if (file_exists($skillFile)) {
                    $content = file_get_contents($skillFile);
                    // Extract first heading
                    if ($content && preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                        $skills[$skillName] = $matches[1];
                    } else {
                        $skills[$skillName] = ucwords(str_replace('-', ' ', $skillName));
                    }
                }
            }
        }

        ksort($skills);

        $markdown = "# Available Magento Development Skills\n\n";

        if (empty($skills)) {
            $markdown .= "No skills found in config/skills directory.\n";
        } else {
            foreach ($skills as $skillId => $skillTitle) {
                $markdown .= "- **{$skillTitle}** (`magento://skills/{$skillId}`)\n";
            }
        }

        return $markdown;
    }

    /**
     * Load skill content from file.
     *
     * @param string $skillName Skill directory name
     * @return string Content or placeholder message
     */
    private function loadSkill(string $skillName): string
    {
        return $this->loadConfigFile('skills', $skillName . '/SKILL.md');
    }
}
