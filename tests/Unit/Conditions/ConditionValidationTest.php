<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Conditions;

use Cart\CartContext;
use Cart\CartInstance;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Conditions\DiscountCondition;
use Cart\Contracts\PriceResolver;
use Cart\Drivers\ArrayDriver;
use Cart\Events\CartConditionInvalidated;
use Cart\ResolvedPrice;
use Cart\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

class ConditionValidationTest extends TestCase
{
    private ArrayDriver $driver;

    private PriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new ArrayDriver;
        $this->resolver = $this->createFakeResolver(1000);
    }

    public function test_it_returns_true_for_valid_condition(): void
    {
        $cart = $this->createCart();
        $cart->add(1, 5); // 5 * 1000 = 5000 cents

        // Min order 3000 cents, subtotal is 5000 - should be valid
        $discount = new DiscountCondition('SALE', 10, 'percentage', 'subtotal', null, 3000);

        $this->assertTrue($discount->isValid($cart));
        $this->assertNull($discount->getValidationError());
    }

    public function test_it_returns_false_for_invalid_condition(): void
    {
        $cart = $this->createCart();
        $cart->add(1, 2); // 2 * 1000 = 2000 cents

        // Min order 5000 cents, subtotal is 2000 - should be invalid
        $discount = new DiscountCondition('SALE', 10, 'percentage', 'subtotal', null, 5000);

        $this->assertFalse($discount->isValid($cart));
        $this->assertNotNull($discount->getValidationError());
        $this->assertStringContainsString('requires minimum order of 5000 cents', $discount->getValidationError());
        $this->assertStringContainsString('current subtotal is 2000 cents', $discount->getValidationError());
    }

    public function test_it_is_valid_when_subtotal_equals_min_order(): void
    {
        $cart = $this->createCart();
        $cart->add(1, 5); // 5 * 1000 = 5000 cents

        // Min order 5000 cents, subtotal is exactly 5000 - should be valid (boundary case)
        $discount = new DiscountCondition('SALE', 10, 'percentage', 'subtotal', null, 5000);

        $this->assertTrue($discount->isValid($cart));
        $this->assertNull($discount->getValidationError());
    }

    public function test_it_is_valid_without_cart(): void
    {
        $discount = new DiscountCondition('SALE', 10, 'percentage', 'subtotal', null, 5000);

        // Without cart context, should return true (can't validate)
        $this->assertTrue($discount->isValid(null));
        $this->assertNull($discount->getValidationError());
    }

    public function test_it_is_valid_without_minimum_order(): void
    {
        $cart = $this->createCart();
        $cart->add(1, 1); // 1000 cents

        // No minimum order requirement
        $discount = new DiscountCondition('SALE', 10, 'percentage', 'subtotal');

        $this->assertTrue($discount->isValid($cart));
        $this->assertNull($discount->getValidationError());
    }

    public function test_it_auto_removes_invalid_conditions_on_load(): void
    {
        Config::set('cart.conditions.auto_remove_invalid', true);

        // Create cart and add items with a discount that requires min order
        $cart = $this->createCart();
        $cart->add(1, 5); // 5000 cents
        $cart->condition(new DiscountCondition('BIG_SALE', 10, 'percentage', 'subtotal', null, 3000));
        $this->assertTrue($cart->hasCondition('BIG_SALE'));

        // Simulate removing an item so subtotal drops below min order
        $items = $cart->content();
        $rowId = $items->first()->rowId;
        $cart->remove($rowId);
        $cart->add(1, 1); // 1000 cents now

        // Create a new cart instance to trigger reload and validation
        $newCart = new CartInstance('default', $this->driver, $this->resolver);
        $newCart->setEventsEnabled(false);

        // The condition should be auto-removed because subtotal (1000) < minOrder (3000)
        $this->assertFalse($newCart->hasCondition('BIG_SALE'));
    }

    public function test_it_respects_auto_remove_config_when_disabled(): void
    {
        Config::set('cart.conditions.auto_remove_invalid', false);

        // Create cart with low subtotal and high min order condition
        $cart = $this->createCart();
        $cart->add(1, 1); // 1000 cents
        $cart->condition(new DiscountCondition('SALE', 10, 'percentage', 'subtotal', null, 5000));
        $this->assertTrue($cart->hasCondition('SALE'));

        // Create a new cart instance - condition should still be there (auto-remove disabled)
        $newCart = new CartInstance('default', $this->driver, $this->resolver);
        $newCart->setEventsEnabled(false);

        $this->assertTrue($newCart->hasCondition('SALE'));
    }

    public function test_it_dispatches_event_with_correct_data(): void
    {
        Config::set('cart.conditions.auto_remove_invalid', true);
        Event::fake([CartConditionInvalidated::class]);

        // Create cart with invalid condition
        $cart = $this->createCart();
        $cart->setEventsEnabled(true);
        $cart->add(1, 1); // 1000 cents
        $cart->condition(new DiscountCondition('EXPIRED', 10, 'percentage', 'subtotal', null, 5000));

        // Create a new cart instance to trigger validation
        $newCart = new CartInstance('default', $this->driver, $this->resolver);
        $newCart->setEventsEnabled(true);

        // Trigger content load which validates conditions
        $newCart->content();

        Event::assertDispatched(CartConditionInvalidated::class, function ($event) {
            return $event->instance === 'default'
                && $event->condition->getName() === 'EXPIRED'
                && $event->reason !== null
                && str_contains($event->reason, 'requires minimum order');
        });
    }

    public function test_it_keeps_valid_conditions(): void
    {
        Config::set('cart.conditions.auto_remove_invalid', true);

        $cart = $this->createCart();
        $cart->add(1, 10); // 10000 cents

        // Add two conditions - one valid, one invalid
        $cart->condition(new DiscountCondition('VALID', 5, 'percentage', 'subtotal', null, 5000)); // min 5000, valid
        $cart->condition(new DiscountCondition('INVALID', 10, 'percentage', 'subtotal', null, 20000)); // min 20000, invalid

        // Create new cart to trigger validation
        $newCart = new CartInstance('default', $this->driver, $this->resolver);
        $newCart->setEventsEnabled(false);

        $this->assertTrue($newCart->hasCondition('VALID'));
        $this->assertFalse($newCart->hasCondition('INVALID'));
    }

    public function test_discount_without_constraints_is_always_valid(): void
    {
        $cart = $this->createCart();
        $cart->add(1, 1);

        // DiscountCondition without minOrderAmount - should always be valid
        $discount = new DiscountCondition('SIMPLE', 10, 'percentage');

        $this->assertTrue($discount->isValid($cart));
        $this->assertNull($discount->getValidationError());
    }

    private function createCart(): CartInstance
    {
        $cart = new CartInstance('default', $this->driver, $this->resolver);
        $cart->setEventsEnabled(false);

        return $cart;
    }

    private function createFakeResolver(int $price): PriceResolver
    {
        return new class($price) implements PriceResolver
        {
            public function __construct(private int $price) {}

            public function resolve(CartItem $item, CartContext $context): ResolvedPrice
            {
                return new ResolvedPrice($this->price, $this->price);
            }

            public function resolveMany(CartItemCollection $items, CartContext $context): array
            {
                $results = [];
                foreach ($items as $item) {
                    $results[$item->rowId] = new ResolvedPrice($this->price, $this->price);
                }

                return $results;
            }
        };
    }
}
