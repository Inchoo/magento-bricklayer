# GraphQL Development Skill

## Overview

Magento 2 provides a comprehensive GraphQL API for headless commerce implementations.

## Schema Definition

### Type Definition

Create `etc/schema.graphqls`:

```graphql
type Query {
    customEntity(id: Int! @doc(description: "Entity ID")): CustomEntity
        @resolver(class: "Vendor\\Module\\Model\\Resolver\\CustomEntity")
        @doc(description: "Get custom entity by ID")
        @cache(cacheIdentity: "Vendor\\Module\\Model\\Resolver\\CustomEntity\\Identity")

    customEntities(
        filter: CustomEntityFilterInput @doc(description: "Filter entities")
        pageSize: Int = 20 @doc(description: "Number of items per page")
        currentPage: Int = 1 @doc(description: "Current page")
    ): CustomEntityList
        @resolver(class: "Vendor\\Module\\Model\\Resolver\\CustomEntities")
        @doc(description: "List custom entities")
}

type CustomEntity @doc(description: "Custom entity type") {
    id: Int @doc(description: "Entity ID")
    name: String @doc(description: "Entity name")
    status: Int @doc(description: "Entity status")
    created_at: String @doc(description: "Creation date")
}

type CustomEntityList @doc(description: "List of custom entities") {
    items: [CustomEntity] @doc(description: "List of entities")
    total_count: Int @doc(description: "Total number of entities")
    page_info: SearchResultPageInfo @doc(description: "Pagination info")
}

input CustomEntityFilterInput @doc(description: "Filter input for custom entities") {
    id: FilterEqualTypeInput @doc(description: "Filter by ID")
    name: FilterMatchTypeInput @doc(description: "Filter by name")
    status: FilterEqualTypeInput @doc(description: "Filter by status")
}
```

## Resolver Implementation

### Query Resolver

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Module\Api\CustomEntityRepositoryInterface;

class CustomEntity implements ResolverInterface
{
    /**
     * @param CustomEntityRepositoryInterface $repository
     */
    public function __construct(
        private readonly CustomEntityRepositoryInterface $repository
    ) {
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        ContextInterface $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $entityId = $args['id'];

        try {
            $entity = $this->repository->get($entityId);
            return [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'status' => $entity->getStatus(),
                'created_at' => $entity->getCreatedAt(),
            ];
        } catch (\Exception $e) {
            throw new GraphQlNoSuchEntityException(
                __('Entity with ID "%1" does not exist.', $entityId)
            );
        }
    }
}
```

### List Resolver

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Vendor\Module\Api\CustomEntityRepositoryInterface;

class CustomEntities implements ResolverInterface
{
    /**
     * @param CustomEntityRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly CustomEntityRepositoryInterface $repository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        ContextInterface $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $pageSize = $args['pageSize'] ?? 20;
        $currentPage = $args['currentPage'] ?? 1;

        $this->searchCriteriaBuilder->setPageSize($pageSize);
        $this->searchCriteriaBuilder->setCurrentPage($currentPage);

        // Apply filters
        if (isset($args['filter'])) {
            $this->applyFilters($args['filter']);
        }

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $result = $this->repository->getList($searchCriteria);

        $items = [];
        foreach ($result->getItems() as $entity) {
            $items[] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'status' => $entity->getStatus(),
                'created_at' => $entity->getCreatedAt(),
            ];
        }

        return [
            'items' => $items,
            'total_count' => $result->getTotalCount(),
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => ceil($result->getTotalCount() / $pageSize),
            ],
        ];
    }

    /**
     * @param array $filters
     * @return void
     */
    private function applyFilters(array $filters): void
    {
        foreach ($filters as $field => $condition) {
            if (isset($condition['eq'])) {
                $this->searchCriteriaBuilder->addFilter($field, $condition['eq']);
            }
            if (isset($condition['match'])) {
                $this->searchCriteriaBuilder->addFilter($field, '%' . $condition['match'] . '%', 'like');
            }
        }
    }
}
```

## Mutations

### Schema

```graphql
type Mutation {
    createCustomEntity(input: CustomEntityInput!): CustomEntityOutput
        @resolver(class: "Vendor\\Module\\Model\\Resolver\\CreateCustomEntity")
        @doc(description: "Create a custom entity")

    updateCustomEntity(id: Int!, input: CustomEntityInput!): CustomEntityOutput
        @resolver(class: "Vendor\\Module\\Model\\Resolver\\UpdateCustomEntity")
        @doc(description: "Update a custom entity")

    deleteCustomEntity(id: Int!): Boolean
        @resolver(class: "Vendor\\Module\\Model\\Resolver\\DeleteCustomEntity")
        @doc(description: "Delete a custom entity")
}

input CustomEntityInput @doc(description: "Input for custom entity") {
    name: String! @doc(description: "Entity name")
    status: Int @doc(description: "Entity status")
}

type CustomEntityOutput @doc(description: "Output for custom entity mutation") {
    entity: CustomEntity @doc(description: "The created/updated entity")
}
```

### Mutation Resolver

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Vendor\Module\Api\CustomEntityRepositoryInterface;
use Vendor\Module\Api\Data\CustomEntityInterfaceFactory;

class CreateCustomEntity implements ResolverInterface
{
    /**
     * @param CustomEntityRepositoryInterface $repository
     * @param CustomEntityInterfaceFactory $entityFactory
     */
    public function __construct(
        private readonly CustomEntityRepositoryInterface $repository,
        private readonly CustomEntityInterfaceFactory $entityFactory
    ) {
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     */
    public function resolve(
        Field $field,
        ContextInterface $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $input = $args['input'];

        $entity = $this->entityFactory->create();
        $entity->setName($input['name']);

        if (isset($input['status'])) {
            $entity->setStatus($input['status']);
        }

        $savedEntity = $this->repository->save($entity);

        return [
            'entity' => [
                'id' => $savedEntity->getId(),
                'name' => $savedEntity->getName(),
                'status' => $savedEntity->getStatus(),
                'created_at' => $savedEntity->getCreatedAt(),
            ],
        ];
    }
}
```

## Cache Identity

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Resolver\CustomEntity;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class Identity implements IdentityInterface
{
    /**
     * @var string
     */
    private readonly string $cacheTag = 'CUSTOM_ENTITY';

    /**
     * @param array $resolvedData
     * @return array
     */
    public function getIdentities(array $resolvedData): array
    {
        $ids = [];
        if (isset($resolvedData['id'])) {
            $ids[] = $this->cacheTag . '_' . $resolvedData['id'];
        }
        return $ids;
    }
}
```

## Example Queries

```graphql
# Get single entity
query {
  customEntity(id: 1) {
    id
    name
    status
    created_at
  }
}

# List with filters
query {
  customEntities(
    filter: { status: { eq: 1 } }
    pageSize: 10
    currentPage: 1
  ) {
    items {
      id
      name
    }
    total_count
  }
}

# Create entity
mutation {
  createCustomEntity(input: { name: "New Entity", status: 1 }) {
    entity {
      id
      name
    }
  }
}
```
