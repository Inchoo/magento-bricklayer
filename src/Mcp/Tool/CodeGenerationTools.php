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
 * Code Generation Tools
 *
 * Provides MCP tools for generating Magento 2 code scaffolding.
 */
class CodeGenerationTools
{
    /**
     * Scaffolds a new Magento 2 module.
     *
     * @param string $vendor Vendor name (e.g., "Acme")
     * @param string $module Module name (e.g., "CustomFeature")
     * @param string $version Module version (default: "1.0.0")
     * @return array<string, mixed> Generated module files
     */
    #[McpTool(
        name: 'generate-module',
        description: 'Scaffolds a new Magento 2 module with required files'
    )]
    public function generateModule(string $vendor, string $module, string $version = '1.0.0'): array
    {
        if ($vendor === '' || $module === '') {
            return ['error' => true, 'message' => 'Vendor and module names are required'];
        }

        $moduleName = "{$vendor}_{$module}";
        $vendorLower = strtolower($vendor);
        $moduleLower = strtolower($module);

        $files = [];

        // registration.php
        $files['registration.php'] = <<<PHP
<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    '{$moduleName}',
    __DIR__
);
PHP;

        // etc/module.xml
        $files['etc/module.xml'] = <<<XML
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="{$moduleName}"/>
</config>
XML;

        // composer.json
        $composerJson = [
            'name' => "{$vendorLower}/module-{$moduleLower}",
            'description' => "Magento 2 {$module} module by {$vendor}",
            'type' => 'magento2-module',
            'version' => $version,
            'license' => 'proprietary',
            'autoload' => [
                'files' => ['registration.php'],
                'psr-4' => [
                    "{$vendor}\\{$module}\\" => '',
                ],
            ],
        ];
        $files['composer.json'] = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            'success' => true,
            'module_name' => $moduleName,
            'path' => "app/code/{$vendor}/{$module}",
            'files' => $files,
            'instructions' => "Create these files in app/code/{$vendor}/{$module}/, then run: bin/magento setup:upgrade",
        ];
    }

    /**
     * Creates model, resource model, and collection classes.
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $entity Entity name (e.g., "Post")
     * @param string $table Database table name
     * @param string $fields Comma-separated field names (e.g., "title,content,status")
     * @return array<string, mixed> Generated model files
     */
    #[McpTool(
        name: 'generate-model',
        description: 'Creates model, resource model, and collection for an entity'
    )]
    public function generateModel(
        string $vendor,
        string $module,
        string $entity,
        string $table,
        string $fields = ''
    ): array {
        if ($vendor === '' || $module === '' || $entity === '' || $table === '') {
            return ['error' => true, 'message' => 'Vendor, module, entity, and table are required'];
        }

        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $entityLower = strtolower($entity);
        $fieldList = $fields !== '' ? array_map('trim', explode(',', $fields)) : [];

        $files = [];

        // Model class
        $gettersSetters = '';
        foreach ($fieldList as $field) {
            $methodName = str_replace('_', '', ucwords($field, '_'));
            $gettersSetters .= <<<PHP

    public function get{$methodName}(): ?string
    {
        return \$this->getData('{$field}');
    }

    public function set{$methodName}(string \$value): self
    {
        return \$this->setData('{$field}', \$value);
    }
PHP;
        }

        $files["Model/{$entity}.php"] = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace}\Model;

use Magento\Framework\Model\AbstractModel;
use {$namespace}\Model\ResourceModel\\{$entity} as {$entity}Resource;

class {$entity} extends AbstractModel
{
    protected function _construct(): void
    {
        \$this->_init({$entity}Resource::class);
    }
{$gettersSetters}
}
PHP;

        // Resource Model class
        $files["Model/ResourceModel/{$entity}.php"] = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace}\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class {$entity} extends AbstractDb
{
    protected function _construct(): void
    {
        \$this->_init('{$table}', 'entity_id');
    }
}
PHP;

        // Collection class
        $files["Model/ResourceModel/{$entity}/Collection.php"] = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace}\Model\ResourceModel\\{$entity};

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use {$namespace}\Model\\{$entity};
use {$namespace}\Model\ResourceModel\\{$entity} as {$entity}Resource;

