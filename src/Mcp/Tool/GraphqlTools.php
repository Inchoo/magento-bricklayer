<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Mcp\Capability\Attribute\McpTool;

class GraphqlTools
{
    #[McpTool(
        name: 'graphql-types',
        description: 'Lists GraphQL schema types registered in Magento'
    )]
    public function getGraphqlTypes(string $typeName = '', string $kind = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $schemaGenerator = MagentoBootstrap::get(\Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface::class);
            $schema = $schemaGenerator->generate();
            $typeMap = $schema->getTypeMap();

            $types = [];
            foreach ($typeMap as $name => $type) {
                if (str_starts_with($name, '__')) {
                    continue;
                }

                $typeKind = $this->getTypeKind($type);

                if ($typeName !== '' && stripos($name, $typeName) === false) {
                    continue;
                }
                if ($kind !== '' && strtoupper($kind) !== $typeKind) {
                    continue;
                }

                $typeInfo = [
                    'name' => $name,
                    'kind' => $typeKind,
                    'description' => method_exists($type, 'getDescription') ? $type->getDescription() : null,
                ];

                if (method_exists($type, 'getFields')) {
                    $typeInfo['field_count'] = count($type->getFields());
                }

                $types[] = $typeInfo;
            }

            usort($types, fn($a, $b) => strcmp($a['name'], $b['name']));

            return [
                'total' => count($types),
                'filter_name' => $typeName ?: 'all',
                'filter_kind' => $kind ?: 'all',
                'types' => $types,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'graphql-type-info',
        description: 'Returns detailed information about a specific GraphQL type'
    )]
    public function getGraphqlTypeInfo(string $typeName): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $schemaGenerator = MagentoBootstrap::get(\Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface::class);
            $schema = $schemaGenerator->generate();

            $type = $schema->getType($typeName);
            if ($type === null) {
                return ['error' => true, 'message' => "Type not found: $typeName"];
            }

            $typeInfo = [
                'name' => $typeName,
                'kind' => $this->getTypeKind($type),
                'description' => method_exists($type, 'getDescription') ? $type->getDescription() : null,
            ];

            if (method_exists($type, 'getFields')) {
                $fields = [];
                foreach ($type->getFields() as $fieldName => $field) {
                    $fieldInfo = [
                        'name' => $fieldName,
                        'type' => (string) $field->getType(),
                        'description' => $field->getDescription(),
                    ];

                    $args = $field->getArgs();
                    if (!empty($args)) {
                        $fieldInfo['arguments'] = [];
                        foreach ($args as $argName => $arg) {
                            $fieldInfo['arguments'][] = [
                                'name' => $argName,
                                'type' => (string) $arg->getType(),
                                'default_value' => $arg->defaultValueExists() ? $arg->getDefaultValue() : null,
                            ];
                        }
                    }

                    $fields[] = $fieldInfo;
                }
                $typeInfo['fields'] = $fields;
            }

            if (method_exists($type, 'getValues')) {
                $values = [];
                foreach ($type->getValues() as $value) {
                    $values[] = [
                        'name' => $value->name,
                        'value' => $value->value,
                        'description' => $value->description,
                    ];
                }
                $typeInfo['values'] = $values;
            }

            if (method_exists($type, 'getInterfaces')) {
                $interfaces = [];
                foreach ($type->getInterfaces() as $interface) {
                    $interfaces[] = $interface->name();
                }
                $typeInfo['implements'] = $interfaces;
            }

            return $typeInfo;
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'graphql-queries',
        description: 'Lists all GraphQL queries available in the schema'
    )]
    public function getGraphqlQueries(): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $schemaGenerator = MagentoBootstrap::get(\Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface::class);
            $schema = $schemaGenerator->generate();

            $queryType = $schema->getQueryType();
            if ($queryType === null) {
                return ['error' => true, 'message' => 'No query type found'];
            }

            $queries = [];
            foreach ($queryType->getFields() as $fieldName => $field) {
                $args = [];
                foreach ($field->getArgs() as $argName => $arg) {
                    $args[] = [
                        'name' => $argName,
                        'type' => (string) $arg->getType(),
                        'default_value' => $arg->defaultValueExists() ? $arg->getDefaultValue() : null,
                    ];
                }

                $queries[] = [
                    'name' => $fieldName,
                    'return_type' => (string) $field->getType(),
                    'description' => $field->getDescription(),
                    'arguments' => $args,
                ];
            }

            usort($queries, fn($a, $b) => strcmp($a['name'], $b['name']));

            return [
                'total' => count($queries),
                'queries' => $queries,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'graphql-mutations',
        description: 'Lists all GraphQL mutations available in the schema'
    )]
    public function getGraphqlMutations(): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $schemaGenerator = MagentoBootstrap::get(\Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface::class);
            $schema = $schemaGenerator->generate();

            $mutationType = $schema->getMutationType();
            if ($mutationType === null) {
                return ['error' => true, 'message' => 'No mutation type found'];
            }

            $mutations = [];
            foreach ($mutationType->getFields() as $fieldName => $field) {
                $args = [];
                foreach ($field->getArgs() as $argName => $arg) {
                    $args[] = [
                        'name' => $argName,
                        'type' => (string) $arg->getType(),
                    ];
                }

                $mutations[] = [
                    'name' => $fieldName,
                    'return_type' => (string) $field->getType(),
                    'description' => $field->getDescription(),
                    'arguments' => $args,
                ];
            }

            usort($mutations, fn($a, $b) => strcmp($a['name'], $b['name']));

            return [
                'total' => count($mutations),
                'mutations' => $mutations,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    #[McpTool(
        name: 'graphql-resolvers',
        description: 'Lists GraphQL resolvers registered for types'
    )]
    public function getGraphqlResolvers(string $typeName = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            return [
                'message' => 'Use graphql-type-info to inspect specific type resolvers',
                'note' => 'Resolver configuration is defined in etc/schema.graphqls files',
                'hint' => 'Search for "@resolver" directive in graphqls files',
                'filter_type' => $typeName ?: 'all',
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function getTypeKind(object $type): string
    {
        $className = get_class($type);

        return match (true) {
            str_contains($className, 'ObjectType') => 'OBJECT',
            str_contains($className, 'InputObjectType') => 'INPUT_OBJECT',
            str_contains($className, 'EnumType') => 'ENUM',
            str_contains($className, 'InterfaceType') => 'INTERFACE',
            str_contains($className, 'ScalarType') => 'SCALAR',
            str_contains($className, 'UnionType') => 'UNION',
            str_contains($className, 'ListOf') => 'LIST',
            str_contains($className, 'NonNull') => 'NON_NULL',
            default => 'UNKNOWN',
        };
    }
}
