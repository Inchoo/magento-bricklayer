# Magento 2 GraphQL Development Guidelines

## Overview

GraphQL in Magento 2 provides a flexible query language for frontend applications. It's optimized for headless/PWA storefronts.

## Area Code

```php
\Magento\Framework\App\Area::AREA_GRAPHQL // 'graphql'
```

## Directory Structure

```
app/code/Vendor/Module/
├── etc/
│   └── schema.graphqls
├── Model/
│   └── Resolver/
│       ├── CustomQuery.php
│       ├── CustomMutation.php
│       └── DataProvider/
│           └── CustomDataProvider.php
```

## Schema Definition

```graphql
# etc/schema.graphqls

type Query {
    customItems(
        filter: CustomItemFilterInput @doc(description: "Filter options")
        pageSize: Int = 20 @doc(description: "Number of items per page")
        currentPage: Int = 1 @doc(description: "Current page number")
        sort: CustomItemSortInput @doc(description: "Sort options")
    ): CustomItemsOutput @resolver(class: "Vendor\\Module\\Model\\Resolver\\CustomItems") @doc(description: "Get custom items")

    customItem(
        id: Int! @doc(description: "Item ID")
    ): CustomItem @resolver(class: "Vendor\\Module\\Model\\Resolver\\CustomItem") @doc(description: "Get single custom item")
}

type Mutation {
    createCustomItem(
        input: CreateCustomItemInput!
    ): CreateCustomItemOutput @resolver(class: "Vendor\\Module\\Model\\Resolver\\CreateCustomItem") @doc(description: "Create a custom item")

    updateCustomItem(
        id: Int!
        input: UpdateCustomItemInput!
    ): UpdateCustomItemOutput @resolver(class: "Vendor\\Module\\Model\\Resolver\\UpdateCustomItem") @doc(description: "Update a custom item")

    deleteCustomItem(
        id: Int!
    ): DeleteCustomItemOutput @resolver(class: "Vendor\\Module\\Model\\Resolver\\DeleteCustomItem") @doc(description: "Delete a custom item")
}

type CustomItem {
    id: Int @doc(description: "Item ID")
    name: String @doc(description: "Item name")
    description: String @doc(description: "Item description")
    status: Int @doc(description: "Item status")
    created_at: String @doc(description: "Creation date")
}

type CustomItemsOutput {
    items: [CustomItem] @doc(description: "Array of custom items")
    total_count: Int @doc(description: "Total number of items")
    page_info: SearchResultPageInfo @doc(description: "Pagination information")
}

input CustomItemFilterInput {
    name: FilterStringInput @doc(description: "Filter by name")
    status: FilterIntInput @doc(description: "Filter by status")
}

input CustomItemSortInput {
    name: SortEnum @doc(description: "Sort by name")
    created_at: SortEnum @doc(description: "Sort by creation date")
}

input CreateCustomItemInput {
    name: String! @doc(description: "Item name")
    description: String @doc(description: "Item description")
    status: Int = 1 @doc(description: "Item status")
}

input UpdateCustomItemInput {
    name: String @doc(description: "Item name")
    description: String @doc(description: "Item description")
    status: Int @doc(description: "Item status")
}

type CreateCustomItemOutput {
    item: CustomItem @doc(description: "Created item")
}

type UpdateCustomItemOutput {
    item: CustomItem @doc(description: "Updated item")
}

type DeleteCustomItemOutput {
    success: Boolean @doc(description: "Whether deletion was successful")
    message: String @doc(description: "Result message")
}

# Filter input types (reusable)
input FilterStringInput {
    eq: String
    in: [String]
    match: String
}

input FilterIntInput {
    eq: Int
    in: [Int]
    from: Int
    to: Int
}
```

## Query Resolver

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Vendor\Module\Api\ItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;

