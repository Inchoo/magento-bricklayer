# Hyvä Checkout: Evaluation, Form & Frontend APIs

> Related: See [hyva-checkout-magewire](../hyva-checkout-magewire/SKILL.md) for Magewire fundamentals, and [hyva-checkout-configuration](../hyva-checkout-configuration/SKILL.md) for checkout XML and layout config.

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
    /**
     * @var string|null
     */
    public ?string $selectedOption = null;

    /**
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
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

    /**
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
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

    /**
     * @return static
     */
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

    /**
     * @return EntityFormInterface
     */
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

    /**
     * @return string
     */
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
    /**
     * @param EntityFormInterface $form
     * @return EntityFormInterface
     */
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

    /**
     * @var array
     */
    public array $rules = [
        'data.company_name' => 'required|min:2',
        'data.vat_number' => 'required|regex:/^[A-Z]{2}[0-9]+$/',
    ];

    // Custom validation messages

    /**
     * @var array
     */
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
    /**
     * @param EntityFormInterface $form
     * @return EntityFormInterface
     */
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
    /**
     * @var string|null
     */
    public ?string $couponCode = null;

    /**
     * @var int
     */
    public int $couponHits = 0;

    /**
     * @return void
     */
    public function boot(): void
    {
        $couponCode = $this->couponManagement->get($this->sessionCheckout->getQuoteId());
        $this->couponCode = ($couponCode && $couponCode != '') ? $couponCode : null;
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
    /**
     * @param MethodList $subject
     * @param string $result
     * @return string
     */
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
