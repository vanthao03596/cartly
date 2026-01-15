<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\CartInstance;
use Cart\CartItem;
use Cart\CartContext;
use Cart\CartItemCollection;
use Cart\Contracts\PriceResolver;
use Cart\Drivers\ArrayDriver;
use Cart\Exceptions\InvalidQuantityException;
use Cart\Exceptions\InvalidRowIdException;
use Cart\Exceptions\MaxItemsExceededException;
use Cart\ResolvedPrice;
use Illuminate\Support\Facades\Config;
use Cart\Tests\TestCase;

class CartInstanceTest extends TestCase
{
    private CartInstance $cart;
    private ArrayDriver $driver;
    private PriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new ArrayDriver();
        $this->resolver = $this->createFakeResolver(1000);
        $this->cart = new CartInstance('default', $this->driver, $this->resolver);
        $this->cart->setEventsEnabled(false);
    }

    public function test_it_adds_item_to_cart(): void
    {
        $item = $this->cart->add(1, 2);

        $this->assertInstanceOf(CartItem::class, $item);
        $this->assertSame(1, $item->id);
        $this->assertSame(2, $item->quantity);
        $this->assertFalse($this->cart->isEmpty());
    }

    public function test_it_adds_item_with_options(): void
    {
        $item = $this->cart->add(1, 1, ['size' => 'L', 'color' => 'red']);

        $this->assertSame('L', $item->options->get('size'));
        $this->assertSame('red', $item->options->get('color'));
    }

    public function test_it_adds_item_with_meta(): void
    {
        $item = $this->cart->add(1, 1, [], ['gift_wrap' => true]);

        $this->assertTrue($item->meta->get('gift_wrap'));
    }

    public function test_it_increases_quantity_when_adding_same_item(): void
    {
        $item1 = $this->cart->add(1, 2, ['size' => 'L']);
        $item2 = $this->cart->add(1, 3, ['size' => 'L']);

        $this->assertSame($item1->rowId, $item2->rowId);
        $this->assertSame(5, $item2->quantity);
        $this->assertSame(1, $this->cart->countItems());
    }

    public function test_it_creates_separate_items_for_different_options(): void
    {
        $item1 = $this->cart->add(1, 1, ['size' => 'L']);
        $item2 = $this->cart->add(1, 1, ['size' => 'M']);

        $this->assertNotSame($item1->rowId, $item2->rowId);
        $this->assertSame(2, $this->cart->countItems());
    }

    public function test_it_throws_exception_for_invalid_quantity(): void
    {
        $this->expectException(InvalidQuantityException::class);

        $this->cart->add(1, 0);
    }

    public function test_it_updates_item_quantity(): void
    {
        $item = $this->cart->add(1, 2);
        $updated = $this->cart->update($item->rowId, 5);

        $this->assertSame(5, $updated->quantity);
    }

    public function test_it_updates_item_with_attributes_array(): void
    {
        $item = $this->cart->add(1, 2, ['size' => 'L']);
        $updated = $this->cart->update($item->rowId, [
            'quantity' => 3,
            'options' => ['color' => 'blue'],
        ]);

        $this->assertSame(3, $updated->quantity);
        $this->assertSame('blue', $updated->options->get('color'));
    }

    public function test_it_throws_exception_when_updating_nonexistent_item(): void
    {
        $this->expectException(InvalidRowIdException::class);

        $this->cart->update('invalid_row_id', 1);
    }

    public function test_it_removes_item(): void
    {
        $item = $this->cart->add(1, 1);
        $this->cart->remove($item->rowId);

        $this->assertTrue($this->cart->isEmpty());
    }

    public function test_it_throws_exception_when_removing_nonexistent_item(): void
    {
        $this->expectException(InvalidRowIdException::class);

        $this->cart->remove('invalid_row_id');
    }

    public function test_it_gets_item_by_row_id(): void
    {
        $item = $this->cart->add(1, 2);
        $retrieved = $this->cart->get($item->rowId);

        $this->assertSame($item->rowId, $retrieved->rowId);
    }

    public function test_it_returns_null_for_nonexistent_row_id(): void
    {
        $retrieved = $this->cart->get('invalid');

        $this->assertNull($retrieved);
    }

    public function test_it_checks_if_item_exists(): void
    {
        $item = $this->cart->add(1, 1);

        $this->assertTrue($this->cart->has($item->rowId));
        $this->assertFalse($this->cart->has('invalid'));
    }

    public function test_it_returns_content(): void
    {
        $this->cart->add(1, 1);
        $this->cart->add(2, 2);

        $content = $this->cart->content();

        $this->assertInstanceOf(CartItemCollection::class, $content);
        $this->assertCount(2, $content);
    }

    public function test_it_checks_empty_state(): void
    {
        $this->assertTrue($this->cart->isEmpty());
        $this->assertFalse($this->cart->isNotEmpty());

        $this->cart->add(1, 1);

        $this->assertFalse($this->cart->isEmpty());
        $this->assertTrue($this->cart->isNotEmpty());
    }

    public function test_it_counts_total_quantity(): void
    {
        $this->cart->add(1, 3);
        $this->cart->add(2, 2);

        $this->assertSame(5, $this->cart->count());
    }

    public function test_it_counts_unique_items(): void
    {
        $this->cart->add(1, 3);
        $this->cart->add(2, 2);

        $this->assertSame(2, $this->cart->countItems());
    }

    public function test_it_calculates_subtotal(): void
    {
        $this->cart->add(1, 2); // 2 * 1000 = 2000
        $this->cart->add(2, 1); // 1 * 1000 = 1000

        $this->assertSame(3000, $this->cart->subtotal());
    }

    public function test_it_calculates_total_without_conditions(): void
    {
        $this->cart->add(1, 2);

        $this->assertSame(2000, $this->cart->total());
    }

    public function test_it_clears_cart(): void
    {
        $this->cart->add(1, 1);
        $this->cart->add(2, 1);
        $this->cart->clear();

        $this->assertTrue($this->cart->isEmpty());
    }

    public function test_it_destroys_cart(): void
    {
        $this->cart->add(1, 1);
        $this->cart->destroy();

        $this->assertTrue($this->cart->isEmpty());
    }

    public function test_it_finds_item_by_buyable_id(): void
    {
        $item = $this->cart->add(123, 1);

        $found = $this->cart->find(123);

        $this->assertSame($item->rowId, $found->rowId);
    }

    public function test_it_returns_instance_name(): void
    {
        $this->assertSame('default', $this->cart->getInstanceName());
    }

    public function test_it_enforces_max_items_constraint(): void
    {
        Config::set('cart.instances.limited', ['max_items' => 2]);
        $cart = new CartInstance('limited', $this->driver, $this->resolver);
        $cart->setEventsEnabled(false);

        $cart->add(1, 1);
        $cart->add(2, 1);

        $this->expectException(MaxItemsExceededException::class);
        $cart->add(3, 1);
    }

    private function createFakeResolver(int $price): PriceResolver
    {
        return new class($price) implements PriceResolver {
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
