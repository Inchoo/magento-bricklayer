# Order Tools

14 tools for managing orders, invoices, shipments, and credit memos.

## Orders

### `order-get`

Get order by increment ID.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `incrementId` | string | *required* | Order increment ID |
| `fields` | string | `""` | Field filter |

### `order-list`

Search orders with optional status filter and pagination.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | `""` | Filter by order status |
| `fields` | string | `""` | Field filter |
| `pageSize` | int | 20 | Results per page |
| `currentPage` | int | 1 | Page number |
| `sortField` | string | `created_at` | Sort field |
| `sortDir` | string | `DESC` | Sort direction |
| `count_only` | bool | false | Return count only |

### `order-items`

Returns line items for an order.

### `order-comments`

Lists order status history and comments.

### `order-add-comment`

Add a comment to order history.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `orderId` | int | *required* | Order entity ID |
| `comment` | string | *required* | Comment text |
| `status` | string | `""` | New status |
| `notifyCustomer` | bool | false | Send notification |

### `order-cancel`

Cancel an order. Blocked in production mode.

### `order-hold` / `order-unhold`

Place an order on hold or release it.

## Invoices

### `invoice-create`

Create an invoice for an order.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `orderId` | int | *required* | Order entity ID |
| `capture` | bool | true | Capture payment |
| `notify` | bool | true | Send notification |

### `invoice-list`

List invoices with optional order filter and pagination.

## Shipments

### `shipment-create`

Create a shipment for an order.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `orderId` | int | *required* | Order entity ID |
| `notify` | bool | true | Send notification |

### `shipment-list`

List shipments with optional order filter and pagination.

### `shipment-track-add`

Add tracking information to a shipment.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `shipmentId` | int | *required* | Shipment ID |
| `carrierCode` | string | *required* | Carrier code |
| `title` | string | *required* | Carrier title |
| `trackNumber` | string | *required* | Tracking number |

## Credit Memos

### `creditmemo-create`

Create a credit memo (refund) for an order. Blocked in production mode.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `orderId` | int | *required* | Order entity ID |
| `notify` | bool | true | Send notification |

### `creditmemo-list`

List credit memos with optional order filter and pagination.
