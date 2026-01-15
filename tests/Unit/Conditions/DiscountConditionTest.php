<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Conditions;

use Cart\Conditions\DiscountCondition;
use PHPUnit\Framework\TestCase;

class DiscountConditionTest extends TestCase
{
    public function test_it_creates_percentage_discount(): void
    {
        $discount = new DiscountCondition('Sale', 15, 'percentage');

        $this->assertSame('Sale', $discount->getName());
        $this->assertSame('discount', $discount->getType());
        $this->assertSame(50, $discount->getOrder());
        $this->assertTrue($discount->isPercentage());
    }

    public function test_it_creates_fixed_discount(): void
    {
        $discount = new DiscountCondition('Coupon', 500, 'fixed');

        $this->assertSame(500, $discount->getValue());
        $this->assertTrue($discount->isFixed());
    }

    public function test_it_calculates_percentage_discount(): void
    {
        $discount = new DiscountCondition('Sale', 15, 'percentage');

        // 10000 - 15% = 8500
        $this->assertSame(8500, $discount->calculate(10000));
    }

    public function test_it_gets_percentage_discount_value(): void
    {
        $discount = new DiscountCondition('Sale', 15, 'percentage');

        // -15% of 10000 = -1500
        $this->assertSame(-1500, $discount->getCalculatedValue(10000));
    }

    public function test_it_calculates_fixed_discount(): void
    {
        $discount = new DiscountCondition('Coupon', 500, 'fixed');

        // 10000 - 500 = 9500
        $this->assertSame(9500, $discount->calculate(10000));
    }

    public function test_it_gets_fixed_discount_value(): void
    {
        $discount = new DiscountCondition('Coupon', 500, 'fixed');

        $this->assertSame(-500, $discount->getCalculatedValue(10000));
    }

    public function test_it_does_not_go_below_zero(): void
    {
        $discount = new DiscountCondition('BigDiscount', 15000, 'fixed');

        $this->assertSame(0, $discount->calculate(10000));
    }

    public function test_it_respects_max_amount_for_percentage(): void
    {
        $discount = new DiscountCondition('Sale', 50, 'percentage', 'subtotal', maxAmount: 1000);

        // 50% of 10000 = 5000, but max is 1000
        $this->assertSame(-1000, $discount->getCalculatedValue(10000));
        $this->assertSame(9000, $discount->calculate(10000));
    }

    public function test_it_respects_minimum_order_amount(): void
    {
        $discount = new DiscountCondition('Sale', 10, 'percentage', 'subtotal', minOrderAmount: 5000);

        // Below minimum - no discount
        $this->assertSame(0, $discount->getCalculatedValue(4000));
        $this->assertSame(4000, $discount->calculate(4000));

        // Above minimum - discount applies
        $this->assertSame(-1000, $discount->getCalculatedValue(10000));
        $this->assertSame(9000, $discount->calculate(10000));
    }

    public function test_it_serializes_to_array(): void
    {
        $discount = new DiscountCondition('Sale', 15, 'percentage', 'subtotal', 1000, 5000);

        $array = $discount->toArray();

        $this->assertSame('Sale', $array['name']);
        $this->assertSame('discount', $array['type']);
        $this->assertSame(15, $array['attributes']['value']);
        $this->assertSame('percentage', $array['attributes']['mode']);
        $this->assertSame(1000, $array['attributes']['maxAmount']);
        $this->assertSame(5000, $array['attributes']['minOrderAmount']);
    }

    public function test_it_deserializes_from_array(): void
    {
        $data = [
            'name' => 'Coupon',
            'type' => 'discount',
            'target' => 'subtotal',
            'order' => 50,
            'attributes' => [
                'value' => 500,
                'mode' => 'fixed',
            ],
        ];

        $discount = DiscountCondition::fromArray($data);

        $this->assertSame('Coupon', $discount->getName());
        $this->assertSame(500, $discount->getValue());
        $this->assertTrue($discount->isFixed());
    }

    public function test_it_throws_exception_for_negative_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount value cannot be negative');

        new DiscountCondition('BadDiscount', -10);
    }

    public function test_it_throws_exception_for_percentage_over_100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentage discount cannot exceed 100');

        new DiscountCondition('BadDiscount', 150, 'percentage');
    }

    public function test_it_throws_exception_for_invalid_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid discount mode. Expected 'percentage' or 'fixed'");

        new DiscountCondition('BadDiscount', 10, 'invalid');
    }

    public function test_it_allows_fixed_discount_over_100(): void
    {
        // Fixed discount of 15000 cents ($150) is valid
        $discount = new DiscountCondition('BigDiscount', 15000, 'fixed');

        $this->assertSame(15000, $discount->getValue());
    }
}
