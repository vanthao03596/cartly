<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\CartItem;
use Cart\CartItemCollection;
use PHPUnit\Framework\TestCase;

class CartItemCollectionTest extends TestCase
{
    public function test_it_creates_empty_collection(): void
    {
        $collection = new CartItemCollection;

        $this->assertTrue($collection->isEmpty());
    }

    public function test_it_finds_item_by_row_id(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $collection = new CartItemCollection([$item->rowId => $item]);

        $found = $collection->find($item->rowId);

        $this->assertSame($item, $found);
    }

    public function test_it_returns_null_for_nonexistent_row_id(): void
    {
        $collection = new CartItemCollection;

        $found = $collection->find('nonexistent');

        $this->assertNull($found);
    }

    public function test_it_finds_item_by_buyable_id(): void
    {
        $item1 = new CartItem(id: 1, quantity: 1);
        $item2 = new CartItem(id: 2, quantity: 1);
        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
        ]);

        $found = $collection->findByBuyableId(2);

        $this->assertSame($item2, $found);
    }

    public function test_it_returns_null_for_nonexistent_buyable_id(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $collection = new CartItemCollection([$item->rowId => $item]);

        $found = $collection->findByBuyableId(999);

        $this->assertNull($found);
    }

    public function test_it_finds_items_by_buyable_type(): void
    {
        $item1 = new CartItem(id: 1, quantity: 1, buyableType: 'App\\Product');
        $item2 = new CartItem(id: 2, quantity: 1, buyableType: 'App\\Service');
        $item3 = new CartItem(id: 3, quantity: 1, buyableType: 'App\\Product');

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
            $item3->rowId => $item3,
        ]);

        $products = $collection->findByBuyableType('App\\Product');

        $this->assertCount(2, $products);
    }

    public function test_it_calculates_total_quantity(): void
    {
        $item1 = new CartItem(id: 1, quantity: 3);
        $item2 = new CartItem(id: 2, quantity: 2);
        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
        ]);

        $this->assertSame(5, $collection->totalQuantity());
    }

    public function test_it_returns_zero_quantity_for_empty_collection(): void
    {
        $collection = new CartItemCollection;

        $this->assertSame(0, $collection->totalQuantity());
    }

    public function test_it_checks_has_row_id(): void
    {
        $item = new CartItem(id: 1, quantity: 1);
        $collection = new CartItemCollection([$item->rowId => $item]);

        $this->assertTrue($collection->hasRowId($item->rowId));
        $this->assertFalse($collection->hasRowId('nonexistent'));
    }

    public function test_it_groups_by_buyable_type(): void
    {
        $item1 = new CartItem(id: 1, quantity: 1, buyableType: 'App\\Product');
        $item2 = new CartItem(id: 2, quantity: 1, buyableType: 'App\\Service');
        $item3 = new CartItem(id: 3, quantity: 1, buyableType: 'App\\Product');

        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
            $item3->rowId => $item3,
        ]);

        $grouped = $collection->groupByBuyableType();

        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped->get('App\\Product'));
        $this->assertCount(1, $grouped->get('App\\Service'));
    }

    public function test_it_creates_from_array(): void
    {
        $data = [
            ['id' => 1, 'quantity' => 2, 'options' => ['size' => 'L']],
            ['id' => 2, 'quantity' => 1, 'options' => []],
        ];

        $collection = CartItemCollection::fromArray($data);

        $this->assertCount(2, $collection);
    }

    public function test_it_converts_to_storage_array(): void
    {
        $item1 = new CartItem(id: 1, quantity: 2);
        $item2 = new CartItem(id: 2, quantity: 1);
        $collection = new CartItemCollection([
            $item1->rowId => $item1,
            $item2->rowId => $item2,
        ]);

        $array = $collection->toStorageArray();

        $this->assertCount(2, $array);
        $this->assertArrayHasKey('id', $array[0]);
        $this->assertArrayHasKey('quantity', $array[0]);
    }
}
