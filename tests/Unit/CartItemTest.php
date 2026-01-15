<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\CartItem;
use Cart\ResolvedPrice;
use PHPUnit\Framework\TestCase;

class CartItemTest extends TestCase
{
    public function test_it_creates_cart_item(): void
    {
        $item = new CartItem(
            id: 1,
            quantity: 2,
            options: ['size' => 'L', 'color' => 'red'],
        );

        $this->assertSame(1, $item->id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('L', $item->options->get('size'));
        $this->assertSame('red', $item->options->get('color'));
        $this->assertNotEmpty($item->rowId);
    }

    public function test_it_generates_unique_row_id_for_different_options(): void
    {
        $item1 = new CartItem(id: 1, options: ['size' => 'L']);
        $item2 = new CartItem(id: 1, options: ['size' => 'M']);
        $item3 = new CartItem(id: 1, options: ['size' => 'L']);

        $this->assertNotSame($item1->rowId, $item2->rowId);
        $this->assertSame($item1->rowId, $item3->rowId);
    }

    public function test_it_generates_same_row_id_regardless_of_option_order(): void
    {
        $item1 = new CartItem(id: 1, options: ['size' => 'L', 'color' => 'red']);
        $item2 = new CartItem(id: 1, options: ['color' => 'red', 'size' => 'L']);

        $this->assertSame($item1->rowId, $item2->rowId);
    }

    public function test_it_calculates_subtotal_when_price_is_resolved(): void
    {
        $item = new CartItem(id: 1, quantity: 3);
        $item->setResolvedPrice(new ResolvedPrice(
            unitPrice: 1000,
            originalPrice: 1200,
        ));

        $this->assertSame(1000, $item->unitPrice());
        $this->assertSame(3000, $item->subtotal());
    }

    public function test_it_calculates_savings(): void
    {
        $item = new CartItem(id: 1, quantity: 2);
        $item->setResolvedPrice(new ResolvedPrice(
            unitPrice: 800,
            originalPrice: 1000,
        ));

        // savings = (1000 * 2) - (800 * 2) = 400
        $this->assertSame(400, $item->savings());
    }

    public function test_it_updates_quantity(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $item->setQuantity(5);

        $this->assertSame(5, $item->quantity);
    }

    public function test_it_serializes_to_array(): void
    {
        $item = new CartItem(
            id: 1,
            quantity: 2,
            options: ['size' => 'L'],
            buyableType: 'App\\Models\\Product',
            buyableId: 1,
            meta: ['note' => 'Gift wrap'],
        );

        $array = $item->toArray();

        $this->assertSame(1, $array['id']);
        $this->assertSame(2, $array['quantity']);
        $this->assertSame(['size' => 'L'], $array['options']);
        $this->assertSame('App\\Models\\Product', $array['buyableType']);
        $this->assertSame(1, $array['buyableId']);
        $this->assertSame(['note' => 'Gift wrap'], $array['meta']);
    }

    public function test_it_deserializes_from_array(): void
    {
        $data = [
            'id' => 1,
            'quantity' => 2,
            'options' => ['size' => 'L'],
            'buyableType' => 'App\\Models\\Product',
            'buyableId' => 1,
            'meta' => ['note' => 'Gift wrap'],
        ];

        $item = CartItem::fromArray($data);

        $this->assertSame(1, $item->id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('L', $item->options->get('size'));
    }

    public function test_it_tracks_price_resolution_state(): void
    {
        $item = new CartItem(id: 1);

        $this->assertFalse($item->hasPriceResolved());

        $item->setResolvedPrice(new ResolvedPrice(unitPrice: 1000, originalPrice: 1000));

        $this->assertTrue($item->hasPriceResolved());

        $item->clearResolvedPrice();

        $this->assertFalse($item->hasPriceResolved());
    }

    public function test_it_tracks_model_loaded_state(): void
    {
        $item = new CartItem(id: 1);

        $this->assertFalse($item->hasModelLoaded());
    }

    public function test_it_sets_model_loading_callback(): void
    {
        $item = new CartItem(id: 1);
        $callbackTriggered = false;

        $result = $item->setModelLoadingCallback(function () use (&$callbackTriggered) {
            $callbackTriggered = true;
        });

        // Should return self for chaining
        $this->assertSame($item, $result);
    }
}
