---
name: hyva-checkout
description: Design, build, migrate, and review Hyva Checkout integrations in Magento 2. Use for checkout architecture, primary navigation, Evaluation API gates, Place Order Services, frontend payment methods, payment and shipping integrations, layout-independent components, Magewire version selection, and checkout extension decisions.
---

# Hyva Checkout Architecture and Development

Load this skill before changing checkout behavior. Hyva Checkout combines Magento conventions with
Magewire, Alpine.js, and Tailwind CSS, but those frontend technologies are defaults rather than a
requirement that every customization must use them.

## Load the Focused Resource

| Task | Resource |
|---|---|
| Components, lifecycle, events, and Magewire generation | `magento://skills/hyva-checkout-magewire` |
| Checkout definitions, steps, conditions, handles, and component placement | `magento://skills/hyva-checkout-configuration` |
| Evaluation results, forms, validators, and frontend APIs | `magento://skills/hyva-checkout-apis` |
| Native Magewire 3 component behavior | `magento://skills/magewire-three` |
| Magewire V1 component behavior | `magento://skills/magewire` |

## Mandatory Compatibility Gate

Resolve the installed Hyva Checkout version before producing component PHP, `wire:` directives,
Magewire events, lifecycle hooks, or JavaScript. Do not choose a Magewire API from memory or from
the first example found in this skill.

Inspect, in order:

1. `composer show hyva-themes/magento2-hyva-checkout --format=json` when dependencies are installed.
2. The exact locked package version in `composer.lock`.
3. The installed checkout `composer.json` requirement and resolved `magewirephp/magewire` package
   when the checkout version is a development branch, alias, or otherwise not semver-classifiable.
4. The installed Magewire source as final confirmation when package metadata conflicts.

Select exactly one working mode:

| Hyva Checkout | Working mode | Required component resource |
|---|---|---|
| `>=1.0 <1.4` | Magewire V1 | `magento://skills/magewire` |
| `>=1.4` new component | Native Magewire 3 | `magento://skills/magewire-three` |
| `>=1.4` existing V1 component | V1-on-V3 migration | `magento://skills/magewire-three/backwards-compatibility` |

Magewire V2 never existed. Do not describe V1 as unsupported: Hyva Checkout 1.0-1.3.* uses V1.
Hyva Checkout 1.4 and newer uses Magewire 3. On 1.4+, backwards compatibility is valid only for
existing V1 components being preserved or migrated; it is not the build target for new components.

Before writing code, establish a compatibility profile containing the resolved checkout version,
the selected working mode, the Magewire package version, and whether checkout subtree backwards
compatibility applies. If the version cannot be resolved, inspect further or ask; never blend V1
and V3 syntax as a fallback.

The enhanced Frontend Payment API centered on `hyvaCheckout.payment.registerMethod()` was
introduced in the 1.3.6 line. For older installations, verify whether the separately installed
`hyva-themes/magento2-hyva-checkout-frontend-api` backport supplies the required API. Treat the
installed PHP and PHTML/JavaScript as final authority for signatures and available result types.

## The Three Architectural Pillars

### 1. Evaluation API

Use the Evaluation API when a Magewire component must describe whether checkout may proceed or must
instruct the frontend to validate, notify, execute, or redirect.

A component implements `EvaluationInterface` and returns an `EvaluationResultInterface`. The
result carries a positive or negative `result` flag plus typed frontend instructions; it is not
merely a boolean return value.

```php
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;

class DeliveryChoice extends \Magewirephp\Magewire\Component implements EvaluationInterface
{
    public string $choice = '';

    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory
    ): EvaluationResultInterface {
        if ($this->choice !== '') {
            return $resultFactory->createSuccess();
        }

        return $resultFactory->createErrorMessage()
            ->withMessage('Choose a delivery option to continue.')
            ->asWarning();
    }
}
```

Evaluation runs on the server during component evaluation. By default, failure instructions bind
to checkout validation and become visible when the customer uses the primary navigation button.
Call a result's `dispatch()` capability only when it must execute immediately after the Magewire
response; document why it should bypass the normal navigation trigger.

Evaluation rules:

