# Adobe Commerce Guidelines

## Overview

Adobe Commerce (formerly Magento Commerce) is the enterprise edition with additional features, support, and cloud hosting options not available in Open Source.

## Edition Differences

| Feature | Open Source | Adobe Commerce |
|---------|-------------|----------------|
| B2B Features | No | Yes |
| Page Builder | Limited | Full |
| Staging & Preview | No | Yes |
| Customer Segments | No | Yes |
| Gift Registry | No | Yes |
| RMA | No | Yes |
| Reward Points | No | Yes |
| Cloud Hosting | No | Optional |
| Adobe Support | No | Yes |
| SLA | No | Yes |

## B2B Module Development

### Company Structure

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\CompanyRepositoryInterface;

class CompanyService
{
    /**
     * @param CompanyRepositoryInterface $companyRepository
     */
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository
    ) {
    }

    /**
     * @param int $customerId
     * @return CompanyInterface|null
     */
    public function getCompanyByCustomerId(int $customerId): ?CompanyInterface
    {
        // Get company for B2B customer
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('super_user_id', $customerId)
            ->create();

        $results = $this->companyRepository->getList($searchCriteria);

        return $results->getTotalCount() > 0
            ? current($results->getItems())
            : null;
    }
}
```

### Shared Catalogs

```php
<?php
use Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface;
use Magento\SharedCatalog\Api\ProductManagementInterface;

class SharedCatalogService
{
    /**
     * @param int $sharedCatalogId
     * @param array $skus
     * @return void
     */
    public function assignProductsToSharedCatalog(
        int $sharedCatalogId,
        array $skus
    ): void {
        $sharedCatalog = $this->sharedCatalogRepository->get($sharedCatalogId);

        $this->productManagement->assignProducts(
            $sharedCatalog->getId(),
            $skus
        );
    }
}
```

### Requisition Lists

```php
<?php
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;

class RequisitionService
{
    /**
     * @param int $customerId
     * @param string $name
     * @param string $description
     * @return RequisitionListInterface
     */
    public function createRequisitionList(
        int $customerId,
        string $name,
        string $description = ''
    ): RequisitionListInterface {
        $list = $this->requisitionListFactory->create();
        $list->setCustomerId($customerId)
             ->setName($name)
             ->setDescription($description);

        return $this->requisitionListRepository->save($list);
    }
}
```

## Content Staging

Content Staging allows scheduling changes:

```php
<?php
use Magento\Staging\Api\UpdateRepositoryInterface;
use Magento\Staging\Api\Data\UpdateInterface;

class StagingService
{
    /**
     * @param string $name
     * @param string $startTime
     * @param string|null $endTime
     * @return UpdateInterface
     */
    public function createScheduledUpdate(
        string $name,
        string $startTime,
        ?string $endTime = null
    ): UpdateInterface {
        $update = $this->updateFactory->create();
        $update->setName($name)
               ->setStartTime($startTime);

        if ($endTime) {
            $update->setEndTime($endTime);
        }

        return $this->updateRepository->save($update);
    }

    /**
     * @param int $updateId
     * @param string $sku
     * @param array $changes
     * @return void
     */
    public function scheduleProductChange(
        int $updateId,
        string $sku,
        array $changes
    ): void {
        $product = $this->productRepository->get($sku);

        // Apply changes for this update
        foreach ($changes as $attribute => $value) {
            $product->setData($attribute, $value);
        }

        // Link to staging update
        $product->setCreatedIn($updateId);
        $this->productRepository->save($product);
    }
}
```

## Customer Segments

```php
<?php
use Magento\CustomerSegment\Model\Segment;
use Magento\CustomerSegment\Model\ResourceModel\Segment\CollectionFactory;

class SegmentService
{
    /**
     * @param int $customerId
     * @return array
     */
    public function getCustomerSegments(int $customerId): array
    {
        $collection = $this->segmentCollectionFactory->create();
        $collection->addIsActiveFilter(true);

        $segments = [];
        foreach ($collection as $segment) {
            if ($this->segmentValidator->validateCustomer($segment, $customerId)) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }
}
```

## Adobe Commerce Cloud

### Environment Variables

```php
<?php
// Access cloud environment variables
$cloudConfig = json_decode(base64_decode($_ENV['MAGENTO_CLOUD_RELATIONSHIPS']), true);
$database = $cloudConfig['database'][0];
```

### Cloud Hooks

```yaml
# .magento.app.yaml
hooks:
    build: |
        set -e
        php ./vendor/bin/ece-tools build:generate
        php ./vendor/bin/ece-tools build:transfer
    deploy: |
        php ./vendor/bin/ece-tools deploy
    post_deploy: |
        php ./vendor/bin/ece-tools post-deploy
```

### ECE-Tools

```bash
# Deploy to cloud
./vendor/bin/ece-tools deploy

# Run database commands
./vendor/bin/ece-tools db-dump

# Validate configuration
./vendor/bin/ece-tools validate:config
```

## Live Search

Adobe Commerce includes Live Search powered by Adobe Sensei:

```php
<?php
use Magento\LiveSearch\Api\SearchInterface;

class LiveSearchService
{
    /**
     * @param string $query
     * @param int $pageSize
     * @return array
     */
    public function search(string $query, int $pageSize = 20): array
    {
        return $this->liveSearch->search([
            'query' => $query,
            'page_size' => $pageSize,
            'current_page' => 1,
        ]);
    }
}
```

## Best Practices

1. **Use Commerce features** - Leverage B2B, staging, segments
2. **Follow upgrade path** - Test Commerce-specific modules
3. **Cloud optimization** - Use environment-specific config
4. **Monitor performance** - Use New Relic (included)
5. **Security patches** - Apply Adobe security updates promptly
6. **Contact support** - Use Adobe support for enterprise issues
7. **Review license** - Understand Commerce license terms
