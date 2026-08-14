<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

class CodeGenerationTools
{
    use ChecksConfig;
    use RequiresMagento;

    #[McpTool(
        name: 'generate-module',
        description: 'Scaffold a new module.',
        meta: ['hidden' => true]
    )]
    public function generateModule(string $vendor, string $module, string $version = '1.0.0', bool $dry_run = false, bool $force = false): array
    {
        if ($vendor === '' || $module === '') {
            return ['error' => true, 'message' => 'Vendor and module names are required'];
        }

        if ($error = $this->requireToolEnabled('generate-module')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('generate-module')) {
            return $error;
        }

        $moduleName = "{$vendor}_{$module}";
        $vendorLower = strtolower($vendor);
        $moduleLower = strtolower($module);

        $files = [];

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

        $files['etc/module.xml'] = <<<XML
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="{$moduleName}"/>
</config>
XML;

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

        $basePath = "app/code/{$vendor}/{$module}";
        $writeResult = ['written' => false, 'conflicts' => []];
        if (!$dry_run) {
            $writeResult = $this->writeFiles($basePath, $files, $force);
            if (!$writeResult['written'] && !empty($writeResult['conflicts'])) {
                return [
                    'success' => false,
                    'module_name' => $moduleName,
                    'path' => $basePath,
                    'conflicts' => $writeResult['conflicts'],
                    'message' => $writeResult['message'] ?? '',
                ];
            }
        }

        return [
            'success' => true,
            'module_name' => $moduleName,
            'path' => $basePath,
            'files' => $dry_run ? $this->annotateFiles($basePath, $files) : array_keys($files),
            'written' => $writeResult['written'],
            'instructions' => $writeResult['written']
                ? "Files created in {$basePath}/. Run: bin/magento setup:upgrade"
                : "Create these files in {$basePath}/, then run: bin/magento setup:upgrade",
        ];
    }

    #[McpTool(
        name: 'generate-model',
        description: 'Generate model, resource model, and collection.',
        meta: ['hidden' => true]
    )]
    public function generateModel(
        string $vendor,
        string $module,
        string $entity,
        string $table,
        string $fields = '',
        bool $dry_run = false,
        bool $force = false
    ): array {
        if ($vendor === '' || $module === '' || $entity === '' || $table === '') {
            return ['error' => true, 'message' => 'Vendor, module, entity, and table are required'];
        }

        if ($error = $this->requireToolEnabled('generate-model')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('generate-model')) {
            return $error;
        }

        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $entityLower = strtolower($entity);
        $parsedFields = $this->parseFields($fields);

        $files = [];
        $gettersSetters = '';
        foreach ($parsedFields as $f) {
            $methodName = str_replace('_', '', ucwords($f['name'], '_'));
            $phpType = $f['php_type'];
            $gettersSetters .= <<<PHP

    /**
     * @return {$phpType}|null
     */
    public function get{$methodName}(): ?{$phpType}
    {
        return \$this->getData('{$f['name']}');
    }

    /**
     * @param {$phpType} \$value
     * @return self
     */
    public function set{$methodName}({$phpType} \$value): self
    {
        return \$this->setData('{$f['name']}', \$value);
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
    /**
     * @return void
     */
    protected function _construct(): void
    {
        \$this->_init({$entity}Resource::class);
    }
{$gettersSetters}
}
PHP;

        $files["Model/ResourceModel/{$entity}.php"] = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class {$entity} extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        \$this->_init('{$table}', 'entity_id');
    }
}
PHP;

        $files["Model/ResourceModel/{$entity}/Collection.php"] = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Model\ResourceModel\\{$entity};

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use {$namespace}\Model\\{$entity};
use {$namespace}\Model\ResourceModel\\{$entity} as {$entity}Resource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected string \$_idFieldName = 'entity_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        \$this->_init({$entity}::class, {$entity}Resource::class);
    }
}
PHP;

        $files['etc/db_schema.xml'] = $this->buildDbSchema($table, $parsedFields);

        $basePath = "app/code/{$vendor}/{$module}";
        $writeResult = ['written' => false, 'conflicts' => []];
        if (!$dry_run) {
            $writeResult = $this->writeFiles($basePath, $files, $force);
            if (!$writeResult['written'] && !empty($writeResult['conflicts'])) {
                return [
                    'success' => false,
                    'module_name' => $moduleName,
                    'entity' => $entity,
                    'table' => $table,
                    'conflicts' => $writeResult['conflicts'],
                    'message' => $writeResult['message'] ?? '',
                ];
            }
        }

        return [
            'success' => true,
            'module_name' => $moduleName,
            'entity' => $entity,
            'table' => $table,
            'files' => $dry_run ? $this->annotateFiles($basePath, $files) : array_keys($files),
            'written' => $writeResult['written'],
            'instructions' => $writeResult['written']
                ? "Files created in {$basePath}/. Run: bin/magento setup:upgrade"
                : "Create these files in {$basePath}/, then run: bin/magento setup:upgrade",
        ];
    }

    #[McpTool(
        name: 'generate-controller',
        description: 'Generate controller with layout and template.',
        meta: ['hidden' => true]
    )]
    public function generateController(
        string $vendor,
        string $module,
        string $area = 'frontend',
        string $route = 'custom',
        string $action = 'index',
        bool $dry_run = false,
        bool $force = false
    ): array {
        if ($vendor === '' || $module === '') {
            return ['error' => true, 'message' => 'Vendor and module are required'];
        }

        if ($error = $this->requireToolEnabled('generate-controller')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('generate-controller')) {
            return $error;
        }

        $moduleName = "{$vendor}_{$module}";
        $namespace = "{$vendor}\\{$module}";
        $actionClass = ucfirst($action);
        $routeLower = strtolower($route);

        $files = [];
        $controllerPath = $area === 'adminhtml' ? 'Controller/Adminhtml' : 'Controller';
        $baseClass = $area === 'adminhtml'
            ? 'Magento\Backend\App\Action'
            : 'Magento\Framework\App\Action\Action';
        $contextClass = $area === 'adminhtml'
            ? 'Magento\Backend\App\Action\Context'
            : 'Magento\Framework\App\Action\Context';

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
    /**
     * @param Context \$context
     * @param PageFactory \$resultPageFactory
     */
    public function __construct(
        Context \$context,
        private readonly PageFactory \$resultPageFactory
    ) {
        parent::__construct(\$context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        \$resultPage = \$this->resultPageFactory->create();
        \$resultPage->getConfig()->getTitle()->prepend(__('{$actionClass}'));
        return \$resultPage;
    }
}
PHP;

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

        $templateFile = $area === 'adminhtml'
            ? "view/adminhtml/templates/{$action}/content.phtml"
            : "view/frontend/templates/{$action}/content.phtml";

        $files[$templateFile] = <<<PHTML
<?php

declare(strict_types=1);

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;

/** @var Template \$block */
/** @var Escaper \$escaper */
?>
<div class="{$routeLower}-{$action}">
    <h1><?= \$escaper->escapeHtml(__('{$actionClass} Page')) ?></h1>
    <p><?= \$escaper->escapeHtml(__('This is the {$action} content.')) ?></p>
</div>
PHTML;

        $basePath = "app/code/{$vendor}/{$module}";
        $writeResult = ['written' => false, 'conflicts' => []];
        if (!$dry_run) {
            $writeResult = $this->writeFiles($basePath, $files, $force);
            if (!$writeResult['written'] && !empty($writeResult['conflicts'])) {
                return [
                    'success' => false,
                    'module_name' => $moduleName,
                    'area' => $area,
                    'route' => $route,
                    'action' => $action,
                    'conflicts' => $writeResult['conflicts'],
                    'message' => $writeResult['message'] ?? '',
                ];
            }
        }

        return [
            'success' => true,
            'module_name' => $moduleName,
            'area' => $area,
            'route' => $route,
            'action' => $action,
            'url' => $area === 'adminhtml' ? "admin/{$routeLower}/{$action}/index" : "{$routeLower}/{$action}/index",
            'files' => $dry_run ? $this->annotateFiles($basePath, $files) : array_keys($files),
            'written' => $writeResult['written'],
            'instructions' => $writeResult['written']
                ? "Files created in {$basePath}/. Run: bin/magento cache:clean"
                : "Create these files in {$basePath}/, then run: bin/magento cache:clean",
        ];
    }

    #[McpTool(
        name: 'generate-api',
        description: 'Generate REST API endpoint with interface.',
        meta: ['hidden' => true]
    )]
    public function generateApi(
        string $vendor,
        string $module,
        string $resource,
        string $method = 'GET',
        string $path = '',
        bool $dry_run = false,
        bool $force = false
    ): array {
        if ($vendor === '' || $module === '' || $resource === '') {
            return ['error' => true, 'message' => 'Vendor, module, and resource are required'];
        }

        if ($error = $this->requireToolEnabled('generate-api')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('generate-api')) {
            return $error;
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

        $files["Model/{$resourceClass}Management.php"] = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Model;

use {$namespace}\Api\\{$resourceClass}ManagementInterface;

class {$resourceClass}Management implements {$resourceClass}ManagementInterface
{
    /**
     * @return array
     */
    public function getList(): array
    {
        return ['items' => [], 'total_count' => 0];
    }

    /**
     * @param int $id
     * @return array
     */
    public function getById(int \$id): array
    {
        return ['id' => \$id];
    }

    /**
     * @param array $data
     * @return array
     */
    public function save(array \$data): array
    {
        return ['success' => true, 'data' => \$data];
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById(int \$id): bool
    {
        return true;
    }
}
PHP;

        $files['etc/di.xml'] = <<<XML
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <preference for="{$namespace}\Api\\{$resourceClass}ManagementInterface"
                type="{$namespace}\Model\\{$resourceClass}Management"/>
</config>
XML;

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

        $basePath = "app/code/{$vendor}/{$module}";
        $writeResult = ['written' => false, 'conflicts' => []];
        if (!$dry_run) {
            $writeResult = $this->writeFiles($basePath, $files, $force);
            if (!$writeResult['written'] && !empty($writeResult['conflicts'])) {
                return [
                    'success' => false,
                    'module_name' => $moduleName,
                    'resource' => $resource,
                    'conflicts' => $writeResult['conflicts'],
                    'message' => $writeResult['message'] ?? '',
                ];
            }
        }

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
            'files' => $dry_run ? $this->annotateFiles($basePath, $files) : array_keys($files),
            'written' => $writeResult['written'],
            'instructions' => $writeResult['written']
                ? "Files created in {$basePath}/. Run: bin/magento setup:di:compile"
                : "Create these files in {$basePath}/, then run: bin/magento setup:di:compile",
        ];
    }

    /**
     * Write generated files to disk under the Magento root.
     *
     * @param string $basePath Relative path from Magento root (e.g. "app/code/Vendor/Module")
     * @param array<string, string> $files Map of relative file path => content
     * @param bool $force Overwrite existing files
     * @return array{written: bool, conflicts: string[]}
     */
    private function writeFiles(string $basePath, array $files, bool $force = false): array
    {
        $root = MagentoBootstrap::isInitialized()
            ? (defined('BP') ? BP : getcwd())
            : getcwd();

        $root = rtrim($root, '/');
        $absoluteBase = $root . '/' . $basePath;

        // Check for existing files
        if (!$force) {
            $existing = [];
            foreach ($files as $relativePath => $content) {
                $fullPath = $absoluteBase . '/' . $relativePath;
                if (file_exists($fullPath)) {
                    $existing[] = $relativePath;
                }
            }
            if (!empty($existing)) {
                return [
                    'written' => false,
                    'conflicts' => $existing,
                    'message' => count($existing) . ' file(s) already exist. Use force=true to overwrite.',
                ];
            }
        }

        foreach ($files as $relativePath => $content) {
            $fullPath = $absoluteBase . '/' . $relativePath;
            $dir = dirname($fullPath);

            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['written' => false, 'conflicts' => []];
            }

            // Path traversal protection: verify resolved path stays within Magento root
            $realDir = realpath($dir);
            if ($realDir === false || !str_starts_with($realDir, $root)) {
                return ['written' => false, 'conflicts' => []];
            }

            if (file_put_contents($fullPath, $content) === false) {
                return ['written' => false, 'conflicts' => []];
            }
        }

        return ['written' => true, 'conflicts' => []];
    }

    /**
     * Annotate file list for dry-run output with new/exists status.
     *
     * @param string $basePath Relative path from Magento root
     * @param array<string, string> $files Map of relative file path => content
     * @return array<array{path: string, status: string}>
     */
    private function annotateFiles(string $basePath, array $files): array
    {
        $root = MagentoBootstrap::isInitialized()
            ? (defined('BP') ? BP : getcwd())
            : getcwd();

        $root = rtrim($root, '/');
        $absoluteBase = $root . '/' . $basePath;

        return array_map(fn($path) => [
            'path' => $path,
            'status' => file_exists($absoluteBase . '/' . $path) ? 'exists' : 'new',
        ], array_keys($files));
    }

    private function buildDbSchema(string $table, array $parsedFields): string
    {
        $columns = '        <column xsi:type="int" name="entity_id" unsigned="true" nullable="false"'
            . ' identity="true" comment="Entity ID"/>';

        foreach ($parsedFields as $f) {
            $name = $f['name'];
            $xsi = $f['xsi_type'];
            $nullable = $f['nullable'] ? 'true' : 'false';
            $attrs = " name=\"{$name}\" nullable=\"{$nullable}\"";

            $extra = match ($xsi) {
                'varchar' => ' length="255"',
                'decimal' => ' scale="4" precision="12"',
                'text', 'blob' => '',
                'int', 'smallint', 'bigint' => ' unsigned="false"',
                'timestamp' => ' default="CURRENT_TIMESTAMP"',
                default => '',
            };

            $comment = ucwords(str_replace('_', ' ', $name));
            $columns .= "\n        <column xsi:type=\"{$xsi}\"{$attrs}{$extra} comment=\"{$comment}\"/>";
        }

        return <<<XML
<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">
    <table name="{$table}" resource="default" engine="innodb" comment="{$table}">
{$columns}
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="entity_id"/>
        </constraint>
    </table>
</schema>
XML;
    }

    private const TYPE_MAP = [
        'int' => ['php' => 'int', 'xsi' => 'int', 'unsigned' => false, 'nullable' => true],
        'integer' => ['php' => 'int', 'xsi' => 'int', 'unsigned' => false, 'nullable' => true],
        'smallint' => ['php' => 'int', 'xsi' => 'smallint', 'unsigned' => false, 'nullable' => true],
        'bigint' => ['php' => 'int', 'xsi' => 'bigint', 'unsigned' => false, 'nullable' => true],
        'float' => ['php' => 'float', 'xsi' => 'decimal', 'unsigned' => false, 'nullable' => true],
        'decimal' => ['php' => 'float', 'xsi' => 'decimal', 'unsigned' => false, 'nullable' => true],
        'bool' => ['php' => 'bool', 'xsi' => 'boolean', 'unsigned' => false, 'nullable' => false],
        'boolean' => ['php' => 'bool', 'xsi' => 'boolean', 'unsigned' => false, 'nullable' => false],
        'varchar' => ['php' => 'string', 'xsi' => 'varchar', 'unsigned' => false, 'nullable' => true],
        'text' => ['php' => 'string', 'xsi' => 'text', 'unsigned' => false, 'nullable' => true],
        'timestamp' => ['php' => 'string', 'xsi' => 'timestamp', 'unsigned' => false, 'nullable' => true],
        'datetime' => ['php' => 'string', 'xsi' => 'datetime', 'unsigned' => false, 'nullable' => true],
        'date' => ['php' => 'string', 'xsi' => 'date', 'unsigned' => false, 'nullable' => true],
        'blob' => ['php' => 'string', 'xsi' => 'blob', 'unsigned' => false, 'nullable' => true],
    ];

    /**
     * Parse field definitions from comma-separated string.
     *
     * Supports formats: "name", "name:type", plain column names.
     *
     * @return array<array{name: string, type: string, php_type: string, xsi_type: string}>
     */
    private function parseFields(string $fields): array
    {
        if ($fields === '') {
            return [];
        }

        $parsed = [];
        foreach (array_map('trim', explode(',', $fields)) as $entry) {
            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, ':')) {
                [$name, $type] = explode(':', $entry, 2);
                $name = trim($name);
                $type = strtolower(trim($type));
            } else {
                $name = $entry;
                $type = 'varchar';
            }

            $mapped = self::TYPE_MAP[$type] ?? self::TYPE_MAP['varchar'];
            $parsed[] = [
                'name' => $name,
                'type' => $type,
                'php_type' => $mapped['php'],
                'xsi_type' => $mapped['xsi'],
                'nullable' => $mapped['nullable'],
            ];
        }

        return $parsed;
    }
}
