# Catalog Tools

18 tools for managing products, categories, stock, media, and product links.

## Products

### `product-get`

Get a product by SKU.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Product SKU |
| `fields` | string | `""` | Comma-separated field filter |
| `storeId` | int | 0 | Store-specific data |

### `product-list`

Search products with pagination.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fields` | string | `""` | Field filter |
| `pageSize` | int | 20 | Results per page |
| `currentPage` | int | 1 | Page number |
| `sortField` | string | `entity_id` | Sort field |
| `sortDir` | string | `DESC` | `ASC` or `DESC` |
| `count_only` | bool | false | Return count only |

### `product-create`

Create a new product.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Product SKU |
| `name` | string | *required* | Product name |
| `price` | float | *required* | Product price |
| `typeId` | string | `simple` | `simple`, `configurable`, `virtual`, `downloadable`, `bundle`, `grouped` |
| `attributeSetId` | int | 4 | Attribute set ID |

### `product-update`

Update an existing product.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Product SKU |
| `name` | string | `""` | New name |
| `price` | float | -1 | New price (-1 to skip) |
| `status` | int | 0 | 1=enabled, 2=disabled, 0=skip |

### `product-delete`

Delete a product. Blocked in production mode.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Product SKU |

## Stock

### `product-stock-get`

Get product stock data.

### `product-stock-update`

Update product stock quantity.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Product SKU |
| `qty` | float | *required* | New quantity |
| `isInStock` | bool | true | In-stock status |

## Media

### `product-media-list`

List product media gallery entries.

### `product-media-add`

Add image to product gallery.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Product SKU |
| `file` | string | `""` | Base64 image or path |
| `label` | string | `""` | Image label |
| `mediaType` | string | `image` | `image`, `small_image`, `thumbnail`, `swatch_image` |
| `position` | int | 0 | Gallery position |

## Product Links

### `product-link-list`

List related, upsell, or crosssell products.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Product SKU |
| `linkType` | string | `related` | `related`, `upsell`, `crosssell` |

### `product-link-set`

Set product links.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sku` | string | *required* | Source product SKU |
| `linkType` | string | *required* | `related`, `upsell`, `crosssell` |
| `linkedSkus` | string | *required* | Comma-separated target SKUs |

## Categories

### `category-tree`

Returns the category tree structure.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `rootId` | int | 1 | Root category ID |
| `depth` | int | 3 | Maximum depth |

### `category-get`

Get category by ID.

### `category-create`

Create a new category.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | *required* | Category name |
| `parentId` | int | 2 | Parent category ID |
| `isActive` | bool | true | Active status |
| `urlKey` | string | `""` | URL key (auto-generated if empty) |

### `category-update` / `category-delete`

Update or delete a category. Delete is blocked in production.

### `category-products`

List products in a category with pagination.

### `category-assign-products`

Assign products to a category.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `categoryId` | int | *required* | Category ID |
| `skus` | string | *required* | Comma-separated SKUs |
| `positions` | string | `""` | Comma-separated positions |