- Keep evaluation deterministic and fast because it can run after every component interaction.
- Return success when the component is satisfied; return a descriptive result when it is not.
- Prefer a visible error, validation, or dialog over the deprecated silent blocking result.
- Keep authoritative validation on the server. Use a frontend validator only for browser/PSP work.
- Do not duplicate server rules in JavaScript as a second source of truth.

### 2. Place Order Service

The primary Place Order action owns order placement. A payment method selects how the customer
wants to pay; it must not convert the quote to an order during selection or from its component.

The server flow is:

```text
primary Place Order button
  -> navigation and validation pipeline
  -> Hyva\Checkout\Magewire\Main::placeOrder()
  -> PlaceOrderServiceProcessor
  -> service selected by payment method code
  -> custom service or DefaultPlaceOrderService
  -> order creation
  -> evaluation results and redirect handling
```

Create a custom `AbstractPlaceOrderService` only when the method needs payment-specific order data,
exception handling, redirect control, or post-order Evaluation results such as 3DS. Register it in
`PlaceOrderServiceProvider::placeOrderServiceList` using the exact payment method code.

Never convert the quote inside a payment component or PSP callback. Payment may appear before later
steps, and converting early removes quote state those steps still require.

### 3. Steps and Primary Navigation

Checkout layout is merchant-configurable. Payment need not be the last step, and a checkout may be
multi-step, one-page, or place other steps after payment.

Hyva Checkout determines whether the primary action is Next Step or Place Order. Both actions own a
shared pipeline of navigation tasks, validations, and Evaluation results. Components participate in
that pipeline; they do not replace it.

- Do not add a second Place Order button in a payment template.
- Do not advance steps by bypassing the primary navigation flow.
- Do not assume the payment component is mounted or visible when Place Order runs.
- Put overlays, hosted forms, and 3DS modals outside step-scoped payment markup.
- Validate every available checkout shape supported by the integration.

## Choose a Payment Integration Strategy

| Need | Primary mechanism |
|---|---|
| Display instructions only | Payment layout/template; default Place Order Service |
| Server-controlled order creation or redirect | Custom `AbstractPlaceOrderService` |
| Browser SDK, wallet, iframe, or hosted interaction | Frontend Payment API |
| Server order flow plus browser preparation/handling | Frontend method with POS fallback |
| Gate navigation on payment state | Payment Magewire component plus Evaluation API |

The Frontend Payment API registers one object by exact Magento payment method code. Missing methods
receive defaults. Override only what the integration needs, and use the supplied `fallback`
function to preserve the standard process before or after custom logic.

```javascript
hyvaCheckout.api.after(() => {
    hyvaCheckout.payment.registerMethod({
        code: 'vendor_gateway',
        method: {
            async placeOrder({ fallback }) {
                await this.prepareProvider();
                return fallback();
            },

            async handleException({ exception, fallback }) {
                this.reportProviderError(exception);
                return fallback({ exception });
            }
        }
    });
});
```

An iframe, redirect, or payment overlay should become actionable from the Place Order flow, not
when the customer merely selects the method. Selection can happen before other checkout work.

### Frontend API Backport Policy

Payment modules targeting older checkout releases may document the Frontend API backport as an
installation prerequisite. Do not require the backport package directly from the payment module's
`composer.json`: installations that already provide the API should not be forced onto it. The
Magento installation owner selects and installs the compatible backport release.

## Build a Checkout Component

1. Decide whether the behavior needs Magewire. Static display, layout, ViewModels, and Alpine-only
   behavior do not require a server component.
2. Declare reusable component blocks in `hyva_checkout_components.xml` and move them into step
   handles. Do not hard-code one step location into component behavior.
3. Keep quote/order operations in injected services. Treat public component state and methods as
   untrusted request input.
4. Implement `EvaluationInterface` only when the component participates in checkout progression.
5. Communicate through version-correct events rather than finding and mutating other components.
6. Test the component on every supported checkout layout and navigation path.

### Magewire 3-Native Components

For Hyva Checkout 1.4+, new components should opt out of V1 compatibility explicitly while the
checkout subtree still supports migrated components:

