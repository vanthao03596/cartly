<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Drivers;

use Cart\CartContent;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Drivers\SessionDriver;
use Cart\Tests\TestCase;

class SessionDriverTest extends TestCase
{
    private SessionDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new SessionDriver;
    }

    public function test_it_returns_null_for_nonexistent_cart(): void
    {
        $content = $this->driver->get('default');

        $this->assertNull($content);
    }

    public function test_it_stores_and_retrieves_cart(): void
    {
        $content = $this->createContent();
        $this->driver->put('default', $content);

        $retrieved = $this->driver->get('default');

        $this->assertInstanceOf(CartContent::class, $retrieved);
        $this->assertSame(1, $retrieved->countItems());
    }

    public function test_it_stores_multiple_instances(): void
    {
        $cart = $this->createContent();
        $wishlist = new CartContent;

        $this->driver->put('default', $cart);
        $this->driver->put('wishlist', $wishlist);

        $this->assertNotNull($this->driver->get('default'));
        $this->assertNotNull($this->driver->get('wishlist'));
    }

    public function test_it_forgets_specific_instance(): void
    {
        $cart = $this->createContent();
        $wishlist = new CartContent;

        $this->driver->put('default', $cart);
        $this->driver->put('wishlist', $wishlist);

        $this->driver->forget('default');

        $this->assertNull($this->driver->get('default'));
        $this->assertNotNull($this->driver->get('wishlist'));
    }

    public function test_it_flushes_cart_instances_by_forgetting_each(): void
    {
        $this->driver->put('default', $this->createContent());
        $this->driver->put('wishlist', new CartContent);

        // Flush works by forgetting each known instance
        $this->driver->forget('default');
        $this->driver->forget('wishlist');

        $this->assertNull($this->driver->get('default'));
        $this->assertNull($this->driver->get('wishlist'));
    }

    public function test_it_ignores_identifier_parameter(): void
    {
        $content = $this->createContent();
        $this->driver->put('default', $content, 'user_1');

        // Should retrieve the same content regardless of identifier
        $retrieved = $this->driver->get('default', 'user_2');

        $this->assertInstanceOf(CartContent::class, $retrieved);
    }

    private function createContent(): CartContent
    {
        $items = new CartItemCollection;
        $item = new CartItem(id: 1, quantity: 1);
        $items->put($item->rowId, $item);

        return new CartContent(items: $items);
    }
}
