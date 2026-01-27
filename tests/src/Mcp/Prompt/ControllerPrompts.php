<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Controller Creation Prompts
 *
 * Provides MCP prompts for creating Magento controllers.
 */
class ControllerPrompts
{
    /**
     * Creates a frontend controller action.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $controllerPath Controller path (e.g., "Index/View")
     * @param string $routeName Route front name
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-frontend-controller',
        description: 'Creates a Magento 2 frontend controller action'
    )]
    public function createFrontendController(
        string $vendor,
        string $module,
        string $controllerPath,
        string $routeName = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $route = $routeName ?: strtolower($module);

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 frontend controller with the following specifications:

**Module:** {$moduleName}
**Controller Path:** {$controllerPath}
**Route:** {$route}

Generate:

1. **Controller/[Path].php** - Controller action class
2. **etc/frontend/routes.xml** - Route configuration

Requirements:
- Extend \Magento\Framework\App\Action\Action (or use \Magento\Framework\App\Action\HttpGetActionInterface for GET-only)
- Implement execute() method returning \Magento\Framework\Controller\ResultInterface
- Use ResultFactory to create responses (Page, Json, Redirect, Forward, Raw)
- Inject dependencies via constructor
- Add proper ACL checks if needed
- Include copyright header and PHPDoc

Example URL: /{$route}/{$controllerPath}

Controller patterns to consider:
- For page rendering: return \$this->resultPageFactory->create()
- For JSON response: return \$this->resultJsonFactory->create()->setData([])
- For redirects: return \$this->resultRedirectFactory->create()->setPath('path')
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates an adminhtml controller action.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $controllerPath Controller path (e.g., "Entity/Index")
     * @param string $aclResource ACL resource name
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-admin-controller',
        description: 'Creates a Magento 2 admin panel controller action'
    )]
    public function createAdminController(
        string $vendor,
        string $module,
        string $controllerPath,
        string $aclResource = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $acl = $aclResource ?: "{$moduleName}::manage";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 admin controller with the following specifications:

**Module:** {$moduleName}
**Controller Path:** {$controllerPath}
**ACL Resource:** {$acl}

Generate:

1. **Controller/Adminhtml/[Path].php** - Admin controller action class
2. **etc/adminhtml/routes.xml** - Admin route configuration
3. **etc/acl.xml** - ACL resource definition
4. **etc/adminhtml/menu.xml** - Admin menu item (optional)

Requirements:
- Extend \Magento\Backend\App\Action
- Implement ADMIN_RESOURCE constant for ACL
- Implement execute() method
- Use context from parent for result factories
- Add proper form key validation for POST requests
- Include authorization check via _isAllowed() if custom logic needed
- Include copyright header and PHPDoc

Admin controller specifics:
- Always validate form_key for state-changing operations
- Use messageManager for success/error messages
- Redirect back to listing after save/delete operations
- Handle exceptions and show user-friendly errors
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates an AJAX controller.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $controllerPath Controller path
     * @param string $area Area (frontend or adminhtml)
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-ajax-controller',
        description: 'Creates a Magento 2 AJAX controller returning JSON'
    )]
    public function createAjaxController(
        string $vendor,
        string $module,
        string $controllerPath,
        string $area = 'frontend'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 AJAX controller with the following specifications:

**Module:** {$moduleName}
**Controller Path:** {$controllerPath}
**Area:** {$area}

Generate:

1. **Controller/[Adminhtml/]{$controllerPath}.php** - AJAX controller
2. Route configuration for the area

Requirements:
- Implement \Magento\Framework\App\Action\HttpPostActionInterface (for POST)
- Return \Magento\Framework\Controller\Result\Json
- Validate request data
- Handle exceptions and return error responses
- Include CSRF protection considerations

Response format:
```php
return \$this->resultJsonFactory->create()->setData([
    'success' => true,
    'message' => 'Operation completed',
    'data' => []
]);
```

Error handling:
```php
try {
    // Operation
} catch (\Exception \$e) {
    return \$this->resultJsonFactory->create()->setData([
        'success' => false,
        'message' => \$e->getMessage()
    ]);
}
```
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a controller with form handling.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $entityName Entity being managed
     * @param string $area Area (frontend or adminhtml)
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-form-controller',
        description: 'Creates Magento 2 controllers for form handling (display and save)'
    )]
    public function createFormController(
        string $vendor,
        string $module,
        string $entityName,
        string $area = 'adminhtml'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create Magento 2 form controllers for managing {$entityName}:

**Module:** {$moduleName}
**Entity:** {$entityName}
**Area:** {$area}

Generate these controllers:

1. **Index.php** - List all entities (grid)
2. **NewAction.php** - Display form for new entity
3. **Edit.php** - Display form for editing entity
4. **Save.php** - Handle form submission
5. **Delete.php** - Handle entity deletion
6. **MassDelete.php** - Handle bulk deletion

Requirements:
- Use service contracts/repositories for data operations
- Validate form_key in Save/Delete controllers
- Use messageManager for user feedback
- Redirect appropriately after operations
- Handle "Save and Continue Edit" functionality
- Include proper exception handling
- Add ACL checks for all actions

Controller flow:
- Index -> renders UI component grid or block-based listing
- NewAction -> forwards to Edit with no ID
- Edit -> loads entity, passes to form
- Save -> validates, saves via repository, redirects
- Delete -> validates, deletes via repository, redirects
PROMPT
                ],
            ],
        ];
    }
}
