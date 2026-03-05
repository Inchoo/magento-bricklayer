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

class EavTools
{
    use RequiresMagento;

    private const SUPPORTED_ENTITY_TYPES = [
        'catalog_product',
        'catalog_category',
        'customer',
        'customer_address',
    ];

    #[McpTool(
        name: 'eav-attributes',
        description: 'Check BEFORE working with product/customer/category data — shows all attributes including custom ones that exist only in database, not in code. Use verbosity for detail control.'
    )]
    public function getEavAttributes(string $entityType, bool $userDefinedOnly = false, string $verbosity = 'standard'): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
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

        if (!in_array($verbosity, ['minimal', 'standard', 'detailed'], true)) {
            return ['error' => true, 'message' => 'verbosity must be one of: minimal, standard, detailed'];
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
                $attrData = match ($verbosity) {
                    'minimal' => [
                        'attribute_code' => $attribute->getAttributeCode(),
                        'frontend_input' => $attribute->getFrontendInput(),
                    ],
                    'detailed' => [
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
                        'note' => method_exists($attribute, 'getNote') ? $attribute->getNote() : null,
                        'sort_order' => method_exists($attribute, 'getSortOrder') ? (int) $attribute->getSortOrder() : null,
                    ],
                    default => [
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
                    ],
                };

                $attributes[] = $attrData;
            }

            $result = [
                'entity_type' => $entityType,
                'entity_type_id' => $entityTypeId,
                'attribute_count' => $attributeList->getTotalCount(),
                'attributes' => $attributes,
            ];

            $result['_skill_hint'] = 'For EAV attribute creation and management patterns: development-context category=eav';

            return $result;
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'eav-entity-types',
        description: 'Returns all supported EAV entity types registered in the Magento system',
        meta: ['hidden' => true]
    )]
    public function getEntityTypes(): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
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
                }
            }

            return ['entity_types' => $entityTypes];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
