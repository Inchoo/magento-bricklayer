<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Order Prompts
 *
 * Provides MCP prompts for order processing operations.
 */
class OrderPrompts
{
    /**
     * Guides through order processing workflow.
     *
     * @param string $orderIncrementId Order increment ID
     * @param string $action Action to perform (invoice, ship, refund, cancel)
     * @param string $notes Additional notes or instructions
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'process-order',
        description: 'Guides through order processing (invoice, ship, refund)'
    )]
    public function processOrder(
        string $orderIncrementId,
        string $action = 'invoice',
        string $notes = ''
    ): array {
        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Process order #{$orderIncrementId} with action: {$action}

**Order:** {$orderIncrementId}
**Action:** {$action}
**Notes:** {$notes}

Guide me through the complete order processing workflow:

1. **Order Verification**:
   - Use `order-get` tool to retrieve order details
   - Verify current order state allows the action
   - Check payment method compatibility

2. **Action: {$action}**:

   For **invoice**:
   - Verify order is not already invoiced
   - Check payment capture settings
   - Use `invoice-create` tool
   - Options: capture online/offline, partial invoice

   For **ship**:
   - Verify order has items to ship
   - Prepare tracking information
   - Use `shipment-create` tool
   - Options: partial shipment, multiple tracking numbers

   For **refund**:
   - Verify order has invoices
   - Calculate refund amounts
   - Use `creditmemo-create` tool
   - Options: adjustment amounts, return to stock

   For **cancel**:
   - Verify order can be cancelled
   - Check payment reversal needs
   - Use `order-cancel` tool

3. **Workflow Sequence** (if full processing):
   ```
   Order Placed → Invoice → Ship → Complete
                      ↓
                   Refund (if needed)
   ```

4. **Post-Action Steps**:
   - Customer notification settings
   - Order status history comments
   - Inventory adjustment verification

5. **Error Handling**:
   - Common issues and solutions
   - Partial action scenarios
   - Payment gateway considerations
PROMPT
            ]
        ];
    }

    /**
     * Creates customer segment logic.
     *
     * @param string $segmentName Segment name
     * @param string $conditions Segment conditions description
     * @param string $description Segment description
     * @return array<array<string, string>> Prompt messages
     */
    #[McpPrompt(
        name: 'create-customer-segment',
        description: 'Creates customer segment logic (Adobe Commerce feature)'
    )]
    public function createCustomerSegment(
        string $segmentName,
        string $conditions = '',
        string $description = ''
    ): array {
        return [
            [
                'role' => 'user',
                'content' => <<<PROMPT
Create a customer segment with the following specifications:

**Segment Name:** {$segmentName}
**Conditions:** {$conditions}
**Description:** {$description}

Note: Customer Segments are an Adobe Commerce (Enterprise) feature. For Community edition, provide alternative approaches.

**For Adobe Commerce:**

1. **Segment Configuration**:
   - Navigate to Customers > Segments
   - Create new segment
   - Define conditions using the rule builder

2. **Condition Types**:
   - Customer attributes (group, created date, etc.)
   - Order history (total, count, products)
   - Shopping cart contents
   - Product views and wishlists

3. **Programmatic Creation**:
   ```php
   // Use Magento\CustomerSegment API
   // Configure conditions array
   // Save segment entity
   ```

**For Community Edition Alternatives:**

1. **Customer Groups**:
   - Use customer groups for basic segmentation
   - Assign customers via code or admin

2. **Custom Implementation**:
   ```php
   // Create custom module with:
   // - Segment entity and repository
   // - Rule condition engine
   // - Customer matching logic
   ```

3. **Third-Party Extensions**:
   - Amasty Customer Segments
   - Mirasvit Customer Segments
   - Custom development

4. **Marketing Automation Integration**:
   - Export customer data to external CRM
   - Use Mailchimp/Klaviyo segmentation
   - Sync segments back to Magento

Provide the most appropriate solution based on the Magento edition being used.
PROMPT
            ]
        ];
    }
}
