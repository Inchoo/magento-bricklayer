# Customer Tools

12 tools for managing customers, addresses, groups, and validation.

## Customers

### `customer-get`

Get customer by email address.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `email` | string | *required* | Customer email |
| `fields` | string | `""` | Field filter |

### `customer-list`

Search customers with pagination.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fields` | string | `""` | Field filter |
| `pageSize` | int | 20 | Results per page |
| `currentPage` | int | 1 | Page number |
| `sortField` | string | `entity_id` | Sort field |
| `sortDir` | string | `DESC` | Sort direction |
| `count_only` | bool | false | Return count only |

### `customer-create`

Create a new customer.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `email` | string | *required* | Customer email |
| `firstname` | string | *required* | First name |
| `lastname` | string | *required* | Last name |
| `groupId` | int | 1 | Customer group ID |
| `storeId` | int | 1 | Store ID |

### `customer-update`

Update customer data.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `customerId` | int | *required* | Customer ID |
| `firstname` | string | `""` | New first name |
| `lastname` | string | `""` | New last name |
| `groupId` | int | 0 | New group (0 to skip) |

### `customer-delete`

Delete a customer. Blocked in production mode.

### `customer-validate`

Validate customer data before create/update.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `email` | string | *required* | Customer email |
| `firstname` | string | `""` | First name |
| `lastname` | string | `""` | Last name |
| `websiteId` | int | 1 | Website ID |

### `customer-groups-list`

List all customer groups. No parameters.

### `customer-orders`

List orders for a specific customer.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `customerId` | int | *required* | Customer ID |
| `pageSize` | int | 20 | Results per page |
| `currentPage` | int | 1 | Page number |

## Addresses

### `customer-addresses`

List all addresses for a customer.

### `customer-address-create`

Add an address to a customer.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `customerId` | int | *required* | Customer ID |
| `firstname` | string | *required* | First name |
| `lastname` | string | *required* | Last name |
| `street` | string | *required* | Street (use `\|` for multiple lines) |
| `city` | string | *required* | City |
| `postcode` | string | *required* | Postal code |
| `countryId` | string | *required* | Country code (e.g., `US`) |
| `telephone` | string | *required* | Phone number |
| `regionCode` | string | `""` | Region/state code |
| `defaultBilling` | bool | false | Set as default billing |
| `defaultShipping` | bool | false | Set as default shipping |

### `customer-address-update`

Update an existing address.

### `customer-address-delete`

Delete a customer address. Blocked in production mode.
