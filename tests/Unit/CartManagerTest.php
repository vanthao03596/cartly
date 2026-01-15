<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\CartInstance;
use Cart\CartManager;
use Cart\Drivers\ArrayDriver;
use Cart\Tests\TestCase;

class CartManagerTest extends TestCase
{
    private CartManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new CartManager();
        $this->manager->fake();
    }

    public function test_it_returns_default_instance(): void
    {
        $instance = $this->manager->instance();

        $this->assertInstanceOf(CartInstance::class, $instance);
        $this->assertSame('default', $instance->getInstanceName());
    }

    public function test_it_switches_between_instances(): void
    {
        $default = $this->manager->instance();
        $wishlist = $this->manager->instance('wishlist');

        $this->assertSame('default', $default->getInstanceName());
        $this->assertSame('wishlist', $wishlist->getInstanceName());
    }

    public function test_it_returns_same_instance_on_multiple_calls(): void
    {
        $first = $this->manager->instance('default');
        $second = $this->manager->instance('default');

        $this->assertSame($first, $second);
    }

    public function test_it_returns_current_instance_name(): void
    {
        $this->assertSame('default', $this->manager->currentInstance());

        $this->manager->instance('wishlist');

        $this->assertSame('wishlist', $this->manager->currentInstance());
    }

    public function test_it_proxies_method_calls_to_current_instance(): void
    {
        $this->manager->fakeResolver(1000);
        $this->manager->add(1, 2);

        $this->assertSame(1, $this->manager->countItems());
        $this->assertSame(2, $this->manager->count());
    }

    public function test_it_accesses_configured_instances_via_magic_method(): void
    {
        $wishlist = $this->manager->wishlist();

        $this->assertInstanceOf(CartInstance::class, $wishlist);
        $this->assertSame('wishlist', $wishlist->getInstanceName());
    }

    public function test_it_moves_item_to_another_instance(): void
    {
        $this->manager->fakeResolver(1000);
        $item = $this->manager->instance('default')->add(1, 2);

        $this->manager->moveTo($item->rowId, 'wishlist');

        $this->assertTrue($this->manager->instance('default')->isEmpty());
        $this->assertFalse($this->manager->instance('wishlist')->isEmpty());
    }

    public function test_it_moves_item_to_wishlist(): void
    {
        $this->manager->fakeResolver(1000);
        $item = $this->manager->instance('default')->add(1, 1);

        $this->manager->moveToWishlist($item->rowId);

        $this->assertTrue($this->manager->instance('default')->isEmpty());
        $this->assertFalse($this->manager->instance('wishlist')->isEmpty());
    }

    public function test_it_moves_item_from_wishlist_to_cart(): void
    {
        $this->manager->fakeResolver(1000);
        $item = $this->manager->instance('wishlist')->add(1, 1);

        $this->manager->moveToCart($item->rowId);

        $this->assertTrue($this->manager->instance('wishlist')->isEmpty());
        $this->assertFalse($this->manager->instance('default')->isEmpty());
    }

    public function test_fake_mode_uses_array_driver(): void
    {
        $this->manager->fakeResolver(1000);
        $this->manager->add(1, 1);

        $this->assertSame(1, $this->manager->countItems());
    }

    public function test_fake_resolver_with_fixed_price(): void
    {
        $this->manager->fakeResolver(500);
        $this->manager->add(1, 2);

        $this->assertSame(1000, $this->manager->subtotal());
    }

    public function test_it_sets_custom_driver(): void
    {
        $driver = new ArrayDriver();
        $this->manager->setDriver($driver);

        $this->manager->fakeResolver(1000);
        $this->manager->add(1, 1);

        $this->assertSame(1, $this->manager->countItems());
    }
}
