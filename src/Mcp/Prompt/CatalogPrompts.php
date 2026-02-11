<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Provides MCP prompts for catalog operations (products, categories).
 */
class CatalogPrompts
{
    /**
     * Guides through product creation.
     *
     * @param string $productType Product type (simple, configurable, virtual, bundle, grouped, downloadable)
     * @param string $attributeSet Attribute set name
     * @param string $sku Product SKU
     * @param string $name Product name
     * @param float $price Product price
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-product',
        description: 'Guides through creating a Magento product'
    )]
    public function createProduct(
        string $productType = 'simple',
        string $attributeSet = 'Default',
        string $sku = '',
        string $name = '',
        float $price = 0.00
    ): array {
        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Guide me through creating a Magento 2 product with these specifications:

**Product Type:** {$productType}
**Attribute Set:** {$attributeSet}
**SKU:** {$sku}
**Name:** {$name}
**Price:** {$price}

Provide step-by-step instructions including:

1. **Prerequisites**:
   - Required attribute set ID
   - Stock source configuration
   - Website assignments

2. **Product Data**:
   - Required fields for {$productType} products
   - Recommended optional attributes
   - SEO attributes (meta title, description, url_key)

3. **API/Code Approach**:
   - Using ProductRepositoryInterface
   - Setting required attributes
   - Configuring stock inventory

4. **Type-Specific Configuration**:
   For {$productType} products, explain:
   - Required additional configuration
   - Associated products (if applicable)
   - Options/variants setup (if applicable)

5. **Post-Creation Steps**:
   - Category assignment
   - Image upload
   - Related products
   - Index and cache management

Use the `product-create` tool to create the product, or provide the code to do so programmatically.
PROMPT
            ]
        ];
    }

    /**
     * Generates product import strategy.
     *
     * @param string $sourceFormat Source format (csv, json, xml, api)
     * @param string $entityType Entity type (products, categories, customers)
     * @param string $mapping Field mapping description
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'bulk-product-import',
        description: 'Generates a strategy for bulk product import'
    )]
    public function bulkProductImport(
        string $sourceFormat = 'csv',
        string $entityType = 'products',
        string $mapping = ''
    ): array {
        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a bulk import strategy for Magento 2 with these specifications:

**Source Format:** {$sourceFormat}
**Entity Type:** {$entityType}
**Field Mapping:** {$mapping}

Provide a comprehensive import strategy including:

1. **Data Preparation**:
   - Required columns/fields for {$entityType}
   - Data format requirements
   - Validation rules

2. **Import Method Selection**:
   - Magento native import (System > Import)
   - ImportExport module programmatic approach
   - Custom import using repositories
   - Third-party import tools comparison

3. **Field Mapping**:
   - Map source fields to Magento attributes
   - Handle custom attributes
   - Multi-select and dropdown values

4. **Code Implementation** (if custom):
   ```php
   // Provide sample import code using:
   // - \Magento\ImportExport\Model\Import
   // - ProductRepositoryInterface for API approach
   // - Direct database for performance (with caution)
   ```

5. **Error Handling**:
   - Validation before import
   - Logging failed records
   - Rollback strategy

6. **Performance Optimization**:
   - Batch processing
   - Index management during import
   - Memory considerations

7. **Post-Import Tasks**:
   - Reindex
   - Cache flush
   - Data verification
PROMPT
            ]
        ];
    }

    /**
     * Creates category hierarchy structure.
     *
     * @param string $parentCategory Parent category name or ID
     * @param string $children Comma-separated child category names
     * @param string $attributes Additional attributes to set
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-category-structure',
        description: 'Creates a category hierarchy structure'
    )]
    public function createCategoryStructure(
        string $parentCategory = 'Default Category',
        string $children = '',
        string $attributes = ''
    ): array {
        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a Magento 2 category structure with these specifications:

**Parent Category:** {$parentCategory}
**Children:** {$children}
**Attributes:** {$attributes}

Provide instructions for creating the category hierarchy:

1. **Category Tree Planning**:
   - Identify parent category ID
   - Plan hierarchy levels
   - URL key strategy

2. **Category Creation**:
   - Use CategoryRepositoryInterface
   - Set required attributes (name, is_active, include_in_menu)
   - Configure display settings

3. **Code Example**:
   ```php
   // Use category-create tool or provide code:
   // - Create parent categories first
   // - Set proper parent_id relationships
   // - Configure URL keys for SEO
   ```

4. **SEO Configuration**:
   - Meta title and description
   - URL rewrites
   - Canonical URLs

5. **Display Settings**:
   - Display mode (products only, static block, both)
   - Available sort by
   - Default sort by
   - Landing page

6. **Product Assignment**:
   - Manual assignment
   - Merchandiser rules (if Adobe Commerce)
   - Position/sorting

Use the `category-create` tool to create categories or provide the implementation code.
PROMPT
            ]
        ];
    }
}
