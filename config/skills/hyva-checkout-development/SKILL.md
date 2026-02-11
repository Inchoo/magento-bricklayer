# Hyva Checkout & Magewire Development Skill

## Overview

Hyva Checkout is a reactive, server-driven checkout for Magento 2 built on **Magewire** (a Magento 2 adaptation of Laravel Livewire). It replaces Magento's Luma/KnockoutJS checkout with PHP-based components that handle reactivity via AJAX round-trips. The checkout is composed of **steps** declared in `hyva_checkout.xml`, with **Magewire components** declared in Layout XML and rendered through `.phtml` templates using `wire:` directives.

## When to Use This Skill

| Scenario | Use This Skill? |
|----------|-----------------|
| Customizing Hyva Checkout steps or layout | Yes |
| Adding a custom payment method integration | Yes |
| Adding a custom shipping method view | Yes |
| Building Magewire components (reactive PHP) | Yes |
| Creating custom checkout form fields | Yes |
| Implementing custom order placement logic | Yes |
| Extending existing checkout Magewire components | Yes |
| Building evaluation/validation for checkout | Yes |
| Standard Magento checkout (Luma/KnockoutJS) | No (use checkout-customization) |
| Hyva theme setup / child themes | No (use hyva-theme-development) |
| Non-checkout Hyva UI components | No (use hyva-ui-component-development) |

## Architecture Overview

```
┌──────────────────────────────────────────────────────┐
│  hyva_checkout.xml  (checkout configs, steps, conditions)  │
├──────────────────────────────────────────────────────┤
│  Layout XML         (component declarations, move/arrange)  │
├──────────────────────────────────────────────────────┤
│  Magewire PHP       (server-side reactive components)       │
├──────────────────────────────────────────────────────┤
│  .phtml templates   (wire: directives, ViewModels)          │
├──────────────────────────────────────────────────────┤
│  Frontend API JS    (evaluation, validation, navigation)    │
├──────────────────────────────────────────────────────┤
│  Alpine.js + Livewire.js  (client-side reactivity)          │
└──────────────────────────────────────────────────────┘
```

**Key module:** `Hyva_Checkout` (depends on `Magewirephp_Magewire` and `Magento_Checkout`)

**Route:** `/hyva_checkout/`

## Magewire Component Fundamentals

### Basic Component Class

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Magewire;

use Magewirephp\Magewire\Component;

class MyComponent extends Component
{
    // Public properties are reactive (synced with frontend)
    public string $name = '';
    public int $count = 0;

    // Callable from templates via wire:click="increment"
    public function increment(): void
    {
        $this->count++;
    }
}
```

### Registering in Layout XML

```xml
<!-- Simple object reference -->
<block name="my.component"
       template="Vendor_Module::magewire/my-component.phtml">
    <arguments>
        <argument name="magewire" xsi:type="object">
            \Vendor\Module\Magewire\MyComponent
        </argument>
    </arguments>
</block>

<!-- With initial property values -->
<block name="my.component"
       template="Vendor_Module::magewire/my-component.phtml">
    <arguments>
        <argument name="magewire" xsi:type="array">
            <item name="type" xsi:type="object">\Vendor\Module\Magewire\MyComponent</item>
            <item name="name" xsi:type="string">Default Name</item>
        </argument>
    </arguments>
</block>
```

### Template with `wire:` Directives

```php
<?php
declare(strict_types=1);

use Vendor\Module\Magewire\MyComponent;
use Magento\Framework\Escaper;

/** @var MyComponent $magewire */
/** @var Escaper $escaper */
?>

<div>
    <!-- Two-way data binding -->
    <input type="text" wire:model="name" />

    <!-- Lazy binding (on blur) -->
    <input type="text" wire:model.lazy="name" />

    <!-- Call method on click -->
    <button wire:click="increment">
        Count: <?= $escaper->escapeHtml((string) $magewire->count) ?>
    </button>

    <!-- Magic actions -->
    <button wire:click="$set('count', 0)">Reset</button>
    <button wire:click="$toggle('active')">Toggle</button>
    <button wire:click="$refresh()">Refresh</button>

    <!-- Prevent DOM re-rendering for an element -->
    <div wire:ignore>
        <!-- Third-party JS widget here -->
    </div>
