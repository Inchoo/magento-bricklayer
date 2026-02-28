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
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

class OrderTools
{
    use ChecksConfig;
    use FiltersFields;
    use RequiresMagento;
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
            return ['error' => true, 'message' => 'Order increment ID is required'];
        }

        try {
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

            $searchCriteria = $searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->create();

            $orders = $orderRepository->getList($searchCriteria)->getItems();

            if (empty($orders)) {
                return ['error' => true, 'message' => "Order not found: $incrementId"];
            }

            $order = reset($orders);
            return $this->formatOrderData($order, true, $fields);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
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
                return [
                    'total' => $result->getTotalCount(),
                    'count_only' => true,
                ];
            }

            $orders = [];
            foreach ($result->getItems() as $order) {
                $orders[] = $this->formatOrderData($order, false, $fields);
            }

            return [
                'total_count' => $result->getTotalCount(),
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'has_more' => ($currentPage * $pageSize) < $result->getTotalCount(),
                'items' => $orders,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
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
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => "Order not found: $orderId"];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => "Order not found: $orderId"];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
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

            return [
                'total_count' => $result->getTotalCount(),
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'has_more' => ($currentPage * $pageSize) < $result->getTotalCount(),
                'items' => $invoices,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
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

            return [
                'total_count' => $result->getTotalCount(),
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'has_more' => ($currentPage * $pageSize) < $result->getTotalCount(),
                'items' => $shipments,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
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
            return ['error' => true, 'message' => "Shipment not found: $shipmentId"];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
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

            return [
                'total_count' => $result->getTotalCount(),
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'has_more' => ($currentPage * $pageSize) < $result->getTotalCount(),
                'items' => $creditmemos,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
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
