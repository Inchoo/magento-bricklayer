# Magento 2 Performance Guidelines

## Overview

Performance is critical for Magento stores. Poor performance directly impacts conversion rates, SEO rankings, and user experience.

## Caching Strategies

### Full Page Cache (FPC)

Always design pages to be FPC-compatible:

```php
// Mark blocks as cacheable
$block->setData('cache_lifetime', 3600);
$block->setData('cache_tags', ['catalog_product_' . $productId]);
```

**Hole Punching** for dynamic content:
```xml
<!-- layout XML -->
<block class="..." cacheable="false"/>
```

Use ESI or JavaScript for truly dynamic parts instead of disabling cache.

### Block Cache

```php
/**
 * @return int
 */
protected function getCacheLifetime(): int
{
    return 3600; // 1 hour
}

/**
 * @return string
 */
protected function getCacheKey(): string
{
    return 'MY_BLOCK_' . $this->getStoreId() . '_' . $this->getCustomerGroupId();
}

/**
 * @return array
 */
protected function getCacheTags(): array
{
    return [
        \Magento\Catalog\Model\Category::CACHE_TAG,
        'store_' . $this->getStoreId(),
    ];
}
```

### Data Cache

```php
/**
 * @param \Magento\Framework\App\CacheInterface $cache
 * @param \Magento\Framework\Serialize\SerializerInterface $serializer
 */
public function __construct(
    private readonly \Magento\Framework\App\CacheInterface $cache,
    private readonly \Magento\Framework\Serialize\SerializerInterface $serializer
) {}

/**
 * @param int $id
 * @return array
 */
public function getExpensiveData(int $id): array
{
    $cacheKey = 'expensive_data_' . $id;
    $cached = $this->cache->load($cacheKey);

    if ($cached) {
        return $this->serializer->unserialize($cached);
    }

    $data = $this->computeExpensiveData($id);

    $this->cache->save(
        $this->serializer->serialize($data),
        $cacheKey,
        ['my_cache_tag'],
        3600
    );

    return $data;
}
```

## Database Optimization

### Use Collections Efficiently

```php
// BAD: Loads all data
foreach ($collection as $item) {
    echo $item->getName();
}

// GOOD: Select only needed fields
$collection->addFieldToSelect(['entity_id', 'name']);
foreach ($collection as $item) {
    echo $item->getName();
}

// GOOD: Use iterator for large datasets
$collection->setPageSize(100);
foreach ($collection->getIterator() as $item) {
    // Process in batches
}
```

### Avoid N+1 Queries

```php
// BAD: N+1 query pattern
foreach ($orders as $order) {
    $items = $order->getItems(); // Triggers additional query
}

// GOOD: Eager load related data
$orders = $orderRepository->getList($searchCriteria);
// Items are loaded with the order
```

### Index Usage

```sql
-- Check your custom tables have proper indexes
CREATE INDEX idx_custom_field ON custom_table (custom_field);
```

## Collection Best Practices

### Lazy Loading

```php
// Collections are lazy - query only executes when iterating
$collection = $this->productCollectionFactory->create();
$collection->addFieldToFilter('status', 1);
// No query yet

foreach ($collection as $product) {
    // Query executes here
}
```

### Limit Fields

```php
$collection
    ->addAttributeToSelect(['name', 'price', 'thumbnail'])
    ->addFieldToFilter('status', 1)
    ->setPageSize(10);
```

## Flat Tables

Enable flat catalog for high-traffic stores:

```xml
<!-- config.xml or admin -->
<catalog>
    <frontend>
        <flat_catalog_category>1</flat_catalog_category>
        <flat_catalog_product>1</flat_catalog_product>
    </frontend>
</catalog>
```

## JavaScript Performance

### RequireJS Optimization

```javascript
// Avoid loading unnecessary modules
define(['jquery'], function($) {
    // Only use what you need
});

// Use mixins instead of full overrides
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Vendor_Module/js/shipping-mixin': true
            }
        }
    }
};
```

### Defer Non-Critical JS

```xml
<script src="..." defer="defer"/>
```

## Production Mode Best Practices

1. **Static Content Deployment**
   ```bash
   bin/magento setup:static-content:deploy -f
   ```

2. **DI Compilation**
   ```bash
   bin/magento setup:di:compile
   ```

3. **CSS/JS Bundling** (use with caution, test impact)
   ```bash
   bin/magento config:set dev/js/merge_files 1
   bin/magento config:set dev/css/merge_css_files 1
   ```

## Profiling

### Enable Profiler

```php
\Magento\Framework\Profiler::start('my_operation');
// Code to profile
\Magento\Framework\Profiler::stop('my_operation');
```

### Query Logging

```php
// In di.xml for development
<type name="Magento\Framework\DB\Logger\LoggerProxy">
    <arguments>
        <argument name="loggerAlias" xsi:type="string">file</argument>
        <argument name="logAll" xsi:type="boolean">true</argument>
    </arguments>
</type>
```

## Common Performance Anti-Patterns

| Anti-Pattern | Solution |
|--------------|----------|
| ObjectManager in loops | Inject dependency once |
| Loading full entities | Select specific fields |
| Synchronous operations | Use message queue |
| No caching | Implement proper cache |
| Large collections | Use pagination |
| Blocking I/O | Use async operations |

## Redis Configuration

```php
// env.php
'cache' => [
    'frontend' => [
        'default' => [
            'backend' => 'Magento\Framework\Cache\Backend\Redis',
            'backend_options' => [
                'server' => 'redis',
                'port' => '6379',
                'database' => '0',
            ],
        ],
        'page_cache' => [
            'backend' => 'Magento\Framework\Cache\Backend\Redis',
            'backend_options' => [
                'server' => 'redis',
                'port' => '6379',
                'database' => '1',
            ],
        ],
    ],
],
```

## Varnish Integration

Configure Varnish for FPC:

```php
// env.php
'http_cache_hosts' => [
    ['host' => 'varnish', 'port' => '80'],
],
```

## Measuring Performance

Use tools like:
- New Relic APM
- Blackfire.io
- Magento built-in profiler
- Database slow query log
- Browser DevTools (Network, Performance tabs)
