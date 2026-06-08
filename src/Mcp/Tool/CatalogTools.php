<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\FiltersFields;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\PaginatesResults;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\SecureArea;
use Mcp\Capability\Attribute\McpTool;

/**
 * Catalog Tools
 *
 * Provides MCP tools for managing Magento catalog (products and categories).
 */
class CatalogTools
{
    use ChecksConfig;
    use FiltersFields;
    use PaginatesResults;
    use RequiresMagento;
    use RespondsWithErrors;
    use SecureArea;

    /**
     * Retrieves product data by SKU or ID.
     *
     * @param string $sku Product SKU
     * @param int $storeId Store ID for store-specific data
     * @return array<string, mixed> Product data
     */
    #[McpTool(
        name: 'product-get',
        description: 'Get product by SKU. Use fields to limit response.'
    )]
    public function getProduct(string $sku = '', int $storeId = 0, string $fields = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($sku === '') {
            return $this->errorResponse('SKU is required');
        }

        try {
            $productRepository = MagentoBootstrap::get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
            $product = $productRepository->get($sku, false, $storeId);

            $result = $this->formatProductData($product, true, $fields);

            $eavConfig = MagentoBootstrap::get(\Magento\Eav\Model\Config::class);
            foreach ($product->getCustomAttributes() ?? [] as $attr) {
                $eavAttribute = $eavConfig->getAttribute('catalog_product', $attr->getAttributeCode());
                if ($eavAttribute && $eavAttribute->getIsUserDefined()) {
                    $result['_hint'] = 'Custom attributes present. Use eav-attributes entity_type=catalog_product for metadata';
                    break;
                }
            }

            return $result;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Product not found: $sku");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Lists products with filtering and pagination.
     *
     * @param int $pageSize Number of results per page
     * @param int $currentPage Current page number
     * @param string $sortField Field to sort by
     * @param string $sortDir Sort direction (ASC or DESC)
     * @return array<string, mixed> List of products
     */
    #[McpTool(
        name: 'product-list',
        description: 'Search products. Use fields to limit response. Set count_only=true to check size before fetching.',
        meta: ['hidden' => true]
    )]
    public function listProducts(
        int $pageSize = 20,
        int $currentPage = 1,
        string $sortField = 'entity_id',
        string $sortDir = 'DESC',
        bool $count_only = false,
        string $fields = ''
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $productRepository = MagentoBootstrap::get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $sortOrderBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SortOrderBuilder::class);

            $sortOrder = $sortOrderBuilder->setField($sortField)->setDirection($sortDir)->create();
            $searchCriteria = $searchCriteriaBuilder
                ->addSortOrder($sortOrder)
                ->setPageSize($pageSize)
                ->setCurrentPage($currentPage)
                ->create();

            $result = $productRepository->getList($searchCriteria);

            if ($count_only) {
                return $this->countOnlyResponse($result->getTotalCount());
            }

            $products = [];
            foreach ($result->getItems() as $product) {
                $products[] = $this->formatProductData($product, false, $fields);
            }

            return $this->paginatedResponse($result->getTotalCount(), $pageSize, $currentPage, $products);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Creates a new product.
     *
     * @param string $sku Product SKU
     * @param string $name Product name
     * @param float $price Product price
     * @param int $attributeSetId Attribute set ID
     * @param string $typeId Product type (simple, configurable, virtual, downloadable, bundle, grouped)
     * @return array<string, mixed> Created product data or error
     */
    #[McpTool(
        name: 'product-create',
        description: 'Create a product.',
        meta: ['hidden' => true]
    )]
    public function createProduct(
        string $sku,
        string $name,
        float $price,
        int $attributeSetId = 4,
        string $typeId = 'simple'
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('product-create')) {
            return $error;
        }

        try {
            $productRepository = MagentoBootstrap::get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
            $productFactory = MagentoBootstrap::get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);

            $product = $productFactory->create();
            $product->setSku($sku)
                ->setName($name)
                ->setPrice($price)
                ->setAttributeSetId($attributeSetId)
                ->setTypeId($typeId)
                ->setStatus(1)
                ->setVisibility(4);

            $savedProduct = $productRepository->save($product);

            return ['success' => true, 'product' => $this->formatProductData($savedProduct, true)];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Updates an existing product.
     *
     * @param string $sku Product SKU
     * @param string $name New product name (optional)
     * @param float $price New product price (optional, use -1 to skip)
     * @param int $status Product status (1=enabled, 2=disabled, 0=skip)
     * @return array<string, mixed> Updated product data or error
     */
    #[McpTool(
        name: 'product-update',
        description: 'Update a product.',
        meta: ['hidden' => true]
    )]
    public function updateProduct(
        string $sku,
        string $name = '',
        float $price = -1,
        int $status = 0
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('product-update')) {
            return $error;
        }

        try {
            $productRepository = MagentoBootstrap::get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
            $product = $productRepository->get($sku);

            if ($name !== '') {
                $product->setName($name);
            }
            if ($price >= 0) {
                $product->setPrice($price);
            }
            if ($status > 0) {
                $product->setStatus($status);
            }

            $savedProduct = $productRepository->save($product);

            return ['success' => true, 'product' => $this->formatProductData($savedProduct, true)];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Product not found: $sku");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Gets stock information for a product.
     *
     * @param string $sku Product SKU
     * @return array<string, mixed> Stock data
     */
    #[McpTool(
        name: 'product-stock-get',
        description: 'Get product stock data.',
        meta: ['hidden' => true]
    )]
    public function getProductStock(string $sku): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $stockRegistry = MagentoBootstrap::get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
            $stockItem = $stockRegistry->getStockItemBySku($sku);

            return [
                'sku' => $sku,
                'qty' => $stockItem->getQty(),
                'is_in_stock' => $stockItem->getIsInStock(),
                'min_qty' => $stockItem->getMinQty(),
                'min_sale_qty' => $stockItem->getMinSaleQty(),
                'max_sale_qty' => $stockItem->getMaxSaleQty(),
                'manage_stock' => $stockItem->getManageStock(),
                'backorders' => $stockItem->getBackorders(),
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Updates stock quantity for a product.
     *
     * @param string $sku Product SKU
     * @param float $qty New quantity
     * @param bool $isInStock In stock status
     * @return array<string, mixed> Update result
     */
    #[McpTool(
        name: 'product-stock-update',
        description: 'Update product stock.',
        meta: ['hidden' => true]
    )]
    public function updateProductStock(string $sku, float $qty, bool $isInStock = true): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('product-stock-update')) {
            return $error;
        }

        try {
            $stockRegistry = MagentoBootstrap::get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
            $stockItem = $stockRegistry->getStockItemBySku($sku);

            $stockItem->setQty($qty);
            $stockItem->setIsInStock($isInStock);
            $stockRegistry->updateStockItemBySku($sku, $stockItem);

            return ['success' => true, 'sku' => $sku, 'qty' => $qty, 'is_in_stock' => $isInStock];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Returns category tree structure.
     *
     * @param int $rootId Root category ID (use 1 for default root)
     * @param int $depth Maximum depth to retrieve
     * @return array<string, mixed> Category tree
     */
    #[McpTool(
        name: 'category-tree',
        description: 'Returns category tree structure',
        meta: ['hidden' => true]
    )]
    public function getCategoryTree(int $rootId = 1, int $depth = 3): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $categoryManagement = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryManagementInterface::class);
            $tree = $categoryManagement->getTree($rootId, $depth);

            return $this->formatCategoryTree($tree);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Retrieves category data by ID.
     *
     * @param int $categoryId Category ID
     * @param int $storeId Store ID
     * @return array<string, mixed> Category data
     */
    #[McpTool(
        name: 'category-get',
        description: 'Get category by ID.',
        meta: ['hidden' => true]
    )]
    public function getCategory(int $categoryId, int $storeId = 0): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $categoryRepository = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
            $category = $categoryRepository->get($categoryId, $storeId);

            return [
                'id' => (int) $category->getId(),
                'name' => $category->getName(),
                'parent_id' => (int) $category->getParentId(),
                'is_active' => (bool) $category->getIsActive(),
                'level' => (int) $category->getLevel(),
                'position' => (int) $category->getPosition(),
                'path' => $category->getPath(),
                'url_key' => $category->getUrlKey(),
                'children_count' => (int) $category->getChildrenCount(),
                'product_count' => (int) $category->getProductCount(),
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Category not found: $categoryId");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Deletes a product by SKU.
     *
     * @param string $sku Product SKU
     * @return array<string, mixed> Deletion result
     */
    #[McpTool(
        name: 'product-delete',
        description: 'Delete a product.',
        meta: ['hidden' => true]
    )]
    public function deleteProduct(string $sku): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('product-delete')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('product-delete')) {
            return $error;
        }

        try {
            return $this->withSecureArea(function () use ($sku) {
                $productRepository = MagentoBootstrap::get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
                $productRepository->deleteById($sku);

                return ['success' => true, 'message' => "Product $sku deleted"];
            });
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Product not found: $sku");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Lists media gallery entries for a product.
     *
     * @param string $sku Product SKU
     * @return array<string, mixed> List of media entries
     */
    #[McpTool(
        name: 'product-media-list',
        description: 'List product media entries.',
        meta: ['hidden' => true]
    )]
    public function listProductMedia(string $sku): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $galleryManagement = MagentoBootstrap::get(\Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface::class);
            $entries = $galleryManagement->getList($sku);

            $media = [];
            foreach ($entries as $entry) {
                $media[] = [
                    'id' => (int) $entry->getId(),
                    'media_type' => $entry->getMediaType(),
                    'label' => $entry->getLabel(),
                    'position' => (int) $entry->getPosition(),
                    'disabled' => (bool) $entry->isDisabled(),
                    'types' => $entry->getTypes(),
                    'file' => $entry->getFile(),
                ];
            }

            return ['sku' => $sku, 'media' => $media];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Adds media to product gallery.
     *
     * @param string $sku Product SKU
     * @param string $mediaType Media type (image, small_image, thumbnail, swatch_image)
     * @param string $file Base64 encoded image or path to existing file
     * @param string $label Image label
     * @param int $position Position in gallery
     * @return array<string, mixed> Created media entry or error
     */
    #[McpTool(
        name: 'product-media-add',
        description: 'Adds media to product gallery',
        meta: ['hidden' => true]
    )]
    public function addProductMedia(
        string $sku,
        string $mediaType = 'image',
        string $file = '',
        string $label = '',
        int $position = 0
    ): array {
        if ($file === '') {
            return $this->errorResponse('File is required');
        }

        if (!str_starts_with($file, 'data:image')) {
            return $this->errorResponse(
                'Only data-URI encoded images are supported (e.g. data:image/jpeg;base64,…). '
                . 'File path input is not implemented.'
            );
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('product-media-add')) {
            return $error;
        }

        try {
            $galleryManagement = MagentoBootstrap::get(\Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface::class);
            $entryFactory = MagentoBootstrap::get(\Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory::class);
            $contentFactory = MagentoBootstrap::get(\Magento\Framework\Api\Data\ImageContentInterfaceFactory::class);

            $entry = $entryFactory->create();
            $entry->setMediaType($mediaType)
                ->setLabel($label)
                ->setPosition($position)
                ->setDisabled(false)
                ->setTypes([$mediaType]);

            $mimeInfo = $this->parseMimeFromDataUri($file);
            $content = $contentFactory->create();
            $imageData = explode(',', $file);
            $content->setBase64EncodedData($imageData[1] ?? $file);
            $content->setType($mimeInfo['mime']);
            $content->setName($mimeInfo['name']);
            $entry->setContent($content);

            $id = $galleryManagement->create($sku, $entry);

            return ['success' => true, 'sku' => $sku, 'media_id' => $id];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Parses MIME type and derives a filename extension from a data-URI prefix.
     *
     * Handles common image types (jpeg, png, gif, webp, svg+xml) and falls back to
     * image/jpeg + .jpg for unrecognised subtypes.
     *
     * @param string $dataUri A string of the form "data:image/<subtype>;base64,…"
     * @return array{mime: string, name: string}
     */
    private function parseMimeFromDataUri(string $dataUri): array
    {
        $mime = 'image/jpeg';
        $ext = '.jpg';

        if (preg_match('#^data:(image/[a-z+\-]+);#', $dataUri, $m)) {
            $mime = $m[1];
            $extMap = [
                'image/jpeg'   => '.jpg',
                'image/png'    => '.png',
                'image/gif'    => '.gif',
                'image/webp'   => '.webp',
                'image/svg+xml' => '.svg',
            ];
            $ext = $extMap[$mime] ?? '.jpg';
        }

        return ['mime' => $mime, 'name' => 'product_image_' . time() . $ext];
    }

    /**
     * Lists related, upsell, or crosssell products.
     *
     * @param string $sku Product SKU
     * @param string $linkType Link type (related, upsell, crosssell)
     * @return array<string, mixed> List of linked products
     */
    #[McpTool(
        name: 'product-link-list',
        description: 'Lists related, upsell, or crosssell products',
        meta: ['hidden' => true]
    )]
    public function listProductLinks(string $sku, string $linkType = 'related'): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        $validTypes = ['related', 'upsell', 'crosssell'];
        if (!in_array($linkType, $validTypes, true)) {
            return $this->errorResponse("Invalid link type. Use: " . implode(', ', $validTypes));
        }

        try {
            $linkManagement = MagentoBootstrap::get(\Magento\Catalog\Api\ProductLinkManagementInterface::class);
            $links = $linkManagement->getLinkedItemsByType($sku, $linkType);

            $linkedProducts = [];
            foreach ($links as $link) {
                $linkedProducts[] = [
                    'linked_sku' => $link->getLinkedProductSku(),
                    'link_type' => $link->getLinkType(),
                    'position' => (int) $link->getPosition(),
                ];
            }

            return ['sku' => $sku, 'link_type' => $linkType, 'links' => $linkedProducts];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Sets product links (related, upsell, crosssell).
     *
     * @param string $sku Product SKU
     * @param string $linkType Link type (related, upsell, crosssell)
     * @param string $linkedSkus Comma-separated list of SKUs to link
     * @return array<string, mixed> Result
     */
    #[McpTool(
        name: 'product-link-set',
        description: 'Sets product links (related, upsell, crosssell)',
        meta: ['hidden' => true]
    )]
    public function setProductLinks(string $sku, string $linkType, string $linkedSkus): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('product-link-set')) {
            return $error;
        }

        $validTypes = ['related', 'upsell', 'crosssell'];
        if (!in_array($linkType, $validTypes, true)) {
            return $this->errorResponse("Invalid link type. Use: " . implode(', ', $validTypes));
        }

        try {
            $linkManagement = MagentoBootstrap::get(\Magento\Catalog\Api\ProductLinkManagementInterface::class);
            $linkFactory = MagentoBootstrap::get(\Magento\Catalog\Api\Data\ProductLinkInterfaceFactory::class);

            $skuList = array_map('trim', explode(',', $linkedSkus));
            $links = [];

            foreach ($skuList as $position => $linkedSku) {
                $link = $linkFactory->create();
                $link->setSku($sku)
                    ->setLinkedProductSku($linkedSku)
                    ->setLinkType($linkType)
                    ->setPosition($position);
                $links[] = $link;
            }

            $linkManagement->setProductLinks($sku, $links);

            return ['success' => true, 'sku' => $sku, 'link_type' => $linkType, 'linked_count' => count($links)];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Creates a new category.
     *
     * @param string $name Category name
     * @param int $parentId Parent category ID
     * @param bool $isActive Whether category is active
     * @param string $urlKey URL key (optional, auto-generated if empty)
     * @return array<string, mixed> Created category data or error
     */
    #[McpTool(
        name: 'category-create',
        description: 'Create a category.',
        meta: ['hidden' => true]
    )]
    public function createCategory(
        string $name,
        int $parentId = 2,
        bool $isActive = true,
        string $urlKey = ''
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('category-create')) {
            return $error;
        }

        try {
            $categoryRepository = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
            $categoryFactory = MagentoBootstrap::get(\Magento\Catalog\Api\Data\CategoryInterfaceFactory::class);

            $category = $categoryFactory->create();
            $category->setName($name)
                ->setParentId($parentId)
                ->setIsActive($isActive);

            if ($urlKey !== '') {
                $category->setCustomAttribute('url_key', $urlKey);
            }

            $savedCategory = $categoryRepository->save($category);

            return [
                'success' => true,
                'category' => [
                    'id' => (int) $savedCategory->getId(),
                    'name' => $savedCategory->getName(),
                    'parent_id' => (int) $savedCategory->getParentId(),
                    'is_active' => (bool) $savedCategory->getIsActive(),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Updates an existing category.
     *
     * @param int $categoryId Category ID
     * @param string $name New category name (optional)
     * @param bool $isActive Active status (use false with care)
     * @param string $urlKey New URL key (optional)
     * @return array<string, mixed> Updated category data or error
     */
    #[McpTool(
        name: 'category-update',
        description: 'Update a category.',
        meta: ['hidden' => true]
    )]
    public function updateCategory(
        int $categoryId,
        string $name = '',
        bool $isActive = true,
        string $urlKey = ''
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('category-update')) {
            return $error;
        }

        try {
            $categoryRepository = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
            $category = $categoryRepository->get($categoryId);

            if ($name !== '') {
                $category->setName($name);
            }
            $category->setIsActive($isActive);
            if ($urlKey !== '') {
                $category->setCustomAttribute('url_key', $urlKey);
            }

            $savedCategory = $categoryRepository->save($category);

            return [
                'success' => true,
                'category' => [
                    'id' => (int) $savedCategory->getId(),
                    'name' => $savedCategory->getName(),
                    'is_active' => (bool) $savedCategory->getIsActive(),
                ],
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Category not found: $categoryId");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Deletes a category.
     *
     * @param int $categoryId Category ID
     * @return array<string, mixed> Deletion result
     */
    #[McpTool(
        name: 'category-delete',
        description: 'Delete a category.',
        meta: ['hidden' => true]
    )]
    public function deleteCategory(int $categoryId): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('category-delete')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('category-delete')) {
            return $error;
        }

        try {
            return $this->withSecureArea(function () use ($categoryId) {
                $categoryRepository = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
                $categoryRepository->deleteByIdentifier($categoryId);

                return ['success' => true, 'message' => "Category $categoryId deleted"];
            });
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Category not found: $categoryId");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Lists products in a category.
     *
     * @param int $categoryId Category ID
     * @param int $pageSize Number of results per page
     * @param int $currentPage Current page number
     * @return array<string, mixed> List of products in category
     */
    #[McpTool(
        name: 'category-products',
        description: 'Lists products in a category',
        meta: ['hidden' => true]
    )]
    public function listCategoryProducts(int $categoryId, int $pageSize = 20, int $currentPage = 1): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $categoryLinkManagement = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryLinkManagementInterface::class);
            $categoryRepository = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);

            // Verify category exists
            $category = $categoryRepository->get($categoryId);

            $assignedProducts = $categoryLinkManagement->getAssignedProducts($categoryId);

            // Paginate
            $total = count($assignedProducts);
            $offset = ($currentPage - 1) * $pageSize;
            $assignedProducts = array_slice($assignedProducts, $offset, $pageSize);

            $products = [];
            foreach ($assignedProducts as $link) {
                $products[] = [
                    'sku' => $link->getSku(),
                    'position' => (int) $link->getPosition(),
                    'category_id' => $categoryId,
                ];
            }

            $paginated = $this->paginatedResponse($total, $pageSize, $currentPage, $products);

            return [
                'category_id' => $categoryId,
                'category_name' => $category->getName(),
                'total_count' => $paginated['total_count'],
                'page_size' => $paginated['page_size'],
                'current_page' => $paginated['current_page'],
                'has_more' => $paginated['has_more'],
                'products' => $paginated['items'],
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Category not found: $categoryId");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Assigns products to a category.
     *
     * @param int $categoryId Category ID
     * @param string $skus Comma-separated list of product SKUs
     * @param string $positions Comma-separated list of positions (optional)
     * @return array<string, mixed> Assignment result
     */
    #[McpTool(
        name: 'category-assign-products',
        description: 'Assigns products to a category',
        meta: ['hidden' => true]
    )]
    public function assignProductsToCategory(int $categoryId, string $skus, string $positions = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('category-assign-products')) {
            return $error;
        }

        try {
            $categoryLinkManagement = MagentoBootstrap::get(\Magento\Catalog\Api\CategoryLinkManagementInterface::class);

            $skuList = array_map('trim', explode(',', $skus));

            foreach ($skuList as $sku) {
                $categoryLinkManagement->assignProductToCategories($sku, [$categoryId]);
            }

            return ['success' => true, 'category_id' => $categoryId, 'assigned_count' => count($skuList)];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Format product data for output
     *
     * @param object $product
     * @param bool $includeExtensions
     * @return array<string, mixed>
     */
    private function formatProductData(object $product, bool $includeExtensions, string $fields = ''): array
    {
        $data = [
            'id' => (int) $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => (float) $product->getPrice(),
            'status' => (int) $product->getStatus(),
            'visibility' => (int) $product->getVisibility(),
            'type_id' => $product->getTypeId(),
            'attribute_set_id' => (int) $product->getAttributeSetId(),
            'created_at' => $product->getCreatedAt(),
            'updated_at' => $product->getUpdatedAt(),
        ];

        if ($includeExtensions) {
            $customAttributes = [];
            foreach ($product->getCustomAttributes() ?? [] as $attr) {
                $customAttributes[] = [
                    'attribute_code' => $attr->getAttributeCode(),
                    'value' => $attr->getValue(),
                ];
            }
            $data['custom_attributes'] = $customAttributes;
        }

        return $this->filterFields($data, $fields);
    }

    /**
     * Format category tree for output
     *
     * @param object $node
     * @return array<string, mixed>
     */
    private function formatCategoryTree(object $node): array
    {
        $data = [
            'id' => (int) $node->getId(),
            'name' => $node->getName(),
            'is_active' => (bool) $node->getIsActive(),
            'level' => (int) $node->getLevel(),
            'product_count' => (int) $node->getProductCount(),
        ];

        $children = [];
        foreach ($node->getChildrenData() ?? [] as $child) {
            $children[] = $this->formatCategoryTree($child);
        }

        if (!empty($children)) {
            $data['children'] = $children;
        }

        return $data;
    }
}
