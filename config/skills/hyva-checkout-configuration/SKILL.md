---
name: hyva-checkout-configuration
description: Configure Hyva Checkout definitions, steps, conditions, layout handles, component placement, payment and shipping blocks, and Place Order Service DI. Use when editing hyva_checkout.xml or checkout layout XML, adding or moving components, or wiring payment and shipping integrations.
---

# Hyvä Checkout: Configuration & Layout

> Start with [hyva-checkout](../hyva-checkout/SKILL.md) for architecture and primary navigation
> rules. See [hyva-checkout-magewire](../hyva-checkout-magewire/SKILL.md) for checkout component
> behavior and [hyva-checkout-apis](../hyva-checkout-apis/SKILL.md) for evaluation, form, and
> frontend APIs.

Resolve the core skill's compatibility profile before attaching a Magewire class or copying
component syntax. Layout concepts may span checkout versions; the component behind a block may not.

## Checkout XML Configuration

### Defining a Custom Checkout (`etc/hyva_checkout.xml`)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Hyva_Checkout:etc/hyva_checkout.xsd">

    <!-- Inherit from default and customize -->
    <checkout name="custom" label="Custom Checkout" layout="2columns" parent="default">

        <!-- Add a new step between shipping and payment -->
        <step name="extras"
              label="Extra Options"
              route="extras"
              after="shipping"
              before="payment">
            <!-- Only show for physical products -->
            <condition name="is_physical" if="is_physical"/>
            <!-- Include custom layout handle -->
            <update handle="hyva_checkout_custom_extras"/>
        </step>

        <!-- Modify existing step -->
        <step name="payment" label="Review &amp; Pay"/>

        <!-- Remove a step -->
        <step name="login" remove="true"/>
    </checkout>
</config>
```

### Step Attributes

| Attribute | Description |
|-----------|-------------|
| `name` | Step identifier (used in layout handle construction) |
| `label` | Display label in navigation (translatable) |
| `route` | URL segment (defaults to step name) |
| `layout` | Step-specific layout: `1column`, `2columns`, `3columns` |
| `before`/`after` | Position relative to another step |
| `remove` | Remove a step declared by parent config |
| `ifconfig` | System config path condition (negate with `!`) |
| `clone` | Replicate from another checkout: `{checkout}.{step}` |

### Built-in Conditions

| Identifier | Class | Purpose |
|------------|-------|---------|
| `is_always_allow` | IsAlwaysAllow | Always true |
| `is_customer` | IsCustomer | Logged-in customer |
| `is_guest` | IsGuest | Guest checkout |
| `is_physical` | IsPhysical | Cart has physical items |
| `is_virtual` | IsVirtual | Cart is fully virtual |
| `is_device` | IsDevice | Device-based condition |

### Registering Custom Conditions

```xml
<!-- etc/frontend/di.xml -->
<type name="Hyva\Checkout\Model\CustomConditionFactory">
    <arguments>
        <argument name="customConditionTypes" xsi:type="array">
            <item name="has_gift_card" xsi:type="string">
                Vendor\Module\Model\Checkout\Condition\HasGiftCard
            </item>
        </argument>
    </arguments>
</type>
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Checkout\Condition;

use Hyva\Checkout\Model\CustomConditionInterface;

class HasGiftCard implements CustomConditionInterface
{
    /**
     * @return bool
     */
    public function validate(): bool
    {
        // Return true if condition is met
        return $this->cart->hasGiftCards();
    }
}
```

## Layout XML for Checkout Components

### Component Declaration (`hyva_checkout_components.xml`)

All checkout components are declared in the `hyva_checkout_components.xml` handle, then moved into steps via step-specific layout handles:

```xml
<!-- view/frontend/layout/hyva_checkout_components.xml -->
<page>
    <body>
        <referenceBlock name="hyva.checkout.components">
            <container name="checkout.my-section" htmlTag="fieldset" htmlId="my-section">
                <block name="checkout.my-section.title"
                       template="Hyva_Checkout::section/title.phtml">
                    <arguments>
                        <argument name="html_tag" xsi:type="string">legend</argument>
                    </arguments>
                    <action method="setTitle">
                        <argument name="title" translate="true" xsi:type="string">My Section</argument>
                    </action>
                </block>

                <!-- Component Messenger for this section -->
                <block name="component-messenger-my-section"
                       template="Hyva_Checkout::page/messenger.phtml" before="-">
                    <arguments>
                        <argument name="event_prefix" xsi:type="string">my:section</argument>
                    </arguments>
                </block>

                <block name="checkout.my-component"
                       template="Vendor_Module::checkout/my-component.phtml">
                    <arguments>
                        <argument name="magewire" xsi:type="object">
                            \Vendor\Module\Magewire\Checkout\MyComponent
                        </argument>
                    </arguments>
                </block>
            </container>
        </referenceBlock>
    </body>
</page>
```

### Moving Components into Steps

```xml
<!-- view/frontend/layout/hyva_checkout_custom_extras.xml -->
<page>
    <body>
        <move element="checkout.my-section"
              destination="column.main"
              before="-"/>
    </body>
