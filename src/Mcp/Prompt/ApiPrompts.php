<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Provides MCP prompts for creating Magento REST and GraphQL APIs.
 */
class ApiPrompts
{
    /**
     * Creates a complete REST API resource.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $resourceName Resource name (e.g., "CustomItem")
     * @param string $basePath API base path (e.g., "/V1/custom")
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-rest-api',
        description: 'Creates a complete REST API resource with CRUD operations'
    )]
    public function createRestApi(
        string $vendor,
        string $module,
        string $resourceName,
        string $basePath = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $path = $basePath ?: '/V1/' . strtolower($module);

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a complete REST API resource for {$resourceName}:

**Module:** {$moduleName}
**Resource:** {$resourceName}
**Base Path:** {$path}

Generate these files:

1. **Api/Data/{$resourceName}Interface.php** - Data interface with getters/setters
2. **Api/{$resourceName}RepositoryInterface.php** - Repository interface
3. **Api/Data/{$resourceName}SearchResultsInterface.php** - Search results interface
4. **Model/{$resourceName}.php** - Model implementation
5. **Model/{$resourceName}Repository.php** - Repository implementation
6. **etc/webapi.xml** - REST API route configuration
7. **etc/di.xml** - DI preferences

API Endpoints to create:
- GET {$path}/{$resourceName}s - List all (with search criteria)
- GET {$path}/{$resourceName}s/:id - Get by ID
- POST {$path}/{$resourceName}s - Create new
- PUT {$path}/{$resourceName}s/:id - Update existing
- DELETE {$path}/{$resourceName}s/:id - Delete

Requirements:
- Use @api annotation on interface methods
- Include proper PHPDoc with @param and @return
- Define ACL resources for each endpoint
- Support SearchCriteria for listing
- Handle exceptions (NoSuchEntityException, CouldNotSaveException, etc.)
- Follow Magento service contract patterns

webapi.xml example structure:
```xml
<route url="{$path}/{$resourceName}s" method="GET">
    <service class="..." method="getList"/>
    <resources>
        <resource ref="anonymous"/> <!-- or specific ACL -->
    </resources>
</route>
```
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a GraphQL query and resolver.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $queryName Query name (e.g., "customItems")
     * @param string $returnType Return type (e.g., "CustomItemOutput")
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-graphql-query',
        description: 'Creates a GraphQL query with resolver'
    )]
    public function createGraphqlQuery(
        string $vendor,
        string $module,
        string $queryName,
        string $returnType = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $type = $returnType ?: ucfirst($queryName) . 'Output';

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a GraphQL query for {$queryName}:

**Module:** {$moduleName}
**Query Name:** {$queryName}
**Return Type:** {$type}

Generate:

1. **etc/schema.graphqls** - GraphQL schema definition
2. **Model/Resolver/{$queryName}.php** - Query resolver class

Schema example:
```graphql
type Query {
    {$queryName}(
        id: Int @doc(description: "Item ID")
        pageSize: Int = 20 @doc(description: "Page size")
        currentPage: Int = 1 @doc(description: "Current page")
    ): {$type} @resolver(class: "{$vendor}\\\\{$module}\\\\Model\\\\Resolver\\\\{$queryName}") @doc(description: "Get {$queryName}")
}

type {$type} {
    items: [{$type}Item]
    total_count: Int
    page_info: SearchResultPageInfo
}

type {$type}Item {
    id: Int
    name: String
    # Add your fields
}
```

Resolver requirements:
- Implement \Magento\Framework\GraphQl\Query\ResolverInterface
- Implement resolve(\$field, \$context, \$info, \$value, \$args) method
- Validate arguments
- Use data providers or repositories for data fetching
- Handle authorization if needed
- Return array matching the schema structure
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a GraphQL mutation.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $mutationName Mutation name (e.g., "createCustomItem")
     * @param string $inputType Input type name
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-graphql-mutation',
        description: 'Creates a GraphQL mutation with resolver'
    )]
    public function createGraphqlMutation(
        string $vendor,
        string $module,
        string $mutationName,
        string $inputType = ''
    ): array {
        $moduleName = "{$vendor}_{$module}";
        $input = $inputType ?: ucfirst($mutationName) . 'Input';

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a GraphQL mutation for {$mutationName}:

**Module:** {$moduleName}
**Mutation Name:** {$mutationName}
**Input Type:** {$input}

Generate:

1. **etc/schema.graphqls** - Mutation schema definition
2. **Model/Resolver/{$mutationName}.php** - Mutation resolver

Schema example:
```graphql
type Mutation {
    {$mutationName}(input: {$input}!): {$mutationName}Output @resolver(class: "{$vendor}\\\\{$module}\\\\Model\\\\Resolver\\\\{$mutationName}") @doc(description: "...")
}

input {$input} {
    name: String! @doc(description: "Name field")
    # Add input fields
}

type {$mutationName}Output {
    success: Boolean!
    message: String
    item: CustomItemType
}
```

Mutation resolver requirements:
- Validate input data
- Check customer authorization if needed (use \$context->getExtensionAttributes()->getIsCustomer())
- Use repository/service for data operations
- Handle exceptions gracefully
- Return structured response with success status
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a custom API endpoint with authentication.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $serviceName Service name
     * @param string $authType Authentication type (anonymous, customer, admin)
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-authenticated-api',
        description: 'Creates a REST API endpoint with specific authentication requirements'
    )]
    public function createAuthenticatedApi(
        string $vendor,
        string $module,
        string $serviceName,
        string $authType = 'customer'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create an authenticated API endpoint:

**Module:** {$moduleName}
**Service:** {$serviceName}
**Authentication:** {$authType}

Generate:

1. **Api/{$serviceName}Interface.php** - Service interface
2. **Model/{$serviceName}.php** - Service implementation
3. **etc/webapi.xml** - API configuration with auth
4. **etc/acl.xml** - ACL resources (if admin auth)
5. **etc/di.xml** - Service preference

Authentication types:
- **anonymous**: No authentication required
  ```xml
  <resources><resource ref="anonymous"/></resources>
  ```
- **customer**: Requires customer token
  ```xml
  <resources><resource ref="self"/></resources>
  ```
- **admin**: Requires admin token with ACL
  ```xml
  <resources><resource ref="{$moduleName}::resource"/></resources>
  ```

Service interface requirements:
- Use @api annotation
- Define clear input/output types
- Document with @param and @return
- Consider using Data interfaces for complex types

For customer-authenticated endpoints:
- Access customer ID via \Magento\Authorization\Model\UserContextInterface
- Validate customer owns the requested resource
PROMPT
                ],
            ],
        ];
    }
}
