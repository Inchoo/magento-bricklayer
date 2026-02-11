# GraphQL: Resolvers & Best Practices

> Related: See [graphql-schema.md](graphql-schema.md) for schema definition syntax, types, inputs, and filters.

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
    /**
     * @param ItemRepositoryInterface $itemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
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

    /**
     * @param array $filters
     * @return void
     */
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

    /**
     * @param array $sort
     * @return void
     */
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

    /**
     * @param string $type
     * @return string
     */
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
    /**
     * @param ItemRepositoryInterface $itemRepository
     * @param ItemInterfaceFactory $itemFactory
     */
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ItemInterfaceFactory $itemFactory
    ) {
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        ContextInterface $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
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
    /**
     * @param array $resolvedData
     * @return array
     */
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