</div>
```

### Component Lifecycle Hooks

```php
class MyComponent extends Component
{
    // Every request, immediately after instantiation
    public function boot(): void {}

    // Initial page load ONLY (like a constructor)
    public function mount(): void {}

    // Every request after boot/mount, before update methods
    public function booted(): void {}

    // Subsequent requests only, after state restoration
    public function hydrate(): void {}

    // Before ANY property update
    public function updating($value, string $name) {}

    // After ANY property update
    public function updated($value, string $name) {}

    // Property-specific hooks (for property $method)
    public function updatingMethod($value) {}
    public function updatedMethod($value): string { return $value; }

    // Nested property hooks (for $data['email_address'])
    public function updatedDataEmailAddress($value) {}

    // Before sending response to frontend
    public function dehydrate(): void {}
}
```

### Events Between Components

```php
class ShippingComponent extends Component
{
    // Declare listeners: event name => method name
    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'coupon_code_applied' => 'refresh',
    ];

    public function selectMethod(string $code): void
    {
        // Save method...

        // Emit to ALL components listening for this event
        $this->emit('shipping_method_selected', ['method' => $code]);

        // Emit to a SPECIFIC component by block name
        $this->emitTo('checkout.payment.methods', 'refresh');

        // Emit only to self
        $this->emitSelf('method_updated');
    }
}
```

### Flash Messages

```php
public function save(): void
{
    try {
        // Save logic...
        $this->dispatchSuccessMessage('Your changes were saved.');
    } catch (LocalizedException $e) {
        $this->dispatchErrorMessage($e->getMessage());
        // Also available:
        // $this->dispatchWarningMessage('Warning text');
        // $this->dispatchNoticeMessage('Notice text');
    }
}
```

### Browser Events from PHP

```php
public function onComplete(): void
{
    $this->dispatchBrowserEvent('checkout:step:complete', [
        'step' => 'shipping'
    ]);
}
```

### Loader Configuration

```php
class PaymentMethodList extends Component
{
    // Show loader text while specific properties/methods are processing
    protected $loader = [
        'method' => 'Saving method',         // Property update
        'placeOrder' => 'Processing order',   // Method call
    ];
}
```

## Alpine.js Integration with Magewire

### Accessing Component via `$wire`

```html
<div x-data>
    <h1 x-text="$wire.name"></h1>
    <button x-on:click="$wire.set('name', 'New Value')">Update</button>
    <button x-on:click="$wire.increment()">Call Method</button>
</div>
```

### Entangle: Two-Way Sync Between Alpine and Magewire

```html
<div x-data="{ localValue: $wire.entangle('serverValue') }">
    <!-- Changes to localValue sync to PHP $serverValue and vice versa -->
    <input type="text" x-model="localValue" />
</div>
```

### Emitting Events from JavaScript

```javascript
// Emit to all listening components
Magewire.emit('shipping_address_saved', { addressId: 123 });

// Emit to a specific component
Magewire.emitTo('checkout.payment.methods', 'refresh');
```

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
    public ?string $cardToken = null;

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
use Hyva\Checkout\Model\Magewire\Payment\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Payment\EvaluationResultInterface;

class MyPaymentPlaceOrderService extends AbstractPlaceOrderService
{
    public function canRedirect(): bool
    {
        return false; // Prevent auto-redirect after order placement
    }

    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory,
        ?int $orderId = null
    ): EvaluationResultInterface {
        $successRedirect = $resultFactory->createRedirect('checkout/onepage/success');

        if ($orderId === null) {
            return $successRedirect;
        }

        // Trigger 3DS validation on frontend
        $validate = $resultFactory->createValidation('my-payment-3ds');
        $validate->withFailureResult($successRedirect);

        // Navigate after validation completes
        $navigationTask = $resultFactory->createNavigationTask(
            'my-payment-redirect',
            $successRedirect
        );
        $navigationTask->executeAfter(true);

        return $resultFactory->createBatch()
            ->push($validate)
            ->push($navigationTask);
    }
}
```

