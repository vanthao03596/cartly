<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Conditions;

use Cart\Conditions\ShippingCondition;
use PHPUnit\Framework\TestCase;

class ShippingConditionTest extends TestCase
{
    public function test_it_creates_shipping_condition(): void
    {
        $shipping = new ShippingCondition('Standard', 599);

        $this->assertSame('Standard', $shipping->getName());
        $this->assertSame('shipping', $shipping->getType());
        $this->assertSame(200, $shipping->getOrder());
        $this->assertSame(599, $shipping->getAmount());
    }

    public function test_it_calculates_shipping_cost(): void
    {
        $shipping = new ShippingCondition('Standard', 599);

        // 10000 + 599 = 10599
        $this->assertSame(10599, $shipping->calculate(10000));
    }

    public function test_it_gets_shipping_value(): void
    {
        $shipping = new ShippingCondition('Standard', 599);

        $this->assertSame(599, $shipping->getCalculatedValue(10000));
    }

    public function test_it_applies_free_shipping_threshold(): void
    {
        $shipping = new ShippingCondition('Standard', 599, freeShippingThreshold: 5000);

        // Below threshold - shipping charged
        $this->assertFalse($shipping->hasFreeShipping(4999));
        $this->assertSame(5598, $shipping->calculate(4999));
        $this->assertSame(599, $shipping->getCalculatedValue(4999));

        // At threshold - free shipping
        $this->assertTrue($shipping->hasFreeShipping(5000));
        $this->assertSame(5000, $shipping->calculate(5000));
        $this->assertSame(0, $shipping->getCalculatedValue(5000));

        // Above threshold - free shipping
        $this->assertTrue($shipping->hasFreeShipping(10000));
        $this->assertSame(10000, $shipping->calculate(10000));
        $this->assertSame(0, $shipping->getCalculatedValue(10000));
    }

    public function test_it_serializes_to_array(): void
    {
        $shipping = new ShippingCondition('Express', 999, freeShippingThreshold: 7500);

        $array = $shipping->toArray();

        $this->assertSame('Express', $array['name']);
        $this->assertSame('shipping', $array['type']);
        $this->assertSame(999, $array['attributes']['amount']);
        $this->assertSame(7500, $array['attributes']['freeShippingThreshold']);
    }

    public function test_it_deserializes_from_array(): void
    {
        $data = [
            'name' => 'Express',
            'type' => 'shipping',
            'target' => 'subtotal',
            'order' => 200,
            'attributes' => [
                'amount' => 999,
                'freeShippingThreshold' => 7500,
            ],
        ];

        $shipping = ShippingCondition::fromArray($data);

        $this->assertSame('Express', $shipping->getName());
        $this->assertSame(999, $shipping->getAmount());
        $this->assertSame(7500, $shipping->getFreeShippingThreshold());
    }
}
