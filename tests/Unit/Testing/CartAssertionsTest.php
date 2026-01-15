<?php

declare(strict_types=1);

namespace Cart\Tests\Unit\Testing;

use Cart\CartManager;
use Cart\Conditions\DiscountCondition;
use Cart\Conditions\TaxCondition;
use Cart\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

class CartAssertionsTest extends TestCase
{
    protected CartManager $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cart = new CartManager();
        $this->cart->fake();
        $this->cart->fakeResolver(1000); // $10.00 per item
    }

    public function test_assert_item_count_passes_with_correct_count(): void
    {
        $this->cart->add(1, 2);
        $this->cart->add(2, 3);

        $this->cart->assertItemCount(5); // 2 + 3 = 5
    }

    public function test_assert_item_count_fails_with_incorrect_count(): void
    {
        $this->cart->add(1, 2);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart to have 5 items, but found 2.');

        $this->cart->assertItemCount(5);
    }

    public function test_assert_unique_item_count_passes_with_correct_count(): void
    {
        $this->cart->add(1, 2);
        $this->cart->add(2, 3);

        $this->cart->assertUniqueItemCount(2);
    }

    public function test_assert_unique_item_count_fails_with_incorrect_count(): void
    {
        $this->cart->add(1, 2);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart to have 3 unique items, but found 1.');

        $this->cart->assertUniqueItemCount(3);
    }

    public function test_assert_has_passes_when_item_exists(): void
    {
        $this->cart->add(42, 1);

        $this->cart->assertHas(42);
    }

    public function test_assert_has_fails_when_item_not_found(): void
    {
        $this->cart->add(1, 1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart to contain item with buyable ID [99]');

        $this->cart->assertHas(99);
    }

    public function test_assert_does_not_have_passes_when_item_missing(): void
    {
        $this->cart->add(1, 1);

        $this->cart->assertDoesNotHave(99);
    }

    public function test_assert_does_not_have_fails_when_item_exists(): void
    {
        $this->cart->add(42, 1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart to not contain item with buyable ID [42]');

        $this->cart->assertDoesNotHave(42);
    }

    public function test_assert_empty_passes_when_cart_is_empty(): void
    {
        $this->cart->assertEmpty();
    }

    public function test_assert_empty_fails_when_cart_has_items(): void
    {
        $this->cart->add(1, 1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart to be empty');

        $this->cart->assertEmpty();
    }

    public function test_assert_not_empty_passes_when_cart_has_items(): void
    {
        $this->cart->add(1, 1);

        $this->cart->assertNotEmpty();
    }

    public function test_assert_not_empty_fails_when_cart_is_empty(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart to not be empty');

        $this->cart->assertNotEmpty();
    }

    public function test_assert_total_passes_with_correct_amount(): void
    {
        $this->cart->add(1, 2); // 2 * 1000 = 2000 cents

        $this->cart->assertTotal(2000);
    }

    public function test_assert_total_fails_with_incorrect_amount(): void
    {
        $this->cart->add(1, 2); // 2000 cents

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart total to be 5000 cents, but got 2000 cents.');

        $this->cart->assertTotal(5000);
    }

    public function test_assert_subtotal_passes_with_correct_amount(): void
    {
        $this->cart->add(1, 3); // 3 * 1000 = 3000 cents

        $this->cart->assertSubtotal(3000);
    }

    public function test_assert_condition_applied_passes_when_condition_exists(): void
    {
        $this->cart->add(1, 1);
        $this->cart->condition(new TaxCondition('VAT', 10));

        $this->cart->assertConditionApplied('VAT');
    }

    public function test_assert_condition_applied_fails_when_condition_missing(): void
    {
        $this->cart->add(1, 1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected condition [VAT] to be applied to cart');

        $this->cart->assertConditionApplied('VAT');
    }

    public function test_assert_condition_not_applied_passes_when_missing(): void
    {
        $this->cart->add(1, 1);

        $this->cart->assertConditionNotApplied('VAT');
    }

    public function test_assert_condition_not_applied_fails_when_exists(): void
    {
        $this->cart->add(1, 1);
        $this->cart->condition(new TaxCondition('VAT', 10));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected condition [VAT] to not be applied to cart');

        $this->cart->assertConditionNotApplied('VAT');
    }

    public function test_assert_quantity_passes_with_correct_quantity(): void
    {
        $this->cart->add(42, 5);

        $this->cart->assertQuantity(42, 5);
    }

    public function test_assert_quantity_fails_with_incorrect_quantity(): void
    {
        $this->cart->add(42, 3);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected item [42] to have quantity 5, but got 3.');

        $this->cart->assertQuantity(42, 5);
    }

    public function test_assert_tax_total_passes_with_correct_amount(): void
    {
        $this->cart->add(1, 1); // 1000 cents
        $this->cart->condition(new TaxCondition('VAT', 10)); // 10% = 100 cents

        $this->cart->assertTaxTotal(100);
    }

    public function test_assert_discount_total_passes_with_correct_amount(): void
    {
        $this->cart->add(1, 1); // 1000 cents
        $this->cart->condition(new DiscountCondition('Sale', 20, 'percentage')); // 20% = -200 cents

        $this->cart->assertDiscountTotal(-200);
    }

    public function test_assertions_work_with_specific_instance(): void
    {
        $this->cart->instance('wishlist')->add(1, 2);
        $this->cart->instance('default')->add(2, 3);

        $this->cart->assertItemCount(2, 'wishlist');
        $this->cart->assertItemCount(3, 'default');
    }

    public function test_assert_has_row_id_passes_when_exists(): void
    {
        $item = $this->cart->add(1, 1);

        $this->cart->assertHasRowId($item->rowId);
    }

    public function test_assert_has_row_id_fails_when_not_found(): void
    {
        $this->cart->add(1, 1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected cart to contain item with row ID [invalid-row-id]');

        $this->cart->assertHasRowId('invalid-row-id');
    }
}