### Redirect-Based Payment (Hosted Payment Page)

```php
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

## Evaluation API

The Evaluation API determines component "completeness" for step navigation and order placement.

### Implementing EvaluationInterface

```php
<?php
declare(strict_types=1);

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magewirephp\Magewire\Component;

class MyCheckoutComponent extends Component implements EvaluationInterface
{
    public ?string $selectedOption = null;

    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory
    ): EvaluationResultInterface {
        if ($this->selectedOption !== null) {
            return $resultFactory->createSuccess();
        }

        return $resultFactory->createErrorMessage()
            ->withMessage('Please select an option to continue.')
            ->withVisibilityDuration(5000)
            ->asWarning();
    }
}
```

### Evaluation Result Types

| Type | Factory Method | Purpose |
|------|---------------|---------|
| Success | `createSuccess()` | Component is complete |
| Blocking | `createBlocking()` | Block navigation silently |
| ErrorMessage | `createErrorMessage()` | Show flash message |
| Event | `createEvent()` | Dispatch custom window event |
| ErrorMessageEvent | `createErrorMessageEvent()` | Error message + custom event |
| ErrorEvent | `createErrorEvent()` | False result bound to validation API |
| Validation | `createValidation('name')` | Trigger frontend JS validator |
| Redirect | `createRedirect('url')` | Redirect browser |
| NavigationTask | `createNavigationTask('name', $result)` | Execute after navigation |
| Batch | `createBatch()` | Combine multiple results |
| MessageDialog | `createMessageDialog('title')` | Show dialog overlay |

### Evaluation Result Examples

```php
// Success
return $resultFactory->createSuccess();

// Error message with auto-hide
return $resultFactory->createErrorMessage()
    ->withMessage('Something went wrong.')
    ->withVisibilityDuration(5000)
    ->asWarning();

// Frontend validation callback
return $resultFactory->createValidation('validateCreditCard')
    ->withFailureResult(
        $resultFactory->createErrorMessageEvent()
            ->withCustomEvent('payment:method:error')
            ->withMessage('Invalid card details.')
    );

// Redirect with notification
return $resultFactory->createRedirect('https://payment-gateway.com/pay')
    ->withTimeout(2500)
    ->withNotificationDialog()
    ->withNotificationMessage('Redirecting to payment...');

// Batch of multiple results
return $resultFactory->createBatch()
    ->push($resultFactory->createErrorMessage()->withMessage('Warning.')->asWarning())
    ->push($resultFactory->createEvent()->withCustomEvent('my-event')->dispatch());
```

### Evaluatable Trait for Sub-Evaluations

```php
use Hyva\Checkout\Magewire\Concern\Evaluatable;

class PaymentMethodList extends Component implements EvaluationInterface
{
    use Evaluatable;

    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory
    ): EvaluationResultInterface {
        // Run sub-evaluations
        $this->evaluateSelection();

        if ($this->evaluationBatch()->containsFailureResults()) {
            return $this->evaluationBatch();
        }

        return $this->evaluationBatch()->push(
            $this->evaluationBatch()->factory()
                ->createSuccess([], 'payment:method:success')
                ->dispatch()
        );
    }

    private function evaluateSelection(): static
    {
        if ($this->method === null) {
            $this->evaluationBatch()->push(
                $this->evaluationBatch()->factory()
                    ->createErrorMessageEvent()
                    ->withCustomEvent('payment:method:error')
                    ->withMessage('Please select a payment method.')
            );
        }
        return $this;
    }
}
```

## Frontend API (JavaScript)

### Registering Frontend Validators

```javascript
// Wait for checkout evaluation API initialization
window.addEventListener('checkout:init:evaluation', () => {

    // Synchronous validator (throw = failure)
    hyvaCheckout.evaluation.registerValidator('validateMyField', (element, component) => {
        const input = element.querySelector('#my-field');
        if (!input || !input.value) {
            throw new Error('Field is required');
        }
    });

    // Async validator (Promise-based, e.g., 3DS authentication)
    hyvaCheckout.evaluation.registerValidator('my-payment-3ds', (element) => {
        return new Promise((resolve, reject) => {
            window.dispatchEvent(new Event('my-payment:3ds:show'));

            window.addEventListener('my-payment:3ds:result', (event) => {
                event.detail.success ? resolve() : reject();
            });
        });
    });
});
```

### Navigation API

```javascript
// Navigate to a specific step
hyvaCheckout.navigation.stepTo('payment', false);

