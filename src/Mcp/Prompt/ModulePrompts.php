<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Module Creation Prompts
 *
 * Provides MCP prompts for creating Magento modules and components.
 */
class ModulePrompts
{
    /**
     * Creates a complete Magento 2 module structure.
     *
     * @param string $vendor The vendor name (e.g., "Acme")
     * @param string $module The module name (e.g., "CustomFeature")
     * @param string $version The module version (default: "1.0.0")
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-module',
        description: 'Creates a complete Magento 2 module structure with all required files'
    )]
    public function createModule(
        string $vendor,
        string $module,
        string $version = '1.0.0'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a complete Magento 2 module with the following specifications:

**Module Name:** {$moduleName}
**Version:** {$version}

Generate all required files:

1. `app/code/{$vendor}/{$module}/registration.php` - Module registration
2. `app/code/{$vendor}/{$module}/etc/module.xml` - Module declaration with version
3. `app/code/{$vendor}/{$module}/composer.json` - Composer package definition

Follow these requirements:
- Use `declare(strict_types=1);` in all PHP files
- Follow Magento 2 coding standards (PSR-12)
- Use the correct XML schemas
- Set appropriate composer type as "magento2-module"

The module should be ready to install via `bin/magento setup:upgrade`.
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a plugin (interceptor) for modifying existing functionality.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $targetClass The fully qualified class name to intercept
     * @param string $targetMethod The method to intercept
     * @param string $pluginType The plugin type: before, after, or around
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-plugin',
        description: 'Creates a Magento 2 plugin (interceptor) for modifying existing functionality'
    )]
    public function createPlugin(
        string $vendor,
        string $module,
        string $targetClass,
        string $targetMethod,
        string $pluginType = 'after'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 plugin with the following specifications:

**Module:** {$moduleName}
**Target Class:** {$targetClass}
**Target Method:** {$targetMethod}
**Plugin Type:** {$pluginType}

Generate:

1. Plugin class in `Plugin/` directory with appropriate name
2. `etc/di.xml` configuration to register the plugin

Requirements:
- Use proper type hints for \$subject and \$result parameters
- Follow the correct plugin method naming convention ({$pluginType}Ucfirst({$targetMethod}))
- Include PHPDoc with @param and @return annotations
- Use constructor property promotion for any dependencies
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates an event observer.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $eventName The event name to observe
     * @param string $observerName The observer class name
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-observer',
        description: 'Creates a Magento 2 event observer'
    )]
    public function createObserver(
        string $vendor,
        string $module,
        string $eventName,
        string $observerName
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 event observer with the following specifications:

**Module:** {$moduleName}
**Event Name:** {$eventName}
**Observer Class:** {$observerName}

Generate:

1. Observer class implementing \Magento\Framework\Event\ObserverInterface
2. `etc/events.xml` configuration to register the observer

Requirements:
- Implement the execute(\Magento\Framework\Event\Observer \$observer) method
- Extract event data properly from the observer
- Use constructor DI for any dependencies
- Include appropriate error handling
- Include PHPDoc
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a model with repository pattern.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $entityName The entity name (e.g., "CustomEntity")
     * @param string $tableName The database table name
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-model',
        description: 'Creates a Magento 2 model with repository pattern'
    )]
    public function createModel(
        string $vendor,
        string $module,
        string $entityName,
        string $tableName
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a complete Magento 2 model structure with repository pattern:

**Module:** {$moduleName}
**Entity Name:** {$entityName}
**Table Name:** {$tableName}

Generate these files:

1. **Api/Data/{$entityName}Interface.php** - Data interface with getters/setters
2. **Api/{$entityName}RepositoryInterface.php** - Repository interface with CRUD methods
3. **Model/{$entityName}.php** - Model class implementing the data interface
4. **Model/ResourceModel/{$entityName}.php** - Resource model
5. **Model/ResourceModel/{$entityName}/Collection.php** - Collection class
6. **Model/{$entityName}Repository.php** - Repository implementation
7. **etc/di.xml** - DI configuration with preferences

Requirements:
- Use service contracts (interfaces)
- Include SearchResultsInterface for list operations
- Use SearchCriteriaBuilder for filtering
- Follow Magento's repository pattern conventions
- Include proper exception handling
- Add @api annotation to public interface methods
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a REST API endpoint.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $resourcePath The API resource path (e.g., "/V1/custom/items")
     * @param string $httpMethod The HTTP method (GET, POST, PUT, DELETE)
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-api-endpoint',
        description: 'Creates a Magento 2 REST API endpoint'
    )]
    public function createApiEndpoint(
        string $vendor,
        string $module,
        string $resourcePath,
        string $httpMethod = 'GET'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 REST API endpoint:

**Module:** {$moduleName}
**Resource Path:** {$resourcePath}
**HTTP Method:** {$httpMethod}

Generate:

1. **Api/ServiceInterface.php** - Service contract interface
2. **Model/Service.php** - Service implementation
3. **etc/webapi.xml** - API route configuration
4. **etc/di.xml** - DI preference for service interface

Requirements:
- Define clear input/output types in interface
- Use appropriate ACL resource (or anonymous access if applicable)
- Include proper PHPDoc with @api annotation
- Handle exceptions and return appropriate responses
- Follow REST conventions for the HTTP method
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a console command.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $commandName The command name (e.g., "custom:process")
     * @param string $description Command description
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-console-command',
        description: 'Creates a Magento 2 console command'
    )]
    public function createConsoleCommand(
        string $vendor,
        string $module,
        string $commandName,
        string $description = 'Custom console command'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 console command:

**Module:** {$moduleName}
**Command Name:** {$commandName}
**Description:** {$description}

Generate:

1. **Console/Command/CustomCommand.php** - Command class extending Symfony Command
2. **etc/di.xml** - Command registration

Requirements:
- Extend \Symfony\Component\Console\Command\Command
- Configure command name and description in configure()
- Implement execute() method with InputInterface and OutputInterface
- Add appropriate arguments and options if needed
- Use SymfonyStyle for formatted output
- Include proper error handling and exit codes
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a cron job.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $jobName The cron job name
     * @param string $schedule The cron schedule expression (e.g., "0 * * * *")
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-cron-job',
        description: 'Creates a Magento 2 cron job'
    )]
    public function createCronJob(
        string $vendor,
        string $module,
        string $jobName,
        string $schedule = '0 * * * *'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a Magento 2 cron job:

**Module:** {$moduleName}
**Job Name:** {$jobName}
**Schedule:** {$schedule}

Generate:

1. **Cron/JobClass.php** - Cron job class with execute() method
2. **etc/crontab.xml** - Cron schedule configuration
3. **etc/cron_groups.xml** (optional) - Custom cron group if needed

Requirements:
- Implement execute() method that returns void
- Use dependency injection for services
- Include proper logging for job execution
- Handle exceptions gracefully
- Add configurable schedule via system.xml if appropriate
PROMPT
                ],
            ],
        ];
    }
}
