<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Drivers;

use Cart\CartContent;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Drivers\CacheDriver;
use Cart\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class CacheDriverTest extends TestCase
{
    private CacheDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->driver = new CacheDriver;
    }

    public function test_it_returns_null_without_identifier(): void
    {
        $content = $this->driver->get('default');

        $this->assertNull($content);
    }

    public function test_it_returns_null_for_nonexistent_cart(): void
    {
        $content = $this->driver->get('default', 'user_1');

        $this->assertNull($content);
    }

    public function test_it_stores_and_retrieves_cart(): void
    {
        $content = $this->createContent();
        $this->driver->put('default', $content, 'user_1');

        $retrieved = $this->driver->get('default', 'user_1');

        $this->assertInstanceOf(CartContent::class, $retrieved);
        $this->assertSame(1, $retrieved->countItems());
    }

    public function test_it_stores_multiple_instances_for_same_user(): void
    {
        $cart = $this->createContent();
        $wishlist = new CartContent;

        $this->driver->put('default', $cart, 'user_1');
        $this->driver->put('wishlist', $wishlist, 'user_1');

        $this->assertNotNull($this->driver->get('default', 'user_1'));
        $this->assertNotNull($this->driver->get('wishlist', 'user_1'));
    }

    public function test_it_stores_for_multiple_users(): void
    {
        $user1Cart = $this->createContent();
        $user2Cart = new CartContent;

        $this->driver->put('default', $user1Cart, 'user_1');
        $this->driver->put('default', $user2Cart, 'user_2');

        $this->assertSame(1, $this->driver->get('default', 'user_1')->countItems());
        $this->assertSame(0, $this->driver->get('default', 'user_2')->countItems());
    }

    public function test_it_forgets_specific_instance(): void
    {
        $this->driver->put('default', $this->createContent(), 'user_1');
        $this->driver->put('wishlist', new CartContent, 'user_1');

        $this->driver->forget('default', 'user_1');

        $this->assertNull($this->driver->get('default', 'user_1'));
        $this->assertNotNull($this->driver->get('wishlist', 'user_1'));
    }

    public function test_it_flushes_all_instances_for_user(): void
    {
        $this->driver->put('default', $this->createContent(), 'user_1');
        $this->driver->put('wishlist', new CartContent, 'user_1');
        $this->driver->put('default', $this->createContent(), 'user_2');

        $this->driver->flush('user_1');

        $this->assertNull($this->driver->get('default', 'user_1'));
        $this->assertNull($this->driver->get('wishlist', 'user_1'));
        $this->assertNotNull($this->driver->get('default', 'user_2'));
    }

    public function test_it_throws_exception_when_putting_without_identifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->driver->put('default', $this->createContent());
    }

    private function createContent(): CartContent
    {
        $items = new CartItemCollection;
        $item = new CartItem(id: 1, quantity: 1);
        $items->put($item->rowId, $item);

        return new CartContent(items: $items);
    }
}