</page>
```

### Existing Component Section Names

| Section Container | Purpose |
|-------------------|---------|
| `checkout.guest-details.section` | Guest email/login |
| `checkout.shipping-details.section` | Shipping address form |
| `checkout.billing-details.section` | Billing address form |
| `checkout.shipping.section` | Shipping method selection |
| `checkout.payment.section` | Payment method selection |
| `checkout.quote-summary.section` | Order summary sidebar |
| `checkout.section.additional-options` | Customer comment, extras |
| `checkout.section.quote-actions` | Terms & conditions, place order |

## Payment Method Integration

Payment layout registers selection UI; it does not own quote-to-order conversion. The primary Place
Order action invokes the selected Place Order Service only after navigation, validation, and
Evaluation checks pass. Build every payment view so it still works when payment is not the final or
currently visible step.

### Auto-Registration

Any enabled payment method automatically appears in the payment step. Methods needing no customer interaction (e.g., check/money order, bank transfer, cash on delivery) work out of the box.

### Custom Payment Component

```xml
<!-- view/frontend/layout/hyva_checkout_components.xml -->
<referenceBlock name="checkout.payment.methods">
    <block name="checkout.payment.method.my_payment"
           as="my_payment"
           template="Vendor_Module::checkout/payment/method/my-payment.phtml">
        <arguments>
            <argument name="magewire" xsi:type="object">
                \Vendor\Module\Magewire\Checkout\Payment\Method\MyPayment
            </argument>
            <!-- Optional: icon and subtitle metadata -->
            <argument name="metadata" xsi:type="array">
                <item name="icon" xsi:type="array">
                    <item name="svg" xsi:type="string">heroicons/outline/credit-card</item>
                    <item name="attributes" xsi:type="array">
                        <item name="fill" xsi:type="string">none</item>
                    </item>
                </item>
                <item name="subtitle" xsi:type="string">Visa, Mastercard</item>
            </argument>
        </arguments>
    </block>
</referenceBlock>
```

**Block alias (`as`) must match the payment method code.**

### Payment Component with Evaluation

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Checkout\Payment\Method;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magewirephp\Magewire\Component;

class MyPayment extends Component implements EvaluationInterface
{
    /**
     * @var string|null
     */
    public ?string $cardToken = null;

    /**
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory
    ): EvaluationResultInterface {
        if ($this->cardToken) {
            return $resultFactory->createSuccess();
        }

        // Trigger frontend validation callback
        return $resultFactory->createValidation('validateMyPayment')
            ->withFailureResult(
                $resultFactory->createErrorMessageEvent()
                    ->withCustomEvent('payment:method:error')
                    ->withMessage('Please provide valid payment details.')
                    ->withVisibilityDuration(5000)
            );
    }
}
```

### Custom Place Order Service

For payment methods requiring special order placement logic (redirects, 3DS, hosted pages):

```xml
<!-- etc/frontend/di.xml -->
<type name="Hyva\Checkout\Model\Magewire\Payment\PlaceOrderServiceProvider">
    <arguments>
        <argument name="placeOrderServiceList" xsi:type="array">
            <!-- Item name = payment method code -->
            <item name="my_payment" xsi:type="object">
                Vendor\Module\Model\Payment\MyPaymentPlaceOrderService
            </item>
        </argument>
    </arguments>
</type>
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;

class MyPaymentPlaceOrderService extends AbstractPlaceOrderService
{
    /**
     * @param EvaluationResultFactory $resultFactory
     * @param int|null $orderId
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory,
        ?int $orderId = null
    ): EvaluationResultInterface {
        $batch = $resultFactory->createBatch([
            $resultFactory->createSuccess()
        ]);

        if ($orderId === null) {
            $batch->push(
                $resultFactory->createErrorMessage()
                    ->withMessage('The order could not be confirmed.')
            );
        }

        return $batch;
    }
}
```

`AbstractPlaceOrderService` already implements the complete contract. Override only the methods
the integration needs: `placeOrder()`, `canPlaceOrder()`, `handleException()`, `canRedirect()`,
`getRedirectUrl()`, `evaluateCompletion()`, or `getData()`. Use the Frontend Payment API for
browser SDK and 3DS interaction rather than turning the payment component into the orchestrator.

### Redirect-Based Payment (Hosted Payment Page)

```php
/**
 * @param EvaluationResultFactory $resultFactory
 * @param int|null $orderId
 * @return EvaluationResultInterface
 */
public function evaluateCompletion(
    EvaluationResultFactory $resultFactory,
    ?int $orderId = null
): EvaluationResultInterface {
    $redirectUrl = $this->getHostedPaymentUrl($orderId);

    return $resultFactory->createRedirect($redirectUrl)
        ->withTimeout(0)
        ->withNotificationDialog()
        ->withNotificationMessage('Redirecting to payment provider...');
}
```

Render PSP overlays, 3DS modals, and durable browser integration scripts outside the payment
method's step-scoped markup, for example under `hyva.checkout.init-validation.after`. The Place
Order action can run when the payment step is not visible.

## Shipping Method Integration

### Auto-Registration

Enabled shipping methods automatically appear. Methods needing no additional input work without customization.

### Custom Shipping Method View

```xml
<!-- view/frontend/layout/hyva_checkout_components.xml -->
<referenceBlock name="checkout.shipping.methods">
    <block name="checkout.shipping.method.mycarrier_standard"
           as="mycarrier_standard"
           template="Vendor_Module::checkout/shipping/method/pickup-selector.phtml">
        <arguments>
            <argument name="magewire" xsi:type="object">
                \Vendor\Module\Magewire\Checkout\Shipping\PickupSelector
            </argument>
            <argument name="metadata" xsi:type="array">
                <item name="icon" xsi:type="array">
                    <item name="svg" xsi:type="string">heroicons/outline/truck</item>
                    <item name="attributes" xsi:type="array">
                        <item name="fill" xsi:type="string">none</item>
                    </item>
                </item>
            </argument>
        </arguments>
    </block>
</referenceBlock>
```

**Block alias (`as`) must follow pattern: `carrierCode_methodCode`.**