// Navigate to first step
hyvaCheckout.navigation.stepToFirst();
```

### Messenger API

```javascript
// Display inline message for a checkout section
hyvaCheckout.messenger.dispatch(
    'payment:method',
    'Please check your payment details.'
);
```

## Form API

### Custom Form Definition

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Form;

use Hyva\Checkout\Model\Form\AbstractEntityForm;
use Hyva\Checkout\Model\Form\EntityFormInterface;

class CustomForm extends AbstractEntityForm
{
    public const FORM_NAMESPACE = 'vendor_custom_form';

    public function populate(): EntityFormInterface
    {
        $this->addField(
            $this->createField('company_name', 'text', [
                'data' => ['label' => 'Company Name']
            ])
        );

        $this->addField(
            $this->createField('vat_number', 'text', [
                'data' => ['label' => 'VAT Number']
            ])
        );

        $this->addElement(
            $this->createElement('submit', [
                'data' => ['label' => 'Save']
            ])
        );

        $this->setAttribute('wire:submit.prevent="submit"');
        return $this;
    }

    public function getTitle(): string
    {
        return 'Business Details';
    }
}
```

### Form Save Service

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Form;

use Hyva\Checkout\Model\Form\AbstractEntityFormSaveService;
use Hyva\Checkout\Model\Form\EntityFormInterface;

class CustomFormSaveService extends AbstractEntityFormSaveService
{
    public function save(EntityFormInterface $form): EntityFormInterface
    {
        $companyName = $form->getField('company_name')->getValue();
        $vatNumber = $form->getField('vat_number')->getValue();

        // Persist data to quote or custom table...

        return $form;
    }
}
```

### Magewire Form Component

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Magewire\Checkout;

use Hyva\Checkout\Magewire\Component\AbstractForm;

class BusinessDetails extends AbstractForm
{
    // Form validation rules (Laravel-like syntax)
    public array $rules = [
        'data.company_name' => 'required|min:2',
        'data.vat_number' => 'required|regex:/^[A-Z]{2}[0-9]+$/',
    ];

    // Custom validation messages
    public array $messages = [
        'data.company_name.required' => 'Company name is required.',
        'data.vat_number.regex' => 'Please enter a valid VAT number (e.g., NL123456789B01).',
    ];
}
```

### Rendering Forms in Templates

```php
<?php
/** @var \Hyva\Checkout\Magewire\Component\AbstractForm $magewire */
/** @var Escaper $escaper */

$form = $magewire->getPublicForm();
?>
<form <?= /* @noEscape */ $form->renderAttributes($escaper) ?>>
    <div class="checkout-form-elements grid grid-cols-12 gap-x-3">
        <?php foreach ($form->getElements() as $element): ?>
            <?php if ($element->canRender()): ?>
                <div class="<?= $escaper->escapeHtmlAttr(
                    $element->renderWrapperClass(['col-span-12 space-y-1'])
                ) ?>">
                    <?= /* @noEscape */ $element->render() ?>

                    <?php if ($magewire->hasError($element->getTracePath())): ?>
                        <ul class="messages" role="list" aria-live="polite">
                            <li><?= /** @noEscape */ $magewire->getError($element->getId()) ?></li>
                        </ul>
                    <?php endif ?>
                </div>
            <?php endif ?>
        <?php endforeach ?>
    </div>

    <?php $submit = $form->getElement('submit'); ?>
    <?php if ($submit && $submit->isVisible()): ?>
        <div class="checkout-form-toolbar flex flex-row gap-x-2 rounded-md">
            <?= /* @noEscape */ $submit->render() ?>
        </div>
    <?php endif ?>
</form>
```

### Form Modifiers

Extend forms from third-party modules without modifying the original:

