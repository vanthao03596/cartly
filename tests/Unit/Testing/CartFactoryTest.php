<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Testing;

use Cart\CartInstance;
use Cart\Conditions\DiscountCondition;
use Cart\Conditions\TaxCondition;
use Cart\Testing\CartFactory;
use Cart\Tests\TestCase;

class CartFactoryTest extends TestCase
{
    public function test_factory_creates_cart_instance(): void
    {
        $factory = new CartFactory();
        $cart = $factory->create();

        $this->assertInstanceOf(CartInstance::class, $cart);
    }

    public function test_factory_creates_empty_cart_by_default(): void
    {
        $factory = new CartFactory();
        $cart = $factory->create();

        $this->assertTrue($cart->isEmpty());
        $this->assertSame(0, $cart->count());
    }

    public function test_with_items_adds_items_to_cart(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 2, 'price' => 1000],
                ['id' => 2, 'quantity' => 1, 'price' => 2000],
            ])
            ->create();

        $this->assertSame(3, $cart->count()); // 2 + 1
        $this->assertSame(2, $cart->countItems()); // 2 unique items
    }

    public function test_with_items_sets_correct_prices(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 2, 'price' => 1000],
                ['id' => 2, 'quantity' => 1, 'price' => 2000],
            ])
            ->create();

        // 2 * 1000 + 1 * 2000 = 4000 cents
        $this->assertSame(4000, $cart->subtotal());
        $this->assertSame(4000, $cart->total());
    }

    public function test_with_item_adds_single_item(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItem(id: 42, quantity: 3, price: 1500)
            ->create();

        $this->assertSame(3, $cart->count());
        $this->assertSame(4500, $cart->subtotal()); // 3 * 1500
    }

    public function test_with_item_chaining_adds_multiple_items(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItem(id: 1, quantity: 1, price: 1000)
            ->withItem(id: 2, quantity: 2, price: 500)
            ->withItem(id: 3, quantity: 1, price: 2000)
            ->create();

        $this->assertSame(4, $cart->count()); // 1 + 2 + 1
        $this->assertSame(3, $cart->countItems());
        $this->assertSame(4000, $cart->subtotal()); // 1000 + 1000 + 2000
    }

    public function test_with_items_supports_original_price(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 1, 'price' => 800, 'originalPrice' => 1000],
            ])
            ->create();

        $item = $cart->find(1);
        $this->assertNotNull($item);
        $this->assertSame(800, $item->unitPrice());
        $this->assertSame(1000, $item->originalUnitPrice());
        $this->assertSame(200, $item->savings()); // 1000 - 800
    }

    public function test_with_items_supports_options(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 1, 'price' => 1000, 'options' => ['size' => 'L', 'color' => 'red']],
            ])
            ->create();

        $item = $cart->find(1);
        $this->assertNotNull($item);
        $this->assertSame('L', $item->options->get('size'));
        $this->assertSame('red', $item->options->get('color'));
    }

    public function test_with_items_supports_meta(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 1, 'price' => 1000, 'meta' => ['gift_wrap' => true]],
            ])
            ->create();

        $item = $cart->find(1);
        $this->assertNotNull($item);
        $this->assertTrue($item->meta->get('gift_wrap'));
    }

    public function test_with_condition_adds_condition_to_cart(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 1, 'price' => 1000],
            ])
            ->withCondition(new TaxCondition('VAT', 10))
            ->create();

        $this->assertTrue($cart->hasCondition('VAT'));
        $this->assertSame(100, $cart->taxTotal()); // 10% of 1000
        $this->assertSame(1100, $cart->total()); // 1000 + 100
    }

    public function test_with_conditions_adds_multiple_conditions(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 1, 'price' => 1000],
            ])
            ->withConditions([
                new DiscountCondition('Sale', 10, 'percentage'),
                new TaxCondition('VAT', 10),
            ])
            ->create();

        $this->assertTrue($cart->hasCondition('Sale'));
        $this->assertTrue($cart->hasCondition('VAT'));
    }

    public function test_factory_is_immutable(): void
    {
        $factory = new CartFactory();
        $factory1 = $factory->withItem(1, 1, 1000);
        $factory2 = $factory->withItem(2, 1, 2000);

        $cart1 = $factory1->create();
        $cart2 = $factory2->create();

        $this->assertSame(1, $cart1->countItems());
        $this->assertSame(1, $cart2->countItems());
        $this->assertNotNull($cart1->find(1));
        $this->assertNull($cart1->find(2));
        $this->assertNull($cart2->find(1));
        $this->assertNotNull($cart2->find(2));
    }

    public function test_instance_sets_cart_instance_name(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->instance('wishlist')
            ->withItem(1, 1, 1000)
            ->create();

        $this->assertSame('wishlist', $cart->getInstanceName());
    }

    public function test_complex_cart_scenario(): void
    {
        $factory = new CartFactory();
        $cart = $factory
            ->withItems([
                ['id' => 1, 'quantity' => 2, 'price' => 1000, 'originalPrice' => 1200],
                ['id' => 2, 'quantity' => 1, 'price' => 2500],
            ])
            ->withCondition(new DiscountCondition('PROMO10', 10, 'percentage'))
            ->withCondition(new TaxCondition('VAT', 20))
            ->create();

        // Subtotal: 2*1000 + 1*2500 = 4500
        $this->assertSame(4500, $cart->subtotal());

        // After 10% discount: 4500 - 450 = 4050
        // After 20% tax: 4050 + 810 = 4860
        $this->assertSame(4860, $cart->total());

        // Savings from original prices: (2*1200 + 2500) - 4500 = 5900 - 4500 = 400
        // Note: item 2 has no original price different, so savings = 2*(1200-1000) = 400
        $this->assertSame(400, $cart->savings());
    }
}
