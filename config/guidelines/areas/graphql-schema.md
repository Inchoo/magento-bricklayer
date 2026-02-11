# GraphQL: Schema Definition

> Related: See [graphql-resolvers.md](graphql-resolvers.md) for resolver implementation, caching, and best practices.

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