class Collection extends AbstractCollection
{
    protected \$_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        \$this->_init({$entity}::class, {$entity}Resource::class);
    }
}
PHP;

        return [
            'success' => true,
            'module_name' => $moduleName,
            'entity' => $entity,
            'table' => $table,
            'files' => $files,
            'instructions' => "Create these files in app/code/{$vendor}/{$module}/",
        ];
    }

    /**
     * Creates a controller with optional layout and template.
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $area Area (frontend or adminhtml)
     * @param string $route Route name (e.g., "custom")
     * @param string $action Action name (e.g., "index")
     * @return array<string, mixed> Generated controller files
     */
    #[McpTool(
        name: 'generate-controller',
        description: 'Creates a controller with layout and template files'
    )]
    public function generateController(
        string $vendor,
        string $module,
        string $area = 'frontend',
        string $route = 'custom',
        string $action = 'index'
    ): array {
        if ($vendor === '' || $module === '') {
            return ['error' => true, 'message' => 'Vendor and module are required'];
        }

        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $actionClass = ucfirst($action);
        $routeLower = strtolower($route);

        $files = [];

        // Determine controller path based on area
        $controllerPath = $area === 'adminhtml' ? 'Controller/Adminhtml' : 'Controller';
        $baseClass = $area === 'adminhtml'
            ? 'Magento\Backend\App\Action'
            : 'Magento\Framework\App\Action\Action';
        $contextClass = $area === 'adminhtml'
            ? 'Magento\Backend\App\Action\Context'
            : 'Magento\Framework\App\Action\Context';

        // Controller class
        $files["{$controllerPath}/{$actionClass}/Index.php"] = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace}\\{$controllerPath}\\{$actionClass};

use {$baseClass};
use {$contextClass};
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;

class Index extends Action
{
    public function __construct(
        Context \$context,
        private readonly PageFactory \$resultPageFactory
    ) {
        parent::__construct(\$context);
    }

    public function execute(): ResultInterface
    {
        \$resultPage = \$this->resultPageFactory->create();
        \$resultPage->getConfig()->getTitle()->prepend(__('{$actionClass}'));
        return \$resultPage;
    }
}
PHP;

        // routes.xml
        $routerType = $area === 'adminhtml' ? 'admin' : 'standard';
        $routesFile = $area === 'adminhtml' ? 'etc/adminhtml/routes.xml' : 'etc/frontend/routes.xml';

        $files[$routesFile] = <<<XML
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
    <router id="{$routerType}">
        <route id="{$routeLower}" frontName="{$routeLower}">
            <module name="{$moduleName}"/>
        </route>
    </router>
</config>
XML;

        // Layout XML
        $layoutFile = $area === 'adminhtml'
            ? "view/adminhtml/layout/{$routeLower}_{$action}_index.xml"
            : "view/frontend/layout/{$routeLower}_{$action}_index.xml";

        $files[$layoutFile] = <<<XML
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="Magento\Framework\View\Element\Template"
                   name="{$routeLower}.{$action}.content"
                   template="{$moduleName}::{$action}/content.phtml"/>
        </referenceContainer>
    </body>
</page>
XML;

        // Template file
        $templateFile = $area === 'adminhtml'
            ? "view/adminhtml/templates/{$action}/content.phtml"
            : "view/frontend/templates/{$action}/content.phtml";

        $files[$templateFile] = <<<PHTML
<?php
/**
 * @var \Magento\Framework\View\Element\Template \$block
 */
?>
<div class="{$routeLower}-{$action}">
    <h1><?= \$block->escapeHtml(__('{$actionClass} Page')) ?></h1>
    <p><?= \$block->escapeHtml(__('This is the {$action} content.')) ?></p>
