<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\CartContent;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Conditions\TaxCondition;
use PHPUnit\Framework\TestCase;

class CartContentTest extends TestCase
{
    public function test_it_creates_empty_content(): void
    {
        $content = new CartContent();

        $this->assertTrue($content->isEmpty());
        $this->assertFalse($content->isNotEmpty());
        $this->assertSame(0, $content->countItems());
    }

    public function test_it_creates_content_with_items(): void
    {
        $items = new CartItemCollection();
        $item = new CartItem(id: 1, quantity: 2);
        $items->put($item->rowId, $item);

        $content = new CartContent(items: $items);

        $this->assertFalse($content->isEmpty());
        $this->assertTrue($content->isNotEmpty());
        $this->assertSame(1, $content->countItems());
        $this->assertSame(2, $content->totalQuantity());
    }

    public function test_it_manages_conditions(): void
    {
        $content = new CartContent();
        $tax = new TaxCondition('VAT', 10);

        $content->addCondition($tax);

        $this->assertTrue($content->hasCondition('VAT'));
        $this->assertSame($tax, $content->getCondition('VAT'));
    }

    public function test_it_removes_conditions(): void
    {
        $content = new CartContent();
        $tax = new TaxCondition('VAT', 10);

        $content->addCondition($tax);
        $content->removeCondition('VAT');

        $this->assertFalse($content->hasCondition('VAT'));
    }

    public function test_it_clears_conditions(): void
    {
        $content = new CartContent();
        $content->addCondition(new TaxCondition('VAT', 10));
        $content->addCondition(new TaxCondition('GST', 5));

        $content->clearConditions();

        $this->assertSame(0, $content->conditions->count());
    }

    public function test_it_manages_meta(): void
    {
        $content = new CartContent();

        $content->setMeta('coupon', 'SAVE10');

        $this->assertSame('SAVE10', $content->getMeta('coupon'));
        $this->assertNull($content->getMeta('nonexistent'));
        $this->assertSame('default', $content->getMeta('nonexistent', 'default'));
    }

    public function test_it_serializes_to_array(): void
    {
        $items = new CartItemCollection();
        $item = new CartItem(id: 1, quantity: 2);
        $items->put($item->rowId, $item);

        $content = new CartContent(
            items: $items,
            meta: ['coupon' => 'SAVE10'],
        );
        $content->addCondition(new TaxCondition('VAT', 10));

        $array = $content->toArray();

        $this->assertCount(1, $array['items']);
        $this->assertCount(1, $array['conditions']);
        $this->assertSame(['coupon' => 'SAVE10'], $array['meta']);
    }

    public function test_it_serializes_to_json(): void
    {
        $content = new CartContent(meta: ['test' => true]);

        $json = $content->toJson();

        $this->assertJson($json);
        $this->assertStringContainsString('"test":true', $json);
    }

    public function test_it_deserializes_from_array(): void
    {
        $data = [
            'items' => [
                [
                    'id' => 1,
                    'quantity' => 2,
                    'options' => ['size' => 'L'],
                    'buyableType' => null,
                    'buyableId' => 1,
                    'meta' => [],
                    'conditions' => [],
                ],
            ],
            'conditions' => [
                [
                    'class' => TaxCondition::class,
                    'name' => 'VAT',
                    'type' => 'tax',
                    'target' => 'subtotal',
                    'order' => 100,
                    'attributes' => ['rate' => 10, 'includedInPrice' => false],
                ],
            ],
            'meta' => ['coupon' => 'SAVE10'],
        ];

        $content = CartContent::fromArray($data);

        $this->assertSame(1, $content->countItems());
        $this->assertTrue($content->hasCondition('VAT'));
        $this->assertSame('SAVE10', $content->getMeta('coupon'));
    }

    public function test_it_deserializes_from_json(): void
    {
        $json = '{"items":[],"conditions":[],"meta":{"test":true}}';

        $content = CartContent::fromJson($json);

        $this->assertTrue($content->isEmpty());
        $this->assertTrue($content->getMeta('test'));
    }
}