```php
use Magewirephp\Magewire\Attributes\On;
use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;

#[HandleBackwardsCompatibility(enabled: false)]
class DeliveryChoice extends \Magewirephp\Magewire\Component
{
    #[On('shipping-address-saved')]
    public function refreshOptions(): void
    {
    }

    public function choose(string $code): void
    {
        $this->dispatch('delivery-choice-updated', code: $code);
    }
}
```

Use `$this->dispatch()` and `#[On]` in V3-native code. Events bubble, so do not invent
`dispatch()->up()`. Keep V1 `emit*()` and `$listeners` only in components intentionally running
through backwards compatibility.

Use `mount()` for first-render initialization only when the component is present during the
preceding page render. Checkout step changes can introduce or reconstruct components during update
requests. Put state required every active request in `boot()`/`booted()`. Where the installed
checkout already uses the component store to distinguish a first appearance, follow its pattern:

```php
use function Magewirephp\Magewire\store;

$isUpdate = (bool) store($this)->get('magewire:update', false);
```

Do not use the store flag as a generic replacement for lifecycle hooks; verify the installed
checkout resolver and neighboring components first.

## Shipping Integrations

Magento shipping rates and method selection remain the source of truth. A custom checkout view is
needed only for carrier-specific input or interaction.

- Name the layout alias `carrierCode_methodCode`.
- Recalculate options when the authoritative address/method event changes.
- Persist extra data through a service and validate it server-side.
- Use Evaluation API results when required carrier data must block progression.
- Keep the carrier view independent of one checkout step or column layout.

## Review Against These Anti-Patterns

| Anti-pattern | Correct architecture |
|---|---|
| Payment component has Pay Now or calls quote-to-order | Primary Place Order action plus POS/frontend payment API |
| Payment assumes it is the final step | Work with payment before, within, or after other steps |
| Modal lives only inside selected payment markup | Render durable UI outside the step-scoped Main subtree |
| Component programmatically advances checkout | Participate through Evaluation and navigation tasks |
| Failure is duplicated in PHP and JavaScript | Server result plus named frontend validator when needed |
| New V3 component calls `emit()` | `dispatch()` plus `#[On]` |
| Component reaches into another via `Magewire.find()` | Version-correct component event |
| Integration requires the frontend backport package | Document installation compatibility instead |
| Expensive API call runs in `evaluateCompletion()` | Cache/prepare state elsewhere; evaluate cheaply |
| Silent `createBlocking()` result | Visible error, validation, dialog, or task result |

## Verification Checklist

- Installed checkout, Magewire generation, and Frontend API capabilities are identified.
- Primary navigation remains the only progression/order-placement entry point.
- Payment behavior works when payment is not the final or currently visible step.
- Server order placement is owned by the selected Place Order Service.
- Frontend method overrides preserve the fallback path deliberately.
- Evaluation failures explain what the customer must do and dispatch only when justified.
- Component actions authorize, validate, and re-fetch authoritative quote/customer data.
- Magewire events and lifecycle hooks match the installed generation.
- CSP, loading, error, retry, double-submit, and redirect behavior are browser-tested.

## Source Authority

Prefer, in order:

1. Installed Hyva Checkout PHP, DI, layout, PHTML, and JavaScript.
2. Installed Magewire and checkout compatibility packages.
3. Current official Hyva Checkout documentation.
4. Examples from other checkout versions only after verifying their API line.

Official references:

- <https://docs.hyva.io/hyva-checkout/devdocs/evaluation-api/index.html>
- <https://docs.hyva.io/hyva-checkout/devdocs/place-order-service-api/index.html>
- <https://docs.hyva.io/hyva-checkout/devdocs/place-order-service-api/abstraction-layer.html>
- <https://docs.hyva.io/hyva-checkout/devdocs/place-order-service-api/service-processor.html>
- <https://docs.hyva.io/hyva-checkout/devdocs/payments/payment-integration-api.html>
- <https://docs.hyva.io/hyva-checkout/devdocs/frontend-api/V1/payment.html>
- <https://docs.hyva.io/hyva-checkout/devdocs/frontend-api/backport.html>