</div>
PHTML;

        return [
            'success' => true,
            'module_name' => $moduleName,
            'area' => $area,
            'route' => $route,
            'action' => $action,
            'url' => $area === 'adminhtml' ? "admin/{$routeLower}/{$action}/index" : "{$routeLower}/{$action}/index",
            'files' => $files,
            'instructions' => "Create these files in app/code/{$vendor}/{$module}/, then run: bin/magento cache:clean",
        ];
    }

    /**
     * Creates REST API endpoint.
     *
     * @param string $vendor Vendor name
     * @param string $module Module name
     * @param string $resource Resource name (e.g., "post")
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $path API path (e.g., "/V1/posts/:id")
     * @return array<string, mixed> Generated API files
     */
    #[McpTool(
        name: 'generate-api',
        description: 'Creates a REST API endpoint with interface and implementation'
    )]
    public function generateApi(
        string $vendor,
        string $module,
        string $resource,
        string $method = 'GET',
        string $path = ''
    ): array {
        if ($vendor === '' || $module === '' || $resource === '') {
            return ['error' => true, 'message' => 'Vendor, module, and resource are required'];
        }

        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $resourceClass = ucfirst($resource);
        $resourceLower = strtolower($resource);
        $methodUpper = strtoupper($method);

        if ($path === '') {
            $path = "/V1/{$resourceLower}s";
        }

        $files = [];

        // Service interface
        $files["Api/{$resourceClass}ManagementInterface.php"] = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace}\Api;

/**
 * {$resourceClass} Management Service Interface
 *
 * @api
 */
interface {$resourceClass}ManagementInterface
{
    /**
     * Get {$resourceLower} list
     *
     * @return array
     */
    public function getList(): array;

    /**
     * Get {$resourceLower} by ID
     *
     * @param int \$id
     * @return array
     */
    public function getById(int \$id): array;

    /**
     * Save {$resourceLower}
     *
     * @param mixed[] \$data
     * @return array
     */
    public function save(array \$data): array;

    /**
     * Delete {$resourceLower} by ID
     *
     * @param int \$id
     * @return bool
     */
    public function deleteById(int \$id): bool;
}
PHP;

        // Service implementation
        $files["Model/{$resourceClass}Management.php"] = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace}\Model;

use {$namespace}\Api\\{$resourceClass}ManagementInterface;

class {$resourceClass}Management implements {$resourceClass}ManagementInterface
{
    /**
     * @inheritDoc
     */
    public function getList(): array
    {
        // TODO: Implement actual logic
        return ['items' => [], 'total_count' => 0];
    }

    /**
     * @inheritDoc
     */
    public function getById(int \$id): array
    {
        // TODO: Implement actual logic
        return ['id' => \$id, 'message' => 'Not implemented'];
    }

    /**
     * @inheritDoc
     */
    public function save(array \$data): array
    {
        // TODO: Implement actual logic
        return ['success' => true, 'data' => \$data];
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int \$id): bool
    {
        // TODO: Implement actual logic
        return true;
    }
}
PHP;

        // di.xml for preference
        $files['etc/di.xml'] = <<<XML
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <preference for="{$namespace}\Api\\{$resourceClass}ManagementInterface"
                type="{$namespace}\Model\\{$resourceClass}Management"/>
</config>
XML;

        // webapi.xml
        $files['etc/webapi.xml'] = <<<XML
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">
    <route url="{$path}" method="GET">
        <service class="{$namespace}\Api\\{$resourceClass}ManagementInterface" method="getList"/>
        <resources>
            <resource ref="anonymous"/>
        </resources>
    </route>
    <route url="{$path}/:id" method="GET">
        <service class="{$namespace}\Api\\{$resourceClass}ManagementInterface" method="getById"/>
        <resources>
            <resource ref="anonymous"/>
        </resources>
    </route>
    <route url="{$path}" method="POST">
        <service class="{$namespace}\Api\\{$resourceClass}ManagementInterface" method="save"/>
        <resources>
            <resource ref="self"/>
        </resources>
    </route>
    <route url="{$path}/:id" method="DELETE">
        <service class="{$namespace}\Api\\{$resourceClass}ManagementInterface" method="deleteById"/>
        <resources>
            <resource ref="self"/>
        </resources>
    </route>
</routes>
XML;

        return [
            'success' => true,
            'module_name' => $moduleName,
            'resource' => $resource,
            'endpoints' => [
                "GET {$path}" => 'getList',
                "GET {$path}/:id" => 'getById',
                "POST {$path}" => 'save',
                "DELETE {$path}/:id" => 'deleteById',
            ],
            'files' => $files,
            'instructions' => "Create these files in app/code/{$vendor}/{$module}/, then run: bin/magento setup:di:compile",
        ];
    }
}