class CustomItems implements ResolverInterface
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $pageSize = $args['pageSize'] ?? 20;
        $currentPage = $args['currentPage'] ?? 1;

        // Apply filters
        if (!empty($args['filter'])) {
            $this->applyFilters($args['filter']);
        }

        // Apply sorting
        if (!empty($args['sort'])) {
            $this->applySorting($args['sort']);
        }

        // Set pagination
        $this->searchCriteriaBuilder
            ->setPageSize($pageSize)
            ->setCurrentPage($currentPage);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->itemRepository->getList($searchCriteria);

        $items = [];
        foreach ($searchResults->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'description' => $item->getDescription(),
                'status' => $item->getStatus(),
                'created_at' => $item->getCreatedAt(),
            ];
        }

        return [
            'items' => $items,
            'total_count' => $searchResults->getTotalCount(),
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => ceil($searchResults->getTotalCount() / $pageSize),
            ],
        ];
    }

    private function applyFilters(array $filters): void
    {
        foreach ($filters as $field => $condition) {
            foreach ($condition as $conditionType => $value) {
                $this->searchCriteriaBuilder->addFilter(
                    $field,
                    $value,
                    $this->mapConditionType($conditionType)
                );
            }
        }
    }

    private function applySorting(array $sort): void
    {
        foreach ($sort as $field => $direction) {
            $sortOrder = $this->sortOrderBuilder
                ->setField($field)
                ->setDirection($direction)
                ->create();
            $this->searchCriteriaBuilder->addSortOrder($sortOrder);
        }
    }

    private function mapConditionType(string $type): string
    {
        return match ($type) {
            'eq' => 'eq',
            'in' => 'in',
            'match' => 'like',
            'from' => 'gteq',
            'to' => 'lteq',
            default => 'eq',
        };
    }
}
```

## Mutation Resolver

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Vendor\Module\Api\ItemRepositoryInterface;
use Vendor\Module\Api\Data\ItemInterfaceFactory;

class CreateCustomItem implements ResolverInterface
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemInterfaceFactory $itemFactory
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        // Check authorization
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('Customer must be logged in'));
        }

        $input = $args['input'];

        // Validate input
        if (empty($input['name'])) {
            throw new GraphQlInputException(__('Name is required'));
        }

        try {
            $item = $this->itemFactory->create();
            $item->setName($input['name']);
            $item->setDescription($input['description'] ?? '');
            $item->setStatus($input['status'] ?? 1);

            $savedItem = $this->itemRepository->save($item);

            return [
                'item' => [
                    'id' => $savedItem->getId(),
                    'name' => $savedItem->getName(),
                    'description' => $savedItem->getDescription(),
                    'status' => $savedItem->getStatus(),
                    'created_at' => $savedItem->getCreatedAt(),
                ],
            ];
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Could not save item: %1', $e->getMessage()));
        }
    }
}
```

## Customer Context

```php
// Check if customer is logged in
$customerId = $context->getUserId();
$isCustomer = $context->getExtensionAttributes()->getIsCustomer();

if (!$isCustomer) {
    throw new GraphQlAuthorizationException(__('Customer not logged in'));
}
```

## Extending Core Types

```graphql
# Add field to existing type
type ProductInterface {
    custom_attribute: String @resolver(class: "Vendor\\Module\\Model\\Resolver\\Product\\CustomAttribute") @doc(description: "Custom product attribute")
}

# Add to cart item
type CartItemInterface {
    custom_option: String @resolver(class: "Vendor\\Module\\Model\\Resolver\\Cart\\CustomOption")
}
```

## Caching

```graphql
type Query {
    customItems: CustomItemsOutput
        @resolver(class: "...")
        @cache(cacheIdentity: "Vendor\\Module\\Model\\Resolver\\Identity\\CustomItems")
}
```

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Resolver\Identity;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class CustomItems implements IdentityInterface
{
    public function getIdentities(array $resolvedData): array
    {
        $ids = [];
        foreach ($resolvedData['items'] ?? [] as $item) {
            $ids[] = 'custom_item_' . $item['id'];
        }
        return $ids;
    }
}
```

## Best Practices

1. **Use typed schemas** - Define all types clearly
2. **Document with @doc** - Add descriptions to all fields
3. **Implement caching** - Use cache identities
4. **Handle errors gracefully** - Use GraphQL exception classes
5. **Validate input** - Check required fields in resolvers
6. **Use data providers** - Separate data fetching logic
7. **Follow naming conventions** - CamelCase for types, snake_case for fields
8. **Batch requests** - Use DataLoader pattern for N+1 prevention
9. **Test thoroughly** - Use GraphQlAbstract test class
