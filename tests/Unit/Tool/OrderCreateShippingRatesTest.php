<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\OrderTools;
use Magento\Catalog\Api\Data\ProductInterface;
use PHPUnit\Framework\TestCase;

/**
 * Regression: order-create must let collectTotals() collect the shipping rates.
 *
 * Calling collectShippingRates() on the address directly rates the quote's raw item
 * list, because the address item set is only assembled later, inside collectTotals().
 * That rated bundle children and virtual items as separate shippable units and froze
 * the result, overcharging shipping on every order with a bundle, configurable or
 * downloadable line (e.g. $30 instead of $5 for a bundle plus a downloadable).
 */
class OrderCreateShippingRatesTest extends TestCase
{
    protected function tearDown(): void
    {
        MagentoBootstrap::reset();
    }

    public function testCreateOrderLeavesRateCollectionToCollectTotals(): void
    {
        $calls = new \ArrayObject();
        $this->bootstrapWithFakes($calls);

        $result = (new OrderTools())->createOrder(
            customerEmail: 'guest@example.com',
            items: [['sku' => 'SIMPLE-1', 'qty' => 1]],
            firstname: 'Test',
            lastname: 'Guest',
            street: '1 Test Street',
            city: 'Calder',
            postcode: '49628',
            countryId: 'US',
            telephone: '(555) 000-0000',
            regionId: 33
        );

        self::assertTrue(
            $result['success'] ?? false,
            'order-create should succeed against the fakes; got: ' . json_encode($result)
        );

        self::assertNotContains(
            'collectShippingRates',
            (array) $calls,
            'order-create must not collect shipping rates itself: at this point the address item '
            . 'set does not exist yet, so bundle children and virtual items get rated as shippable '
            . 'units and the wrong rate is frozen onto the address.'
        );

        $order = (array) $calls;
        self::assertSame(
            ['setCollectShippingRates', 'setShippingMethod', 'collectTotals'],
            $order,
            'the address should only be flagged for rate collection and given a method, '
            . 'leaving collectTotals() to rate the assembled address items'
        );
    }

    /**
     * Point MagentoBootstrap at fakes that record how the shipping address is driven.
     */
    private function bootstrapWithFakes(\ArrayObject $calls): void
    {
        $shippingAddress = new class ($calls) {
            public function __construct(private \ArrayObject $calls)
            {
            }

            public function addData(array $data): self
            {
                return $this;
            }

            public function setCollectShippingRates(bool $flag): self
            {
                $this->calls[] = 'setCollectShippingRates';
                return $this;
            }

            public function collectShippingRates(): self
            {
                $this->calls[] = 'collectShippingRates';
                return $this;
            }

            public function setShippingMethod(string $method): self
            {
                $this->calls[] = 'setShippingMethod';
                return $this;
            }
        };

        $quote = new class ($calls, $shippingAddress) {
            public function __construct(private \ArrayObject $calls, private object $shippingAddress)
            {
            }

            public function setStore(object $store): self
            {
                return $this;
            }

            public function setCustomerEmail(string $email): self
            {
                return $this;
            }

            public function setCustomerIsGuest(bool $isGuest): self
            {
                return $this;
            }

            public function setCustomerGroupId(int $groupId): self
            {
                return $this;
            }

            public function addProduct(object $product, object $request): object
            {
                return new \stdClass();
            }

            public function getBillingAddress(): object
            {
                return new class {
                    public function addData(array $data): self
                    {
                        return $this;
                    }
                };
            }

            public function isVirtual(): bool
            {
                return false;
            }

            public function getShippingAddress(): object
            {
                return $this->shippingAddress;
            }

            public function getPayment(): object
            {
                return new class {
                    public function setMethod(string $method): self
                    {
                        return $this;
                    }
                };
            }

            public function collectTotals(): self
            {
                $this->calls[] = 'collectTotals';
                return $this;
            }

            public function getId(): int
            {
                return 99;
            }
        };

        $product = $this->createMock(ProductInterface::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getSku')->willReturn('SIMPLE-1');

        $order = new class {
            public function getIncrementId(): string
            {
                return '000000099';
            }

            public function getStatus(): string
            {
                return 'pending';
            }

            public function getGrandTotal(): float
            {
                return 42.0;
            }
        };

        $objectManager = new class ($quote, $product, $order) {
            public function __construct(private object $quote, private object $product, private object $order)
            {
            }

            public function get(string $className): object
            {
                return match ($className) {
                    \Magento\Framework\App\State::class => new class {
                        public function getMode(): string
                        {
                            return \Magento\Framework\App\State::MODE_DEVELOPER;
                        }
                    },
                    \Magento\Store\Model\StoreManagerInterface::class => new class {
                        public function getDefaultStoreView(): object
                        {
                            return new class {
                                public function getId(): int
                                {
                                    return 1;
                                }
                            };
                        }

                        public function getStore(int $id = 0): object
                        {
                            return $this->getDefaultStoreView();
                        }
                    },
                    \Magento\Quote\Model\QuoteFactory::class => new class ($this->quote) {
                        public function __construct(private object $quote)
                        {
                        }

                        public function create(): object
                        {
                            return $this->quote;
                        }
                    },
                    \Magento\Catalog\Api\ProductRepositoryInterface::class => new class ($this->product) {
                        public function __construct(private object $product)
                        {
                        }

                        public function get(string $sku, bool $edit = false, ?int $storeId = null): object
                        {
                            return $this->product;
                        }
                    },
                    \Magento\Quote\Api\CartRepositoryInterface::class => new class {
                        public function save(object $quote): void
                        {
                        }
                    },
                    \Magento\Quote\Api\CartManagementInterface::class => new class {
                        public function placeOrder(int $cartId): int
                        {
                            return 99;
                        }
                    },
                    \Magento\Sales\Api\OrderRepositoryInterface::class => new class ($this->order) {
                        public function __construct(private object $order)
                        {
                        }

                        public function get(int $id): object
                        {
                            return $this->order;
                        }
                    },
                    default => throw new \RuntimeException("Unexpected get(): $className"),
                };
            }

            /**
             * @param array<string, mixed> $arguments
             */
            public function create(string $className, array $arguments = []): object
            {
                return new \Magento\Framework\DataObject($arguments['data'] ?? []);
            }
        };

        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $ref->getProperty('objectManager');
        $prop->setAccessible(true);
        $prop->setValue(null, $objectManager);
    }
}
