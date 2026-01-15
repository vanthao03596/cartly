<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\ResolvedPrice;
use PHPUnit\Framework\TestCase;

class ResolvedPriceTest extends TestCase
{
    public function test_it_creates_resolved_price(): void
    {
        $price = new ResolvedPrice(
            unitPrice: 9999,
            originalPrice: 12999,
            priceSource: 'sale',
        );

        $this->assertSame(9999, $price->unitPrice);
        $this->assertSame(12999, $price->originalPrice);
        $this->assertSame('sale', $price->priceSource);
    }

    public function test_it_detects_discount(): void
    {
        $discounted = new ResolvedPrice(unitPrice: 8000, originalPrice: 10000);
        $notDiscounted = new ResolvedPrice(unitPrice: 10000, originalPrice: 10000);

        $this->assertTrue($discounted->hasDiscount());
        $this->assertFalse($notDiscounted->hasDiscount());
    }

    public function test_it_calculates_discount_percent(): void
    {
        $price = new ResolvedPrice(unitPrice: 8000, originalPrice: 10000);

        $this->assertSame(20.0, $price->discountPercent());
    }

    public function test_it_calculates_discount_amount(): void
    {
        $price = new ResolvedPrice(unitPrice: 8000, originalPrice: 10000);

        $this->assertSame(2000, $price->discountAmount());
    }

    public function test_it_handles_zero_original_price(): void
    {
        $price = new ResolvedPrice(unitPrice: 0, originalPrice: 0);

        $this->assertSame(0.0, $price->discountPercent());
    }

    public function test_it_serializes_to_array(): void
    {
        $price = new ResolvedPrice(
            unitPrice: 9999,
            originalPrice: 12999,
            priceSource: 'sale',
            meta: ['tier' => 'gold'],
        );

        $array = $price->toArray();

        $this->assertSame(9999, $array['unitPrice']);
        $this->assertSame(12999, $array['originalPrice']);
        $this->assertSame('sale', $array['priceSource']);
        $this->assertSame(['tier' => 'gold'], $array['meta']);
    }

    public function test_it_deserializes_from_array(): void
    {
        $data = [
            'unitPrice' => 9999,
            'originalPrice' => 12999,
            'priceSource' => 'sale',
            'meta' => ['tier' => 'gold'],
        ];

        $price = ResolvedPrice::fromArray($data);

        $this->assertSame(9999, $price->unitPrice);
        $this->assertSame(12999, $price->originalPrice);
        $this->assertSame('sale', $price->priceSource);
        $this->assertSame(['tier' => 'gold'], $price->meta);
    }
}