```xml
<!-- etc/frontend/di.xml -->
<type name="Vendor\Module\Model\Form\CustomForm">
    <arguments>
        <argument name="entityFormModifiers" xsi:type="array">
            <item name="add_loyalty_field" xsi:type="object" sortOrder="500">
                Vendor\Module\Model\Form\Modifier\AddLoyaltyFieldModifier
            </item>
        </argument>
    </arguments>
</type>
```

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Form\Modifier;

use Hyva\Checkout\Model\Form\EntityFormInterface;
use Hyva\Checkout\Model\Form\EntityFormModifier\AbstractEntityFormModifier;

class AddLoyaltyFieldModifier extends AbstractEntityFormModifier
{
    public function modify(EntityFormInterface $form): EntityFormInterface
    {
        $form->addField(
            $form->createField('loyalty_number', 'text', [
                'data' => ['label' => 'Loyalty Card Number']
            ])
        );

        return $form;
    }
}
```

## Common Checkout Patterns

### Payment Method List Template Pattern

```php
<?php
/** @var \Hyva\Checkout\Magewire\Checkout\Payment\MethodList $magewire */
$viewModel = $viewModels->require(ViewModel::class);
$methods = $viewModel->getList();
?>
<div id="payment-methods">
    <div class="space-y-2" data-method="<?= $escaper->escapeHtmlAttr($magewire->method) ?>">
        <?php foreach ($methods as $method): ?>
            <div class="border-2 rounded-md
                 <?= $magewire->method === $method->getCode()
                     ? 'active bg-primary bg-opacity-10 border-primary'
                     : 'inactive bg-white' ?>"
                 wire:key="<?= $escaper->escapeHtmlAttr($method->getCode()) ?>">
                <label class="flex items-center gap-x-2.5 mb-0 p-4 cursor-pointer">
                    <input type="radio"
                           name="payment-method-option"
                           class="form-radio"
                           value="<?= $escaper->escapeHtmlAttr($method->getCode()) ?>"
                           wire:model="method" />
                    <span class="text-gray-700 font-medium">
                        <?= $escaper->escapeHtml($method->getTitle()) ?>
                    </span>
                </label>
                <!-- Custom payment view renders here when selected -->
            </div>
        <?php endforeach ?>
    </div>
</div>
```

### Real Component Pattern: CouponCode

```php
class CouponCode extends Component
{
    public ?string $couponCode = null;
    public int $couponHits = 0;

    public function boot(): void
    {
        $couponCode = $this->couponManagement->get($this->sessionCheckout->getQuoteId());
        $this->couponCode = ($couponCode && $couponCode != '') ? $couponCode : null;
    }

    public function applyCouponCode()
    {
        try {
            $this->couponManagement->set($quoteId, $this->couponCode);
            $this->dispatchSuccessMessage('Your coupon was successfully applied.');
            $this->emit('coupon_code_applied', ['code' => $this->couponCode]);
        } catch (LocalizedException $e) {
            $this->couponCode = null;
            $this->couponHits++;
            return $this->dispatchWarningMessage($e->getMessage());
        }
    }

    public function revokeCouponCode()
    {
        $this->couponManagement->remove($this->sessionCheckout->getQuoteId());
        $this->reset();
        $this->dispatchSuccessMessage('Your coupon was successfully removed.');
        $this->emit('coupon_code_revoked', ['code' => $couponCode]);
    }
}
```

## Checkout Events Reference

### Key Magewire Events (Component-to-Component)

| Event | Emitted By | Listeners |
|-------|-----------|-----------|
| `billing_address_saved` | BillingDetails | PaymentMethodList |
| `billing_address_submitted` | BillingDetails | AddressForm |
| `shipping_address_saved` | ShippingDetails | PaymentMethodList |
| `shipping_address_activated` | ShippingDetails | ShippingMethodList |
| `shipping_method_selected` | ShippingMethodList | PriceSummary |
| `payment_method_selected` | PaymentMethodList | PriceSummary |
| `coupon_code_applied` | CouponCode | PaymentMethodList, ShippingMethodList |
| `coupon_code_revoked` | CouponCode | PaymentMethodList, ShippingMethodList |

### Key Magento Events

| Event | Purpose |
|-------|---------|
| `hyva_checkout_init_after` | Initialize default addresses and methods |
| `hyva_checkout_quote_id_changed` | Reset checkout session |
| `checkout_submit_all_after` | Transfer quote comment to order |

## Extending Existing Components

### Override via Layout XML

```xml
<!-- Replace a template -->
<referenceBlock name="checkout.payment.methods"
                template="Vendor_Module::checkout/payment/custom-method-list.phtml" />

