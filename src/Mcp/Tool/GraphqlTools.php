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

class GraphqlTools
{
    use RequiresMagento;

    #[McpTool(
        name: 'graphql-inspect',
        description: 'Inspect GraphQL schema. Target: types, queries, mutations. Use name for type details. Note: resolvers target returns guidance only (Magento does not expose resolvers programmatically).',
        meta: ['hidden' => true]
    )]
    public function inspectGraphql(string $target, string $name = '', string $kind = ''): array
    {
        $allowed = ['types', 'queries', 'mutations', 'resolvers'];

        if (!in_array($target, $allowed, true)) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Invalid target "%s". Must be one of: %s.',
                    $target,
                    implode(', ', $allowed)
                ),
            ];
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        return match ($target) {
            'types'     => $name !== '' ? $this->getGraphqlTypeInfo($name) : $this->getGraphqlTypes($name, $kind),
            'queries'   => $this->getGraphqlQueries(),
            'mutations' => $this->getGraphqlMutations(),
            'resolvers' => $this->getGraphqlResolvers($name),
        };
    }

    private function getGraphqlTypes(string $typeName = '', string $kind = ''): array
    {
        try {
            $schema = $this->generateSchema();
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
                    'description' => method_exists($type, 'description') ? $type->description() : ($type->description ?? null),
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

    private function getGraphqlTypeInfo(string $typeName): array
    {
        try {
            $schema = $this->generateSchema();

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
                        'description' => $field->description ?? null,
                    ];

                    $args = $field->args ?? [];
                    if (!empty($args)) {
                        $fieldInfo['arguments'] = [];
                        foreach ($args as $arg) {
                            $fieldInfo['arguments'][] = [
                                'name' => $arg->name,
                                'type' => (string) $arg->getType(),
                                'default_value' => $arg->defaultValueExists() ? $arg->defaultValue : null,
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

    private function getGraphqlQueries(): array
    {
        try {
            $schema = $this->generateSchema();

            $queryType = $schema->getQueryType();
            if ($queryType === null) {
                return ['error' => true, 'message' => 'No query type found'];
            }

            $queries = [];
            foreach ($queryType->getFields() as $fieldName => $field) {
                $args = [];
                foreach ($field->args ?? [] as $arg) {
                    $args[] = [
                        'name' => $arg->name,
                        'type' => (string) $arg->getType(),
                        'default_value' => $arg->defaultValueExists() ? $arg->defaultValue : null,
                    ];
                }

                $queries[] = [
                    'name' => $fieldName,
                    'return_type' => (string) $field->getType(),
                    'description' => $field->description ?? null,
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

    private function getGraphqlMutations(): array
    {
        try {
            $schema = $this->generateSchema();

            $mutationType = $schema->getMutationType();
            if ($mutationType === null) {
                return ['error' => true, 'message' => 'No mutation type found'];
            }

            $mutations = [];
            foreach ($mutationType->getFields() as $fieldName => $field) {
                $args = [];
                foreach ($field->args ?? [] as $arg) {
                    $args[] = [
                        'name' => $arg->name,
                        'type' => (string) $arg->getType(),
                    ];
                }

                $mutations[] = [
                    'name' => $fieldName,
                    'return_type' => (string) $field->getType(),
                    'description' => $field->description ?? null,
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

    private function getGraphqlResolvers(string $typeName = ''): array
    {
        try {
            return [
                'message' => 'Use graphql-inspect with target=types and a name to inspect specific type resolvers',
                'note' => 'Resolver configuration is defined in etc/schema.graphqls files',
                'hint' => 'Search for "@resolver" directive in graphqls files',
                'filter_type' => $typeName ?: 'all',
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generates the GraphQL schema with graphql area DI configuration loaded.
     *
     * @return \GraphQL\Type\Schema
     */
    private function generateSchema(): \GraphQL\Type\Schema
    {
        $objectManager = MagentoBootstrap::getObjectManager();

        $configLoader = $objectManager->get(
            \Magento\Framework\ObjectManager\ConfigLoaderInterface::class
        );
        $objectManager->configure($configLoader->load('graphql'));

        $schemaGenerator = $objectManager->get(
            \Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface::class
        );

        return $schemaGenerator->generate();
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
