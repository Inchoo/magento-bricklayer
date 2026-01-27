<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Block and Template Prompts
 *
 * Provides MCP prompts for creating Magento blocks and templates.
 */
class BlockPrompts
{
    /**
     * Creates a block class with template.
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $blockName Block class name (e.g., "ProductList")
     * @param string $area Area (frontend or adminhtml)
     * @param string $template Template path relative to templates/
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-block',
        description: 'Creates a Magento 2 block class with template'
    )]
    public function createBlock(
        string $vendor,
        string $module,
        string $blockName,
        string $area = 'frontend',
        string $template = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $templatePath = $template ?: strtolower($blockName) . '.phtml';

        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a Magento 2 block class with the following specifications:

**Module:** {$moduleName}
**Namespace:** {$namespace}
**Block Name:** {$blockName}
**Area:** {$area}
**Template:** {$templatePath}

Generate the following files:

1. **Block Class** (`Block/{$blockName}.php`):
   - Extend `Magento\Framework\View\Element\Template`
   - Use constructor property promotion for dependencies
   - Include `declare(strict_types=1);`
   - Add methods to provide data to the template
   - Include proper PHPDoc annotations

2. **Template File** (`view/{$area}/templates/{$templatePath}`):
   - Include proper escaping with `\$block->escapeHtml()`
   - Use `\$block->escapeUrl()` for URLs
   - Access block methods with `\$block->methodName()`
   - Include `@var` annotation at the top

3. **Layout XML** (if needed):
   - Reference the block in a layout handle
   - Set the template using the `template` attribute

Requirements:
- Follow Magento 2 coding standards
- Use proper type hints
- Include helpful methods that templates commonly need
- Demonstrate proper data escaping in templates
PROMPT
            ]
        ];
    }

    /**
     * Creates a UI Component definition.
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $componentName Component name
     * @param string $componentType Type (listing, form, etc.)
     * @param string $dataSource Data source class or config
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-ui-component',
        description: 'Creates a Magento 2 UI Component definition'
    )]
    public function createUiComponent(
        string $vendor,
        string $module,
        string $componentName,
        string $componentType = 'listing',
        string $dataSource = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";

        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a Magento 2 UI Component with the following specifications:

**Module:** {$moduleName}
**Component Name:** {$componentName}
**Component Type:** {$componentType}
**Data Source:** {$dataSource}

Generate the following files:

1. **UI Component XML** (`view/adminhtml/ui_component/{$componentName}.xml`):
   - Define the component structure
   - Configure data source and provider
   - Set up columns/fields as appropriate

2. **DataProvider Class** (`Ui/DataProvider/{$componentName}DataProvider.php`):
   - Extend appropriate base class
   - Implement data fetching logic
   - Configure collection or array data

3. **Layout XML** (to load the component):
   - Reference the UI component in admin layout

Requirements:
- Follow Magento UI Component patterns
- Include proper namespace declarations
- Use appropriate column types and configurations
- Include sorting, filtering, and pagination for listings
- Include validation and fieldsets for forms
PROMPT
            ]
        ];
    }

    /**
     * Creates an admin grid with columns.
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $entityName Entity name for the grid
     * @param string $columns Comma-separated column names
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-admin-grid',
        description: 'Creates a Magento 2 admin grid with columns'
    )]
    public function createAdminGrid(
        string $vendor,
        string $module,
        string $entityName,
        string $columns = 'id,name,status,created_at'
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $columnList = array_map('trim', explode(',', $columns));

        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a Magento 2 admin grid for entity "{$entityName}" with the following specifications:

**Module:** {$moduleName}
**Entity:** {$entityName}
**Columns:** {$columns}

Generate these files:

1. **UI Component Listing** (`view/adminhtml/ui_component/{$entityName}_listing.xml`):
   - Configure listing with columns: {$columns}
   - Include mass actions (delete, status change)
   - Add filters and sorting
   - Configure pagination

2. **Grid Collection DataProvider** (`Ui/DataProvider/{$entityName}DataProvider.php`):
   - Extend ListingDataProvider or implement DataProviderInterface
   - Configure collection source

3. **Controller for Grid Page** (`Controller/Adminhtml/{$entityName}/Index.php`):
   - Return page result with layout

4. **Layout XML** (`view/adminhtml/layout/{$entityName}_index_index.xml`):
   - Configure page and load UI component

5. **Menu Item** (`etc/adminhtml/menu.xml`):
   - Add menu entry for the grid

6. **ACL Resource** (`etc/acl.xml`):
   - Define ACL resources for the grid

Requirements:
- Use proper UI component structure
- Include text, date, and select column types as appropriate
- Add row actions (edit, delete)
- Configure proper data sources
PROMPT
            ]
        ];
    }

    /**
     * Creates an admin form with fields.
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $entityName Entity name for the form
     * @param string $fields Comma-separated field names
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-admin-form',
        description: 'Creates a Magento 2 admin form with fields'
    )]
    public function createAdminForm(
        string $vendor,
        string $module,
        string $entityName,
        string $fields = 'name,description,status,sort_order'
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";

        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a Magento 2 admin form for entity "{$entityName}" with the following specifications:

**Module:** {$moduleName}
**Entity:** {$entityName}
**Fields:** {$fields}

Generate these files:

1. **UI Component Form** (`view/adminhtml/ui_component/{$entityName}_form.xml`):
   - Configure form with fields: {$fields}
   - Include fieldsets for organization
   - Add validation rules
   - Configure save and back buttons

2. **Form DataProvider** (`Ui/DataProvider/{$entityName}FormDataProvider.php`):
   - Load entity data for editing
   - Handle new entity case

3. **Controllers**:
   - `Controller/Adminhtml/{$entityName}/Edit.php` - Load form page
   - `Controller/Adminhtml/{$entityName}/Save.php` - Process form submission
   - `Controller/Adminhtml/{$entityName}/Delete.php` - Handle deletion
   - `Controller/Adminhtml/{$entityName}/NewAction.php` - New entity form

4. **Layout XML**:
   - `view/adminhtml/layout/{$entityName}_edit.xml`
   - `view/adminhtml/layout/{$entityName}_new.xml`

Requirements:
- Use proper UI component form structure
- Include field validation
- Handle both create and edit scenarios
- Add proper success/error messages
- Implement proper redirect after save
PROMPT
            ]
        ];
    }

    /**
     * Creates a setup patch (data or schema).
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $patchName Patch class name
     * @param string $patchType Type: data or schema
     * @param string $description Description of what the patch does
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-setup-patch',
        description: 'Creates a Magento 2 data or schema patch'
    )]
    public function createSetupPatch(
        string $vendor,
        string $module,
        string $patchName,
        string $patchType = 'data',
        string $description = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $patchFolder = ucfirst($patchType);

        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a Magento 2 {$patchType} patch with the following specifications:

**Module:** {$moduleName}
**Patch Name:** {$patchName}
**Patch Type:** {$patchType}
**Description:** {$description}

Generate the patch file at `Setup/Patch/{$patchFolder}/{$patchName}.php`:

Requirements:
- Implement `{$patchFolder}PatchInterface`
- Include `declare(strict_types=1);`
- Implement `apply()` method with the patch logic
- Implement `getDependencies()` to declare dependencies on other patches
- Implement `getAliases()` for backward compatibility
- Use proper dependency injection for required services
- Add comprehensive PHPDoc documentation

For Data Patches:
- Use ModuleDataSetupInterface
- Implement reversible changes where possible

For Schema Patches:
- Use SchemaSetupInterface
- Create/modify tables, columns, indexes, foreign keys
- Consider using declarative schema (db_schema.xml) instead when appropriate
PROMPT
            ]
        ];
    }
}