<!-- Add a block before/after -->
<referenceContainer name="checkout.payment.methods.before">
    <block name="my.payment.notice"
           template="Vendor_Module::checkout/payment/notice.phtml" />
</referenceContainer>

<referenceContainer name="checkout.payment.methods.after">
    <block name="my.payment.help"
           template="Vendor_Module::checkout/payment/help-text.phtml" />
</referenceContainer>
```

### Override via Theme

Place template overrides in your Hyva child theme:

```
app/design/frontend/Vendor/hyva-child/
  Hyva_Checkout/
    templates/
      checkout/payment/method-list.phtml     # Override payment method list
      checkout/shipping/method-list.phtml    # Override shipping method list
      magewire/component/form.phtml          # Override form rendering
```

### Plugin on Magewire Component

```php
<!-- etc/frontend/di.xml -->
<type name="Hyva\Checkout\Magewire\Checkout\Payment\MethodList">
    <plugin name="vendor_module_payment_method_list"
            type="Vendor\Module\Plugin\Checkout\Payment\MethodList" />
</type>
```

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Plugin\Checkout\Payment;

use Hyva\Checkout\Magewire\Checkout\Payment\MethodList;

class MethodListPlugin
{
    public function afterUpdatedMethod(MethodList $subject, string $result): string
    {
        // Custom logic after payment method selection
        return $result;
    }
}
```

## Order Summary & Totals

### Adding Custom Total Segment

```xml
<referenceBlock name="price-summary.total-segments">
    <block name="price-summary.total-segments.custom_fee"
           as="custom_fee"
           template="Vendor_Module::checkout/price-summary/total-segments/custom-fee.phtml" />
</referenceBlock>
```

The block alias (`as="custom_fee"`) must match the total collector name from Magento's total system.

## Database Extensions

Hyva Checkout adds:
- `quote.customer_comment` (TEXT) - Customer order comment
- `sales_order_status_history.is_customer_comment` (BOOLEAN) - Comment origin flag

## Admin Configuration

System config path: `hyva_themes_checkout/`

Key config groups:
- `component/` - Enable/disable coupon code, order comment
- `design/` - Form field styling, icon sizes, price formatting
- `developer/` - Debug mode, evaluation API, experimental features
- `navigation/` - Back button, cart button display
- `breadcrumbs/` - Breadcrumb rendering style
- `signin_registration/` - Sign-in button display
- `billing/` - Billing address configuration
- `address_form/` - Address field customization

## Best Practices

1. **Use `EvaluationInterface`** for any component that must validate before step navigation or order placement
2. **Emit events** for cross-component communication instead of direct coupling
3. **Use `$loader`** to show loading states during server round-trips
4. **Use `boot()` for state restoration** - it runs on every request, unlike `mount()` which only runs on initial load
5. **Use `updated{PropertyName}()` hooks** to trigger side effects when specific properties change
6. **Declare components in `hyva_checkout_components.xml`** and use `<move>` in step layouts for flexibility
7. **Match block alias to method code** for payment (`as="method_code"`) and shipping (`as="carrier_method"`)
8. **Add messenger blocks** for each section to enable inline error display
9. **Use `AbstractPlaceOrderService`** (not the deprecated `PlaceOrderServiceInterface`) for custom payment order placement
10. **Register frontend validators** via `checkout:init:evaluation` event for client-side validation before server round-trip
11. **Use form modifiers** to extend checkout forms from third-party modules without modifying original form classes
12. **Keep components focused** - each component should handle one concern (address, payment, shipping, etc.)
13. **Always escape output** in templates using `$escaper` methods
14. **Use `wire:key`** on repeated elements to help Magewire track DOM updates correctly
