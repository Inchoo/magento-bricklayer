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
use Mcp\Capability\Attribute\McpTool;

class OrderTools
{
    use ChecksConfig;
    use FiltersFields;
    use PaginatesResults;
    use RequiresMagento;
    use RespondsWithErrors;

    #[McpTool(
        name: 'order-get',
        description: 'Get order by increment ID. Use fields to limit response.'
    )]
    public function getOrder(string $incrementId, string $fields = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($incrementId === '') {
            return $this->errorResponse('Order increment ID is required');
        }

        try {
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

            $searchCriteria = $searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->create();

            $orders = $orderRepository->getList($searchCriteria)->getItems();

            if (empty($orders)) {
                return $this->errorResponse("Order not found: $incrementId");
            }

            $order = reset($orders);
            return $this->formatOrderData($order, true, $fields);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'order-list',
        description: 'Search orders. Filter by status. Use fields to limit response. Set count_only=true to check size before fetching.',
        meta: ['hidden' => true]
    )]
    public function listOrders(
        int $pageSize = 20,
        int $currentPage = 1,
        string $status = '',
        string $sortField = 'created_at',
        string $sortDir = 'DESC',
        bool $count_only = false,
        string $fields = ''
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $sortOrderBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SortOrderBuilder::class);

            if ($status !== '') {
                $searchCriteriaBuilder->addFilter('status', $status);
            }

            $sortOrder = $sortOrderBuilder->setField($sortField)->setDirection($sortDir)->create();
            $searchCriteria = $searchCriteriaBuilder
                ->addSortOrder($sortOrder)
                ->setPageSize($pageSize)
                ->setCurrentPage($currentPage)
                ->create();

            $result = $orderRepository->getList($searchCriteria);

            if ($count_only) {
                return $this->countOnlyResponse($result->getTotalCount());
            }

            $orders = [];
            foreach ($result->getItems() as $order) {
                $orders[] = $this->formatOrderData($order, false, $fields);
            }

            return $this->paginatedResponse($result->getTotalCount(), $pageSize, $currentPage, $orders);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create a new order from a guest quote, or for an existing customer when customerId is set.
     *
     * Each entry in $items is one line item. Simple, virtual and downloadable products need only
     * {sku, qty}. Option-bearing types carry extra, human-friendly keys that are resolved to the
     * internal IDs Magento expects:
     *   - configurable: "super_attribute" => {attribute code or label: option value or label}
     *   - grouped:      "grouped_quantities" => {child sku: qty}  (the line "qty" is ignored)
     *   - bundle:       "bundle_selections" => [{selection_sku, qty}]
     *   - downloadable: "links" => [link id or title]  (only when links are sold separately; defaults to all)
     *   - any type:     "custom_options" => {option title or id: value}  (select choices by title/id, a
     *                   list for checkbox/multiple; text/date values pass through)
     *
     * @param array<int, array<string, mixed>> $items Line items to order; see the description above.
     * @param int $customerId Existing customer to attach the order to; 0 places a guest order.
     *     When set, customerEmail is ignored in favour of the customer's own email.
     */
    #[McpTool(
        name: 'order-create',
        description: 'Creates an order from a guest quote, or for an existing customer via '
            . 'customerId. items = list of line objects. Simple/virtual/downloadable: {sku, qty}. '
            . 'Configurable: add super_attribute {attribute code or label: option value or label}. '
            . 'Grouped: add grouped_quantities {child sku: qty} (the line qty is ignored). '
            . 'Bundle: add bundle_selections [{selection_sku, qty}]. '
            . 'Downloadable with separately-priced links: optional links [link id or title] (defaults to all). '
            . 'Any product may add custom_options {option title or id: value} (choice title/id for selects). '
            . 'One address is used for both billing and shipping.',
        meta: ['hidden' => true, 'prerequisite' => 'Products must be salable; shipping and payment methods active']
    )]
    public function createOrder(
        string $customerEmail,
        array $items,
        string $firstname,
        string $lastname,
        string $street,
        string $city,
        string $postcode,
        string $countryId,
        string $telephone,
        string $region = '',
        int $regionId = 0,
        string $shippingMethod = 'flatrate_flatrate',
        string $paymentMethod = 'checkmo',
        int $customerId = 0,
        int $storeId = 0
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('order-create')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('order-create')) {
            return $error;
        }

        if ($customerEmail === '') {
            return $this->errorResponse('customerEmail is required');
        }
        if ($items === []) {
            return $this->errorResponse('At least one item (sku, qty) is required');
        }

        try {
            $storeManager = MagentoBootstrap::get(\Magento\Store\Model\StoreManagerInterface::class);
            $store = $storeId > 0
                ? $storeManager->getStore($storeId)
                : $storeManager->getDefaultStoreView();
            if ($store === null) {
                $store = $storeManager->getStore();
            }
            $resolvedStoreId = (int) $store->getId();

            $quoteFactory = MagentoBootstrap::get(\Magento\Quote\Model\QuoteFactory::class);
            $cartRepository = MagentoBootstrap::get(\Magento\Quote\Api\CartRepositoryInterface::class);
            $cartManagement = MagentoBootstrap::get(\Magento\Quote\Api\CartManagementInterface::class);
            $productRepository = MagentoBootstrap::get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);

            $quote = $quoteFactory->create();
            $quote->setStore($store);

            if ($customerId > 0) {
                $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
                $quote->assignCustomer($customerRepository->getById($customerId));
            } else {
                $quote->setCustomerEmail($customerEmail);
                $quote->setCustomerIsGuest(true);
                $quote->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    return $this->errorResponse('Each item must be an object with a sku (and qty)');
                }

                $sku = (string) ($item['sku'] ?? '');
                if ($sku === '') {
                    return $this->errorResponse('Each item requires a non-empty sku');
                }
                $qty = (float) ($item['qty'] ?? 0);

                $product = $productRepository->get($sku, false, $resolvedStoreId);

                $buyRequest = $this->resolveBuyRequest($product, $qty, $item);
                if (is_string($buyRequest)) {
                    return $this->errorResponse("Could not add '$sku': $buyRequest");
                }

                $added = $quote->addProduct(
                    $product,
                    MagentoBootstrap::create(\Magento\Framework\DataObject::class, ['data' => $buyRequest])
                );
                if (is_string($added)) {
                    return $this->errorResponse("Could not add '$sku': $added");
                }
            }

            $addressData = [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'street' => $street,
                'city' => $city,
                'postcode' => $postcode,
                'country_id' => $countryId,
                'telephone' => $telephone,
                'email' => $customerEmail,
            ];
            if ($regionId > 0) {
                $addressData['region_id'] = $regionId;
            } elseif ($region !== '') {
                $addressData['region'] = $region;
            }

            $quote->getBillingAddress()->addData($addressData);
            // Virtual/downloadable-only quotes have no shippable items, so skip shipping entirely.
            if (!$quote->isVirtual()) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->addData($addressData);
                $shippingAddress->setCollectShippingRates(true)
                    ->collectShippingRates()
                    ->setShippingMethod($shippingMethod);
            }

            $quote->getPayment()->setMethod($paymentMethod);

            $quote->collectTotals();
            $cartRepository->save($quote);

            $orderId = (int) $cartManagement->placeOrder($quote->getId());
            $order = $orderRepository->get($orderId);

            return [
                'success' => true,
                'order_id' => $orderId,
                'increment_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'grand_total' => (float) $order->getGrandTotal(),
                'store_id' => $resolvedStoreId,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Build the addProduct buy-request payload for one line item, resolving type-specific option
     * data (configurable / grouped / bundle) from human-friendly SKUs and labels into the internal
     * IDs Magento expects. Simple, virtual and downloadable products just carry their qty.
     *
     * @param array<string, mixed> $item Raw line item supplied by the MCP client.
     * @return array<string, mixed>|string The buy-request data, or an error message.
     */
    private function resolveBuyRequest(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        float $qty,
        array $item
    ): array|string {
        switch ((string) $product->getTypeId()) {
            case 'configurable':
                $request = $this->buildConfigurableRequest($product, $qty, $item);
                break;
            case 'grouped':
                $request = $this->buildGroupedRequest($product, $item);
                break;
            case 'bundle':
                $request = $this->buildBundleRequest($product, $qty, $item);
                break;
            case 'downloadable':
                $request = $this->buildDownloadableRequest($product, $qty, $item);
                break;
            default:
                if ($qty <= 0) {
                    return 'a positive qty is required';
                }
                $request = ['qty' => $qty];
        }

        if (is_string($request)) {
            return $request;
        }

        // Custom options apply to any product type; merge them onto the resolved buy request.
        $customOptions = $this->resolveCustomOptions($product, $item);
        if (is_string($customOptions)) {
            return $customOptions;
        }
        if ($customOptions !== []) {
            $request['options'] = $customOptions;
        }

        return $request;
    }

    /**
     * Resolve a configurable product's chosen options into a super_attribute map.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|string
     */
    private function buildConfigurableRequest(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        float $qty,
        array $item
    ): array|string {
        if ($qty <= 0) {
            return 'a positive qty is required';
        }

        $requested = $item['super_attribute'] ?? null;
        if (!is_array($requested) || $requested === []) {
            return 'configurable product requires "super_attribute" '
                . 'mapping each attribute (code or label) to an option (value or label)';
        }

        $attributes = $product->getTypeInstance()->getConfigurableAttributesAsArray($product);

        $superAttribute = [];
        foreach ($requested as $attributeKey => $optionKey) {
            $matched = null;
            foreach ($attributes as $attribute) {
                if ($this->matchesConfigurableAttribute((string) $attributeKey, $attribute)) {
                    $matched = $attribute;
                    break;
                }
            }
            if ($matched === null) {
                return "unknown configurable attribute '$attributeKey'";
            }

            $valueIndex = $this->resolveConfigurableOption((string) $optionKey, $matched['values'] ?? []);
            if ($valueIndex === null) {
                return "no option '$optionKey' for attribute '$attributeKey'";
            }
            $superAttribute[(int) $matched['attribute_id']] = $valueIndex;
        }

        foreach ($attributes as $attribute) {
            if (!isset($superAttribute[(int) $attribute['attribute_id']])) {
                $label = (string) ($attribute['attribute_code'] ?? $attribute['label'] ?? $attribute['attribute_id']);
                return "missing selection for configurable attribute '$label'";
            }
        }

        return ['qty' => $qty, 'super_attribute' => $superAttribute];
    }

    /**
     * Match one configurable attribute entry by its code, label or id (case-insensitive).
     *
     * @param array<string, mixed> $attribute A getConfigurableAttributesAsArray() entry.
     */
    private function matchesConfigurableAttribute(string $key, array $attribute): bool
    {
        $candidates = [
            $attribute['attribute_code'] ?? '',
            $attribute['frontend_label'] ?? '',
            $attribute['store_label'] ?? '',
            $attribute['label'] ?? '',
            $attribute['attribute_id'] ?? '',
        ];
        foreach ($candidates as $candidate) {
            if ((string) $candidate !== '' && strcasecmp((string) $candidate, $key) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve an option key (value index or any of its labels) to its numeric value index.
     *
     * @param array<int, array<string, mixed>> $values The attribute's option values.
     */
    private function resolveConfigurableOption(string $key, array $values): ?int
    {
        foreach ($values as $value) {
            $candidates = [
                $value['label'] ?? '',
                $value['store_label'] ?? '',
                $value['default_label'] ?? '',
                $value['value_index'] ?? '',
            ];
            foreach ($candidates as $candidate) {
                if ((string) $candidate !== '' && strcasecmp((string) $candidate, $key) === 0) {
                    return (int) $value['value_index'];
                }
            }
        }
        return null;
    }

    /**
     * Resolve a grouped product's child quantities into a super_group map (child id => qty).
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|string
     */
    private function buildGroupedRequest(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        array $item
    ): array|string {
        $quantities = $item['grouped_quantities'] ?? null;
        if (!is_array($quantities) || $quantities === []) {
            return 'grouped product requires "grouped_quantities" mapping each child sku to a qty';
        }

        $skuToId = [];
        foreach ($product->getTypeInstance()->getAssociatedProducts($product) as $child) {
            $skuToId[strtolower((string) $child->getSku())] = (int) $child->getId();
        }

        $superGroup = [];
        foreach ($quantities as $childSku => $childQty) {
            $lookup = strtolower((string) $childSku);
            if (!isset($skuToId[$lookup])) {
                return "child sku '$childSku' is not part of this grouped product";
            }
            $value = (float) $childQty;
            if ($value > 0) {
                $superGroup[$skuToId[$lookup]] = $value;
            }
        }

        if ($superGroup === []) {
            return 'grouped_quantities must include at least one child with qty > 0';
        }

        return ['super_group' => $superGroup];
    }

    /**
     * Resolve a bundle product's selections (by child sku) into bundle_option / bundle_option_qty
     * maps, and verify every required option has been chosen.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|string
     */
    private function buildBundleRequest(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        float $qty,
        array $item
    ): array|string {
        if ($qty <= 0) {
            return 'a positive qty is required';
        }

        $selections = $item['bundle_selections'] ?? null;
        if (!is_array($selections) || $selections === []) {
            return 'bundle product requires "bundle_selections" as a list of {selection_sku, qty}';
        }

        $typeInstance = $product->getTypeInstance();
        $optionsCollection = $typeInstance->getOptionsCollection($product);
        $selectionsCollection = $typeInstance->getSelectionsCollection($optionsCollection->getAllIds(), $product);

        $skuToSelection = [];
        foreach ($selectionsCollection as $selection) {
            $skuToSelection[strtolower((string) $selection->getSku())] = [
                'option_id' => (int) $selection->getOptionId(),
                'selection_id' => (int) $selection->getSelectionId(),
                'product' => $selection,
            ];
        }

        $bundleOption = [];
        $bundleOptionQty = [];
        $chosen = [];
        foreach ($selections as $selection) {
            if (!is_array($selection)) {
                return 'each bundle_selections entry must be an object with selection_sku and qty';
            }
            $selectionSku = (string) ($selection['selection_sku'] ?? '');
            $lookup = strtolower($selectionSku);
            if ($lookup === '' || !isset($skuToSelection[$lookup])) {
                return "selection sku '$selectionSku' is not part of this bundle";
            }

            $optionId = $skuToSelection[$lookup]['option_id'];
            $selectionId = $skuToSelection[$lookup]['selection_id'];
            $selectionQty = (float) ($selection['qty'] ?? 1);
            if ($selectionQty <= 0) {
                $selectionQty = 1;
            }

            if (isset($bundleOption[$optionId])) {
                $bundleOption[$optionId] = array_merge((array) $bundleOption[$optionId], [$selectionId]);
            } else {
                $bundleOption[$optionId] = $selectionId;
            }
            $bundleOptionQty[$optionId] = $selectionQty;
            $chosen[] = $skuToSelection[$lookup]['product'];
        }

        foreach ($optionsCollection as $option) {
            if ((bool) $option->getRequired() && !isset($bundleOption[(int) $option->getOptionId()])) {
                $title = (string) ($option->getTitle() ?: $option->getOptionId());
                return "missing selection for required bundle option '$title'";
            }
        }

        $request = [
            'qty' => $qty,
            'bundle_option' => $bundleOption,
            'bundle_option_qty' => $bundleOptionQty,
        ];

        // Selections that are themselves downloadable with separately-priced links need those link
        // ids on the parent buy request; the bundle passes it down and each selection keeps only its
        // own links, so the union across chosen selections satisfies them all (defaults to all links).
        $links = [];
        foreach ($chosen as $selectionProduct) {
            $isDownloadable = (string) $selectionProduct->getTypeId() === 'downloadable';
            $separateLinks = (int) $selectionProduct->getData('links_purchased_separately') === 1;
            if (!$isDownloadable || !$separateLinks) {
                continue;
            }
            foreach ($selectionProduct->getTypeInstance()->getLinks($selectionProduct) as $link) {
                $links[] = (int) $link->getId();
            }
        }
        if ($links !== []) {
            $request['links'] = array_values(array_unique($links));
        }

        return $request;
    }

    /**
     * Build the buy-request for a downloadable product. Links only need to be chosen when they are
     * sold separately; the requested "links" may be link ids or titles, and default to every link.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|string
     */
    private function buildDownloadableRequest(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        float $qty,
        array $item
    ): array|string {
        if ($qty <= 0) {
            return 'a positive qty is required';
        }

        $request = ['qty' => $qty];
        if ((int) $product->getData('links_purchased_separately') !== 1) {
            return $request;
        }

        $available = [];
        foreach ($product->getTypeInstance()->getLinks($product) as $link) {
            $available[(int) $link->getId()] = (string) $link->getTitle();
        }
        if ($available === []) {
            return $request;
        }

        $requested = $item['links'] ?? null;
        if ($requested === null || $requested === []) {
            $request['links'] = array_keys($available);
            return $request;
        }
        if (!is_array($requested)) {
            return '"links" must be a list of link ids or titles';
        }

        $linkIds = [];
        foreach ($requested as $linkKey) {
            $resolved = null;
            foreach ($available as $id => $title) {
                if ((string) $id === (string) $linkKey || strcasecmp($title, (string) $linkKey) === 0) {
                    $resolved = $id;
                    break;
                }
            }
            if ($resolved === null) {
                return "unknown downloadable link '$linkKey'";
            }
            $linkIds[] = $resolved;
        }
        $request['links'] = $linkIds;

        return $request;
    }

    /**
     * Resolve product custom options ("custom_options") into the {option id: value} map the buy
     * request expects. Options are matched by title or id; select-type choices are matched by
     * choice title or option_type_id (a list for checkbox/multiple); text/date/file values pass
     * through unchanged. Required options left unset are reported by addProduct.
     *
     * @param array<string, mixed> $item
     * @return array<int, mixed>|string
     */
    private function resolveCustomOptions(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        array $item
    ): array|string {
        $requested = $item['custom_options'] ?? null;
        if ($requested === null || $requested === []) {
            return [];
        }
        if (!is_array($requested)) {
            return '"custom_options" must be a map of option (title or id) to value';
        }

        $productOptions = $product->getOptions() ?? [];
        $selectTypes = ['drop_down', 'radio', 'checkbox', 'multiple'];

        $resolved = [];
        foreach ($requested as $optionKey => $value) {
            $match = null;
            foreach ($productOptions as $option) {
                if (
                    (string) $option->getOptionId() === (string) $optionKey
                    || strcasecmp((string) $option->getTitle(), (string) $optionKey) === 0
                ) {
                    $match = $option;
                    break;
                }
            }
            if ($match === null) {
                return "unknown custom option '$optionKey'";
            }

            $optionId = (int) $match->getOptionId();
            if (in_array((string) $match->getType(), $selectTypes, true)) {
                $ids = $this->resolveCustomOptionValues($match, $value);
                if (is_string($ids)) {
                    return $ids;
                }
                $multiple = in_array((string) $match->getType(), ['checkbox', 'multiple'], true);
                $resolved[$optionId] = $multiple ? $ids : $ids[0];
            } else {
                $resolved[$optionId] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Resolve the chosen value(s) of a select-type custom option to their option_type_id(s). Each
     * value may be a choice title or an option_type_id; arrays are accepted for checkbox/multiple.
     *
     * @param mixed $value
     * @return array<int, int>|string
     */
    private function resolveCustomOptionValues(
        \Magento\Catalog\Api\Data\ProductCustomOptionInterface $option,
        mixed $value
    ): array|string {
        $available = $option->getValues() ?? [];
        $keys = is_array($value) ? $value : [$value];

        $ids = [];
        foreach ($keys as $key) {
            $found = null;
            foreach ($available as $optionValue) {
                if (
                    (string) $optionValue->getOptionTypeId() === (string) $key
                    || strcasecmp((string) $optionValue->getTitle(), (string) $key) === 0
                ) {
                    $found = (int) $optionValue->getOptionTypeId();
                    break;
                }
            }
            if ($found === null) {
                return "no choice '$key' for custom option '" . (string) $option->getTitle() . "'";
            }
            $ids[] = $found;
        }

        return $ids;
    }

    #[McpTool(
        name: 'order-add-comment',
        description: 'Adds a comment to order history',
        meta: ['hidden' => true]
    )]
    public function addOrderComment(
        int $orderId,
        string $comment,
        string $status = '',
        bool $notifyCustomer = false
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('order-add-comment')) {
            return $error;
        }

        try {
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $historyFactory = MagentoBootstrap::get(\Magento\Sales\Model\Order\Status\HistoryFactory::class);

            $order = $orderRepository->get($orderId);

            $history = $historyFactory->create()
                ->setComment($comment)
                ->setIsCustomerNotified($notifyCustomer)
                ->setIsVisibleOnFront($notifyCustomer);

            if ($status !== '') {
                $history->setStatus($status);
                $order->setStatus($status);
            }

            $order->addStatusHistory($history);
            $orderRepository->save($order);

            return [
                'success' => true,
                'order_id' => $orderId,
                'increment_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'order-cancel',
        description: 'Cancels an order',
        meta: ['hidden' => true, 'prerequisite' => 'Order must be cancellable']
    )]
    public function cancelOrder(int $orderId): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('order-cancel')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('order-cancel')) {
            return $error;
        }

        try {
            $orderManagement = MagentoBootstrap::get(\Magento\Sales\Api\OrderManagementInterface::class);
            $result = $orderManagement->cancel($orderId);

            return [
                'success' => $result,
                'order_id' => $orderId,
                'message' => $result ? 'Order cancelled' : 'Failed to cancel order',
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'order-hold',
        description: 'Places an order on hold',
        meta: ['hidden' => true, 'prerequisite' => 'Order must be holdable']
    )]
    public function holdOrder(int $orderId): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('order-hold')) {
            return $error;
        }

        try {
            $orderManagement = MagentoBootstrap::get(\Magento\Sales\Api\OrderManagementInterface::class);
            $result = $orderManagement->hold($orderId);

            return [
                'success' => $result,
                'order_id' => $orderId,
                'message' => 'Order placed on hold',
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'order-unhold',
        description: 'Releases an order from hold',
        meta: ['hidden' => true, 'prerequisite' => 'Order must be on hold']
    )]
    public function unholdOrder(int $orderId): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('order-unhold')) {
            return $error;
        }

        try {
            $orderManagement = MagentoBootstrap::get(\Magento\Sales\Api\OrderManagementInterface::class);
            $result = $orderManagement->unHold($orderId);

            return [
                'success' => $result,
                'order_id' => $orderId,
                'message' => 'Order released from hold',
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'invoice-create',
        description: 'Creates an invoice for an order',
        meta: ['hidden' => true, 'prerequisite' => 'Order must be uninvoiced']
    )]
    public function createInvoice(int $orderId, bool $capture = true, bool $notify = true): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('invoice-create')) {
            return $error;
        }

        try {
            $invoiceOrder = MagentoBootstrap::get(\Magento\Sales\Api\InvoiceOrderInterface::class);
            $invoiceId = $invoiceOrder->execute($orderId, $capture, [], $notify);

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'order_id' => $orderId,
                'captured' => $capture,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'shipment-create',
        description: 'Creates a shipment for an order',
        meta: ['hidden' => true, 'prerequisite' => 'Order must be invoiced (usually)']
    )]
    public function createShipment(int $orderId, bool $notify = true): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('shipment-create')) {
            return $error;
        }

        try {
            $shipOrder = MagentoBootstrap::get(\Magento\Sales\Api\ShipOrderInterface::class);
            $shipmentId = $shipOrder->execute($orderId, [], $notify);

            return [
                'success' => true,
                'shipment_id' => $shipmentId,
                'order_id' => $orderId,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'creditmemo-create',
        description: 'Creates a credit memo (refund) for an order',
        meta: ['hidden' => true, 'prerequisite' => 'Order must have invoice']
    )]
    public function createCreditMemo(int $orderId, bool $notify = true): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('creditmemo-create')) {
            return $error;
        }
        if ($error = $this->requireNonProduction('creditmemo-create')) {
            return $error;
        }

        try {
            $refundOrder = MagentoBootstrap::get(\Magento\Sales\Api\RefundOrderInterface::class);
            $creditmemoId = $refundOrder->execute($orderId, [], $notify);

            return [
                'success' => true,
                'creditmemo_id' => $creditmemoId,
                'order_id' => $orderId,
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'order-items',
        description: 'Returns line items for an order',
        meta: ['hidden' => true]
    )]
    public function getOrderItems(int $orderId): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $order = $orderRepository->get($orderId);

            $items = [];
            foreach ($order->getItems() as $item) {
                $items[] = [
                    'item_id' => (int) $item->getItemId(),
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'product_type' => $item->getProductType(),
                    'qty_ordered' => (float) $item->getQtyOrdered(),
                    'qty_invoiced' => (float) $item->getQtyInvoiced(),
                    'qty_shipped' => (float) $item->getQtyShipped(),
                    'qty_refunded' => (float) $item->getQtyRefunded(),
                    'qty_canceled' => (float) $item->getQtyCanceled(),
                    'price' => (float) $item->getPrice(),
                    'base_price' => (float) $item->getBasePrice(),
                    'discount_amount' => (float) $item->getDiscountAmount(),
                    'tax_amount' => (float) $item->getTaxAmount(),
                    'row_total' => (float) $item->getRowTotal(),
                    'row_total_incl_tax' => (float) $item->getRowTotalInclTax(),
                ];
            }

            return [
                'order_id' => $orderId,
                'increment_id' => $order->getIncrementId(),
                'item_count' => count($items),
                'items' => $items,
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Order not found: $orderId");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'order-comments',
        description: 'Lists order status history and comments',
        meta: ['hidden' => true]
    )]
    public function getOrderComments(int $orderId): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $order = $orderRepository->get($orderId);

            $comments = [];
            foreach ($order->getStatusHistories() as $history) {
                $comments[] = [
                    'entity_id' => (int) $history->getEntityId(),
                    'status' => $history->getStatus(),
                    'comment' => $history->getComment(),
                    'is_customer_notified' => (bool) $history->getIsCustomerNotified(),
                    'is_visible_on_front' => (bool) $history->getIsVisibleOnFront(),
                    'created_at' => $history->getCreatedAt(),
                ];
            }

            return [
                'order_id' => $orderId,
                'increment_id' => $order->getIncrementId(),
                'current_status' => $order->getStatus(),
                'comment_count' => count($comments),
                'comments' => $comments,
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Order not found: $orderId");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'invoice-list',
        description: 'Lists invoices with pagination',
        meta: ['hidden' => true]
    )]
    public function listInvoices(int $pageSize = 20, int $currentPage = 1, int $orderId = 0): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $invoiceRepository = MagentoBootstrap::get(\Magento\Sales\Api\InvoiceRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $sortOrderBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SortOrderBuilder::class);

            if ($orderId > 0) {
                $searchCriteriaBuilder->addFilter('order_id', $orderId);
            }

            $sortOrder = $sortOrderBuilder->setField('created_at')->setDirection('DESC')->create();
            $searchCriteria = $searchCriteriaBuilder
                ->addSortOrder($sortOrder)
                ->setPageSize($pageSize)
                ->setCurrentPage($currentPage)
                ->create();

            $result = $invoiceRepository->getList($searchCriteria);

            $invoices = [];
            foreach ($result->getItems() as $invoice) {
                $invoices[] = [
                    'entity_id' => (int) $invoice->getEntityId(),
                    'increment_id' => $invoice->getIncrementId(),
                    'order_id' => (int) $invoice->getOrderId(),
                    'state' => (int) $invoice->getState(),
                    'grand_total' => (float) $invoice->getGrandTotal(),
                    'subtotal' => (float) $invoice->getSubtotal(),
                    'tax_amount' => (float) $invoice->getTaxAmount(),
                    'created_at' => $invoice->getCreatedAt(),
                ];
            }

            return $this->paginatedResponse($result->getTotalCount(), $pageSize, $currentPage, $invoices);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'shipment-list',
        description: 'Lists shipments with pagination',
        meta: ['hidden' => true]
    )]
    public function listShipments(int $pageSize = 20, int $currentPage = 1, int $orderId = 0): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $shipmentRepository = MagentoBootstrap::get(\Magento\Sales\Api\ShipmentRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $sortOrderBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SortOrderBuilder::class);

            if ($orderId > 0) {
                $searchCriteriaBuilder->addFilter('order_id', $orderId);
            }

            $sortOrder = $sortOrderBuilder->setField('created_at')->setDirection('DESC')->create();
            $searchCriteria = $searchCriteriaBuilder
                ->addSortOrder($sortOrder)
                ->setPageSize($pageSize)
                ->setCurrentPage($currentPage)
                ->create();

            $result = $shipmentRepository->getList($searchCriteria);

            $shipments = [];
            foreach ($result->getItems() as $shipment) {
                $tracks = [];
                foreach ($shipment->getTracks() as $track) {
                    $tracks[] = [
                        'track_id' => (int) $track->getEntityId(),
                        'carrier_code' => $track->getCarrierCode(),
                        'title' => $track->getTitle(),
                        'track_number' => $track->getTrackNumber(),
                    ];
                }

                $shipments[] = [
                    'entity_id' => (int) $shipment->getEntityId(),
                    'increment_id' => $shipment->getIncrementId(),
                    'order_id' => (int) $shipment->getOrderId(),
                    'total_qty' => (float) $shipment->getTotalQty(),
                    'created_at' => $shipment->getCreatedAt(),
                    'tracks' => $tracks,
                ];
            }

            return $this->paginatedResponse($result->getTotalCount(), $pageSize, $currentPage, $shipments);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'shipment-track-add',
        description: 'Adds tracking information to a shipment',
        meta: ['hidden' => true, 'prerequisite' => 'Shipment must exist']
    )]
    public function addShipmentTrack(
        int $shipmentId,
        string $carrierCode,
        string $title,
        string $trackNumber
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('shipment-track-add')) {
            return $error;
        }

        try {
            $shipmentRepository = MagentoBootstrap::get(\Magento\Sales\Api\ShipmentRepositoryInterface::class);
            $trackFactory = MagentoBootstrap::get(\Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory::class);
            $shipmentTrackRepository = MagentoBootstrap::get(\Magento\Sales\Api\ShipmentTrackRepositoryInterface::class);

            $shipment = $shipmentRepository->get($shipmentId);

            $track = $trackFactory->create();
            $track->setParentId($shipmentId)
                ->setOrderId($shipment->getOrderId())
                ->setCarrierCode($carrierCode)
                ->setTitle($title)
                ->setTrackNumber($trackNumber);

            $savedTrack = $shipmentTrackRepository->save($track);

            return [
                'success' => true,
                'track_id' => (int) $savedTrack->getEntityId(),
                'shipment_id' => $shipmentId,
                'carrier_code' => $carrierCode,
                'track_number' => $trackNumber,
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->errorResponse("Shipment not found: $shipmentId");
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    #[McpTool(
        name: 'creditmemo-list',
        description: 'Lists credit memos with pagination',
        meta: ['hidden' => true]
    )]
    public function listCreditMemos(int $pageSize = 20, int $currentPage = 1, int $orderId = 0): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $creditmemoRepository = MagentoBootstrap::get(\Magento\Sales\Api\CreditmemoRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $sortOrderBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SortOrderBuilder::class);

            if ($orderId > 0) {
                $searchCriteriaBuilder->addFilter('order_id', $orderId);
            }

            $sortOrder = $sortOrderBuilder->setField('created_at')->setDirection('DESC')->create();
            $searchCriteria = $searchCriteriaBuilder
                ->addSortOrder($sortOrder)
                ->setPageSize($pageSize)
                ->setCurrentPage($currentPage)
                ->create();

            $result = $creditmemoRepository->getList($searchCriteria);

            $creditmemos = [];
            foreach ($result->getItems() as $creditmemo) {
                $creditmemos[] = [
                    'entity_id' => (int) $creditmemo->getEntityId(),
                    'increment_id' => $creditmemo->getIncrementId(),
                    'order_id' => (int) $creditmemo->getOrderId(),
                    'state' => (int) $creditmemo->getState(),
                    'grand_total' => (float) $creditmemo->getGrandTotal(),
                    'subtotal' => (float) $creditmemo->getSubtotal(),
                    'tax_amount' => (float) $creditmemo->getTaxAmount(),
                    'adjustment_positive' => (float) $creditmemo->getAdjustmentPositive(),
                    'adjustment_negative' => (float) $creditmemo->getAdjustmentNegative(),
                    'created_at' => $creditmemo->getCreatedAt(),
                ];
            }

            return $this->paginatedResponse($result->getTotalCount(), $pageSize, $currentPage, $creditmemos);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    private function formatOrderData(object $order, bool $includeItems, string $fields = ''): array
    {
        $data = [
            'entity_id' => (int) $order->getEntityId(),
            'increment_id' => $order->getIncrementId(),
            'state' => $order->getState(),
            'status' => $order->getStatus(),
            'customer_id' => $order->getCustomerId(),
            'customer_email' => $order->getCustomerEmail(),
            'customer_firstname' => $order->getCustomerFirstname(),
            'customer_lastname' => $order->getCustomerLastname(),
            'grand_total' => (float) $order->getGrandTotal(),
            'subtotal' => (float) $order->getSubtotal(),
            'tax_amount' => (float) $order->getTaxAmount(),
            'shipping_amount' => (float) $order->getShippingAmount(),
            'total_qty_ordered' => (float) $order->getTotalQtyOrdered(),
            'created_at' => $order->getCreatedAt(),
            'updated_at' => $order->getUpdatedAt(),
        ];

        if ($includeItems) {
            $items = [];
            foreach ($order->getItems() as $item) {
                $items[] = [
                    'item_id' => (int) $item->getItemId(),
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'qty_ordered' => (float) $item->getQtyOrdered(),
                    'qty_invoiced' => (float) $item->getQtyInvoiced(),
                    'qty_shipped' => (float) $item->getQtyShipped(),
                    'qty_refunded' => (float) $item->getQtyRefunded(),
                    'price' => (float) $item->getPrice(),
                    'row_total' => (float) $item->getRowTotal(),
                ];
            }
            $data['items'] = $items;
        }

        return $this->filterFields($data, $fields);
    }
}
