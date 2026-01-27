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
 * EAV Tools
 *
 * Provides MCP tools for inspecting Magento's EAV system.
 */
class EavTools
{
    /**
     * Supported entity types
     */
    private const SUPPORTED_ENTITY_TYPES = [
        'catalog_product',
        'catalog_category',
        'customer',
        'customer_address',
    ];

    /**
     * Returns EAV attributes for a specified entity type.
     *
     * @param string $entityType The EAV entity type code (catalog_product, catalog_category, customer, customer_address)
     * @param bool $userDefinedOnly If true, returns only user-defined (custom) attributes
     * @return array<string, mixed> The list of attributes with metadata
     */
    #[McpTool(
        name: 'eav-attributes',
        description: 'Returns EAV attributes for a specified entity type (catalog_product, catalog_category, customer, customer_address)'
    )]
    public function getEavAttributes(string $entityType, bool $userDefinedOnly = false): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        if (!in_array($entityType, self::SUPPORTED_ENTITY_TYPES, true)) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Invalid entity type "%s". Supported types: %s',
                    $entityType,
                    implode(', ', self::SUPPORTED_ENTITY_TYPES)
                ),
            ];
        }

        try {
            $eavConfig = MagentoBootstrap::get(\Magento\Eav\Model\Config::class);
            $entityTypeModel = $eavConfig->getEntityType($entityType);
            $entityTypeId = (int) $entityTypeModel->getId();

            $attributeRepository = MagentoBootstrap::get(\Magento\Eav\Api\AttributeRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

            if ($userDefinedOnly) {
                $searchCriteriaBuilder->addFilter('is_user_defined', 1);
            }

            $searchCriteria = $searchCriteriaBuilder->create();
            $attributeList = $attributeRepository->getList($entityType, $searchCriteria);

            $attributes = [];
            foreach ($attributeList->getItems() as $attribute) {
                $attributes[] = [
                    'attribute_id' => (int) $attribute->getAttributeId(),
                    'attribute_code' => $attribute->getAttributeCode(),
                    'frontend_label' => $attribute->getDefaultFrontendLabel(),
                    'backend_type' => $attribute->getBackendType(),
                    'frontend_input' => $attribute->getFrontendInput(),
                    'backend_model' => $attribute->getBackendModel(),
                    'source_model' => $attribute->getSourceModel(),
                    'is_required' => (bool) $attribute->getIsRequired(),
                    'is_user_defined' => (bool) $attribute->getIsUserDefined(),
                    'is_unique' => (bool) $attribute->getIsUnique(),
                    'default_value' => $attribute->getDefaultValue(),
                ];
            }

            return [
                'entity_type' => $entityType,
                'entity_type_id' => $entityTypeId,
                'attribute_count' => $attributeList->getTotalCount(),
                'attributes' => $attributes,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns all EAV entity types registered in the system.
     *
     * @return array<string, mixed> List of entity types with metadata
     */
    #[McpTool(
        name: 'eav-entity-types',
        description: 'Returns all supported EAV entity types registered in the Magento system'
    )]
    public function getEntityTypes(): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $eavConfig = MagentoBootstrap::get(\Magento\Eav\Model\Config::class);

            $entityTypes = [];
            foreach (self::SUPPORTED_ENTITY_TYPES as $entityTypeCode) {
                try {
                    $entityType = $eavConfig->getEntityType($entityTypeCode);
                    $entityTypes[] = [
                        'entity_type_id' => (int) $entityType->getId(),
                        'entity_type_code' => $entityType->getEntityTypeCode(),
                        'entity_model' => $entityType->getEntityModel(),
                        'attribute_model' => $entityType->getAttributeModel(),
                        'entity_table' => $entityType->getEntityTable(),
                    ];
                } catch (\Throwable $e) {
                    // Entity type not available, skip
                }
            }

            return ['entity_types' => $entityTypes];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
