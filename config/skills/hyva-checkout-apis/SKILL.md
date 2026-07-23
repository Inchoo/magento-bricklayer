---
name: hyva-checkout-apis
description: Build and review Hyva Checkout Evaluation API results, frontend validators, forms, navigation tasks, messages, and frontend payment hooks while preserving the primary checkout flow.
---

# Hyvä Checkout: Evaluation, Form & Frontend APIs

> Related: Start with [hyva-checkout](../hyva-checkout/SKILL.md) for checkout architecture. See [hyva-checkout-magewire](../hyva-checkout-magewire/SKILL.md) for component patterns, and [hyva-checkout-configuration](../hyva-checkout-configuration/SKILL.md) for checkout XML and layout config.

Resolve the core skill's compatibility profile first. The checkout concepts in this resource span
versions, but component lifecycle, event syntax, directives, and frontend availability must follow
the selected V1, native V3, or migration track.

## Evaluation API

The Evaluation API lets a server component report whether checkout may proceed and attach typed
frontend instructions. By default, results are bound to the primary navigation action and are
processed when the customer clicks Next or Place Order. A dispatched result executes immediately
after the current Magewire request instead.

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
| Blocking | `createBlocking()` | **Deprecated:** silently obstructs primary navigation; do not use in new code |
| ErrorMessage | `createErrorMessage()` | Show flash message |
| Event | `createEvent('name')` | Dispatch custom window event |
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
    ->push($resultFactory->createEvent('my-event')->dispatch());
```

### Bound Versus Dispatched Results

Return a normal, bound result for validation that should surface through the primary navigation
pipeline. Call `dispatch()` only when the instruction must run as part of the current Magewire
response, independent of a Next or Place Order click. An immediate success notification after a
user action is a reasonable dispatch; silently advancing checkout is not.

Keep `evaluateCompletion()` deterministic and inexpensive. Prepare remote payment state before
evaluation, then evaluate the stored result rather than calling a PSP on every interaction.

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
                ->createSuccess()
                ->withCustomEvent('payment:method:success')
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
hyvaCheckout.api.after(() => {
    // Return true to pass and false to execute the PHP failure result.
    hyvaCheckout.evaluation.registerValidator(
        'validateMyField',
        (component, element, evaluation) => {
            const input = element.querySelector('#my-field');
            return Boolean(input?.value);
        }
    );

    // Async validators resolve to a boolean too.
    hyvaCheckout.evaluation.registerValidator(
        'my-payment-3ds',
        (component, element, evaluation) => new Promise((resolve) => {
            window.dispatchEvent(new Event('my-payment:3ds:show'));

            window.addEventListener('my-payment:3ds:result', (event) => {
                resolve(Boolean(event.detail.success));
            }, { once: true });
        })
    );
});
```

### Navigation API

Use navigation calls only for an explicit navigation task or other checkout-owned flow. A custom
component must not call these methods to replace the primary Next/Place Order pipeline.

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

### Frontend Payment API

For checkout 1.3.6+ or a compatible Frontend API backport, register browser-driven payment logic
under the exact Magento payment method code. Override only the hooks the integration needs. The
provided `fallback` function preserves the standard Place Order Service flow.

```javascript
hyvaCheckout.api.after(() => {
    hyvaCheckout.payment.registerMethod({
        code: 'vendor_gateway',
        method: {
            async placeOrder({ fallback }) {
                await this.prepareProvider();
                return fallback();
            }
        }
    });
});
```

Do not trigger the provider UI when the method is merely selected. Do not require the backport
package from a payment module; document it as an installation prerequisite when needed. See the
core architecture resource for Place Order Service and fallback decisions.

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

        $this->setAttribute('wire:submit.prevent', 'submit');
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
                            <li><?= /** @noEscape */ $magewire->getError($element->getTracePath()) ?></li>
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
use Hyva\Checkout\Model\Form\EntityFormModifierInterface;

class AddLoyaltyFieldModifier implements EntityFormModifierInterface
{
    /**
     * @param EntityFormInterface $form
     * @return EntityFormInterface
     */
    public function apply(EntityFormInterface $form): EntityFormInterface
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

### Version-Aware Component Coordination

Checkout event names and payloads are contracts, but the Magewire event API depends on the
installed generation. V1 components use `$listeners` and `emit*()`. New V3-native components use
`#[On]` and `dispatch()`, and should opt out of backwards compatibility explicitly. Do not combine
both styles in one example or silently translate an installed checkout event name.

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

## Best Practices

1. Use `EvaluationInterface` only when a component participates in primary navigation or Place Order readiness.
2. Return bound results for normal checkout gates; dispatch only instructions that must run immediately.
3. Register frontend APIs through `hyvaCheckout.api.after()` and match the installed API signatures.
4. Resolve frontend validators to `true` or `false`; keep authoritative business validation in PHP.
5. Use version-correct Magewire events: V1 `emit*()` or V3 `dispatch()` and `#[On]`.
6. Use `wire:loading` and the installed checkout loading APIs instead of assuming `$loader` exists.
7. Restore required per-request state in `boot()` or `booted()`; use `mount()` only for reliable first renders.
8. Declare reusable components in `hyva_checkout_components.xml` and move them into step layouts.
9. Match payment aliases to the method code and shipping aliases to `carrier_method`.
10. Use `AbstractPlaceOrderService` for custom server order placement; never create the order in a component.
11. Use form modifiers to extend third-party forms instead of replacing their form classes.
12. Keep components focused and validate all public method arguments against authoritative quote state.
13. Escape template output with the context-appropriate `$escaper` method.
14. Use stable `wire:key` values on repeated elements.
