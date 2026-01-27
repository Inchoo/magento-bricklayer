<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Plugin Creation Prompts
 *
 * Provides MCP prompts for creating Magento plugins (interceptors).
 */
class PluginPrompts
{
    /**
     * Creates a before plugin.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $targetClass Target class to intercept
     * @param string $targetMethod Method to intercept
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-before-plugin',
        description: 'Creates a Magento 2 before plugin to modify method arguments'
    )]
    public function createBeforePlugin(
        string $vendor,
        string $module,
        string $targetClass,
        string $targetMethod
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a before plugin:

**Module:** {$moduleName}
**Target Class:** {$targetClass}
**Target Method:** {$targetMethod}

Generate:

1. **Plugin/{PluginName}Plugin.php** - Plugin class with before method
2. **etc/di.xml** - Plugin configuration

Before plugin requirements:
- Method name: before{$targetMethod} (with PascalCase)
- First parameter: \$subject (the intercepted object)
- Remaining parameters: match original method signature
- Return: array of modified arguments, or null to keep original

Example structure:
```php
public function before{$targetMethod}(
    \\{$targetClass} \$subject,
    // original method parameters...
): ?array {
    // Modify arguments
    return [\$modifiedArg1, \$modifiedArg2];
    // Or return null to use original arguments
}
```

di.xml configuration:
```xml
<type name="{$targetClass}">
    <plugin name="{$moduleName}_plugin_name"
            type="{$vendor}\\{$module}\\Plugin\\{PluginName}Plugin"
            sortOrder="10"/>
</type>
```
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates an after plugin.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $targetClass Target class to intercept
     * @param string $targetMethod Method to intercept
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-after-plugin',
        description: 'Creates a Magento 2 after plugin to modify return values'
    )]
    public function createAfterPlugin(
        string $vendor,
        string $module,
        string $targetClass,
        string $targetMethod
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create an after plugin:

**Module:** {$moduleName}
**Target Class:** {$targetClass}
**Target Method:** {$targetMethod}

Generate:

1. **Plugin/{PluginName}Plugin.php** - Plugin class with after method
2. **etc/di.xml** - Plugin configuration

After plugin requirements:
- Method name: after{$targetMethod} (with PascalCase)
- First parameter: \$subject (the intercepted object)
- Second parameter: \$result (original return value)
- Optional: original method parameters after \$result
- Return: modified result (same type as original)

Example structure:
```php
public function after{$targetMethod}(
    \\{$targetClass} \$subject,
    \$result,
    // optional: original method parameters...
) {
    // Modify result
    return \$modifiedResult;
}
```

Common patterns:
- Adding data to result objects
- Filtering/transforming return arrays
- Wrapping return values
- Logging return values
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates an around plugin.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $targetClass Target class to intercept
     * @param string $targetMethod Method to intercept
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-around-plugin',
        description: 'Creates a Magento 2 around plugin for full method control'
    )]
    public function createAroundPlugin(
        string $vendor,
        string $module,
        string $targetClass,
        string $targetMethod
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create an around plugin:

**Module:** {$moduleName}
**Target Class:** {$targetClass}
**Target Method:** {$targetMethod}

Generate:

1. **Plugin/{PluginName}Plugin.php** - Plugin class with around method
2. **etc/di.xml** - Plugin configuration

Around plugin requirements:
- Method name: around{$targetMethod} (with PascalCase)
- First parameter: \$subject (the intercepted object)
- Second parameter: \$proceed (callable to invoke original)
- Remaining parameters: match original method signature
- MUST call \$proceed() unless intentionally skipping
- Return: same type as original method

Example structure:
```php
public function around{$targetMethod}(
    \\{$targetClass} \$subject,
    callable \$proceed,
    // original method parameters...
) {
    // Before logic

    // Call original method
    \$result = \$proceed(\$arg1, \$arg2);

    // After logic

    return \$result;
}
```

Use cases for around plugins:
- Caching method results
- Adding try/catch error handling
- Performance timing/logging
- Conditional execution
- Transaction wrapping

WARNING: Around plugins have performance overhead.
Prefer before/after plugins when possible.
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a plugin with all three types.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $targetClass Target class to intercept
     * @param string $purpose Brief description of plugin purpose
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-comprehensive-plugin',
        description: 'Creates a plugin class with examples of all plugin types'
    )]
    public function createComprehensivePlugin(
        string $vendor,
        string $module,
        string $targetClass,
        string $purpose = 'Custom functionality'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a comprehensive plugin for {$targetClass}:

**Module:** {$moduleName}
**Target Class:** {$targetClass}
**Purpose:** {$purpose}

Generate:

1. **Plugin/{ClassName}Plugin.php** - Plugin with multiple interceptions
2. **etc/di.xml** - Plugin configuration

The plugin should demonstrate:
- A before plugin method (modify inputs)
- An after plugin method (modify outputs)
- An around plugin method (full control)

Include:
- Proper type hints for all parameters
- Constructor injection for any dependencies
- PHPDoc blocks explaining each method
- Error handling in around plugin
- Logging example using Psr\Log\LoggerInterface

Best practices to follow:
- Keep each method focused on one concern
- Don't create infinite loops by calling subject methods
- Consider sort order for multiple plugins
- Document any assumptions about original method behavior
PROMPT
                ],
            ],
        ];
    }
}
