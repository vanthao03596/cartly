<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Support;

use Cart\Conditions\DiscountCondition;
use Cart\Conditions\ShippingCondition;
use Cart\Conditions\TaxCondition;
use Cart\Support\CalculationPipeline;
use Cart\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CalculationPipelineTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_static_make(): void
    {
        $pipeline = CalculationPipeline::make();

        $this->assertInstanceOf(CalculationPipeline::class, $pipeline);
    }

    #[Test]
    public function it_returns_original_value_when_no_conditions(): void
    {
        $pipeline = CalculationPipeline::make();

        $result = $pipeline->process(10000);

        $this->assertSame(10000, $result);
        $this->assertEmpty($pipeline->getSteps());
    }

    #[Test]
    public function it_applies_single_condition(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'tax' => new TaxCondition('VAT', 10),
            ]));

        $result = $pipeline->process(10000);

        $this->assertSame(11000, $result);
    }

    #[Test]
    public function it_applies_conditions_in_order(): void
    {
        // Discount (order 50) should apply before tax (order 100)
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'tax' => new TaxCondition('VAT', 10),                          // order 100
                'discount' => new DiscountCondition('Sale', 10, 'percentage'), // order 50
            ]));

        // Start: 10000
        // After 10% discount: 9000
        // After 10% tax: 9900
        $result = $pipeline->process(10000);

        $this->assertSame(9900, $result);
    }

    #[Test]
    public function it_records_calculation_steps(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'discount' => new DiscountCondition('Sale', 15, 'percentage'),
                'tax' => new TaxCondition('VAT', 10),
            ]));

        $pipeline->process(10000);
        $steps = $pipeline->getSteps();

        $this->assertCount(2, $steps);

        // First step: discount
        $this->assertSame('Sale', $steps[0]['name']);
        $this->assertSame('discount', $steps[0]['type']);
        $this->assertSame(50, $steps[0]['order']);
        $this->assertSame(10000, $steps[0]['before']);
        $this->assertSame(8500, $steps[0]['after']);
        $this->assertSame(-1500, $steps[0]['change']);

        // Second step: tax
        $this->assertSame('VAT', $steps[1]['name']);
        $this->assertSame('tax', $steps[1]['type']);
        $this->assertSame(100, $steps[1]['order']);
        $this->assertSame(8500, $steps[1]['before']);
        $this->assertSame(9350, $steps[1]['after']);
        $this->assertSame(850, $steps[1]['change']);
    }

    #[Test]
    public function it_provides_breakdown_by_type(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'discount' => new DiscountCondition('Sale', 15, 'percentage'),
                'tax' => new TaxCondition('VAT', 10),
                'shipping' => new ShippingCondition('Standard', 599),
            ]));

        $pipeline->process(10000);
        $breakdown = $pipeline->getBreakdown();

        $this->assertArrayHasKey('discount', $breakdown);
        $this->assertArrayHasKey('tax', $breakdown);
        $this->assertArrayHasKey('shipping', $breakdown);

        $this->assertSame(-1500, $breakdown['discount']);
        $this->assertSame(850, $breakdown['tax']);
        $this->assertSame(599, $breakdown['shipping']);
    }

    #[Test]
    public function it_calculates_total_change(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'discount' => new DiscountCondition('Sale', 15, 'percentage'),
                'tax' => new TaxCondition('VAT', 10),
                'shipping' => new ShippingCondition('Standard', 599),
            ]));

        $pipeline->process(10000);

        // -1500 + 850 + 599 = -51
        $this->assertSame(-51, $pipeline->getTotalChange());
    }

    #[Test]
    public function it_ensures_result_is_not_negative(): void
    {
        // 100% discount should result in 0, not negative
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'discount' => new DiscountCondition('Free', 100, 'percentage'),
            ]));

        $result = $pipeline->process(10000);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function it_ensures_result_is_not_negative_with_fixed_discount(): void
    {
        // Fixed discount larger than subtotal
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'discount' => new DiscountCondition('Big Discount', 20000, 'fixed'),
            ]));

        $result = $pipeline->process(10000);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function it_tracks_has_processed_state(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'tax' => new TaxCondition('VAT', 10),
            ]));

        $this->assertFalse($pipeline->hasProcessed());

        $pipeline->process(10000);

        $this->assertTrue($pipeline->hasProcessed());
    }

    #[Test]
    public function it_returns_step_count(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'discount' => new DiscountCondition('Sale', 10, 'percentage'),
                'tax' => new TaxCondition('VAT', 10),
                'shipping' => new ShippingCondition('Standard', 599),
            ]));

        $pipeline->process(10000);

        $this->assertSame(3, $pipeline->getStepCount());
    }

    #[Test]
    public function it_clears_steps_on_reprocess(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'tax' => new TaxCondition('VAT', 10),
            ]));

        $pipeline->process(10000);
        $this->assertCount(1, $pipeline->getSteps());

        // Process again
        $pipeline->process(5000);
        $steps = $pipeline->getSteps();

        // Should have fresh steps, not accumulated
        $this->assertCount(1, $steps);
        $this->assertSame(5000, $steps[0]['before']);
    }

    #[Test]
    public function it_handles_multiple_conditions_of_same_type(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'discount1' => new DiscountCondition('Early Bird', 10, 'percentage'),
                'discount2' => new DiscountCondition('Member', 5, 'percentage'),
            ]));

        $pipeline->process(10000);
        $breakdown = $pipeline->getBreakdown();

        // Both discounts should be combined in breakdown
        // 10% of 10000 = -1000, then 5% of 9000 = -450
        // Total discount: -1450
        $this->assertSame(-1450, $breakdown['discount']);
    }

    #[Test]
    public function it_applies_shipping_after_tax(): void
    {
        // Tax order: 100, Shipping order: 200
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'shipping' => new ShippingCondition('Express', 1000),
                'tax' => new TaxCondition('VAT', 10),
            ]));

        $pipeline->process(10000);
        $steps = $pipeline->getSteps();

        // Tax should be applied first (order 100)
        $this->assertSame('VAT', $steps[0]['name']);
        // Shipping should be applied second (order 200)
        $this->assertSame('Express', $steps[1]['name']);
    }

    #[Test]
    public function it_works_with_empty_collection(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect());

        $result = $pipeline->process(10000);

        $this->assertSame(10000, $result);
        $this->assertEmpty($pipeline->getSteps());
        $this->assertEmpty($pipeline->getBreakdown());
        $this->assertSame(0, $pipeline->getTotalChange());
    }

    #[Test]
    public function it_handles_zero_value_input(): void
    {
        $pipeline = CalculationPipeline::make()
            ->through(collect([
                'tax' => new TaxCondition('VAT', 10),
            ]));

        $result = $pipeline->process(0);

        $this->assertSame(0, $result);
    }
}
