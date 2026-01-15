<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Conditions;

use Cart\Conditions\TaxCondition;
use PHPUnit\Framework\TestCase;

class TaxConditionTest extends TestCase
{
    public function test_it_creates_tax_condition(): void
    {
        $tax = new TaxCondition('VAT', 10);

        $this->assertSame('VAT', $tax->getName());
        $this->assertSame('tax', $tax->getType());
        $this->assertSame(100, $tax->getOrder());
        $this->assertSame(10.0, $tax->getRate());
    }

    public function test_it_calculates_tax_excluded_from_price(): void
    {
        $tax = new TaxCondition('VAT', 10, includedInPrice: false);

        // 10000 cents + 10% = 11000 cents
        $this->assertSame(11000, $tax->calculate(10000));
    }

    public function test_it_gets_calculated_value_when_excluded(): void
    {
        $tax = new TaxCondition('VAT', 10, includedInPrice: false);

        // 10% of 10000 = 1000
        $this->assertSame(1000, $tax->getCalculatedValue(10000));
    }

    public function test_it_does_not_add_tax_when_included_in_price(): void
    {
        $tax = new TaxCondition('VAT', 10, includedInPrice: true);

        // Price stays the same when tax is included
        $this->assertSame(11000, $tax->calculate(11000));
    }

    public function test_it_extracts_tax_when_included_in_price(): void
    {
        $tax = new TaxCondition('VAT', 10, includedInPrice: true);

        // 11000 / 1.10 = 10000 (subtotal)
        // Tax = 11000 - 10000 = 1000
        $this->assertSame(1000, $tax->getCalculatedValue(11000));
    }

    public function test_it_gets_subtotal_excluding_tax(): void
    {
        $tax = new TaxCondition('VAT', 10, includedInPrice: true);

        $this->assertSame(10000, $tax->getSubtotalExcludingTax(11000));
    }

    public function test_it_serializes_to_array(): void
    {
        $tax = new TaxCondition('VAT', 10, includedInPrice: true);

        $array = $tax->toArray();

        $this->assertSame('VAT', $array['name']);
        $this->assertSame('tax', $array['type']);
        $this->assertSame(10.0, $array['attributes']['rate']);
        $this->assertTrue($array['attributes']['includedInPrice']);
    }

    public function test_it_deserializes_from_array(): void
    {
        $data = [
            'name' => 'GST',
            'type' => 'tax',
            'target' => 'subtotal',
            'order' => 100,
            'attributes' => ['rate' => 15, 'includedInPrice' => true],
        ];

        $tax = TaxCondition::fromArray($data);

        $this->assertSame('GST', $tax->getName());
        $this->assertSame(15.0, $tax->getRate());
        $this->assertTrue($tax->isIncludedInPrice());
    }

    public function test_it_handles_zero_rate(): void
    {
        $tax = new TaxCondition('NoTax', 0);

        $this->assertSame(10000, $tax->calculate(10000));
        $this->assertSame(0, $tax->getCalculatedValue(10000));
    }

    public function test_it_throws_exception_for_negative_rate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rate must be between 0 and 100');

        new TaxCondition('BadTax', -5);
    }

    public function test_it_throws_exception_for_rate_over_100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rate must be between 0 and 100');

        new TaxCondition('BadTax', 150);
    }
}
