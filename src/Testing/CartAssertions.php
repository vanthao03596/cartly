<?php

declare(strict_types=1);

namespace Cart\Testing;

use Cart\Contracts\Condition;
use PHPUnit\Framework\Assert;

/**
 * Trait providing testing assertions for the cart.
 * Used by CartManager when in fake mode.
 */
trait CartAssertions
{
    /**
     * Assert that the cart contains the expected number of items (total quantity).
     */
    public function assertItemCount(int $expected, ?string $instance = null): void
    {
        $actual = $this->instance($instance)->count();

        Assert::assertSame(
            $expected,
            $actual,
            "Expected cart to have {$expected} items, but found {$actual}."
        );
    }

    /**
     * Assert that the cart contains a specific number of unique items.
     */
    public function assertUniqueItemCount(int $expected, ?string $instance = null): void
    {
        $actual = $this->instance($instance)->countItems();

        Assert::assertSame(
            $expected,
            $actual,
            "Expected cart to have {$expected} unique items, but found {$actual}."
        );
    }

    /**
     * Assert that the cart contains a specific buyable ID.
     */
    public function assertHas(int|string $buyableId, ?string $instance = null): void
    {
        $item = $this->instance($instance)->find($buyableId);

        Assert::assertNotNull(
            $item,
            "Expected cart to contain item with buyable ID [{$buyableId}], but it was not found."
        );
    }

    /**
     * Assert that the cart does not contain a specific buyable ID.
     */
    public function assertDoesNotHave(int|string $buyableId, ?string $instance = null): void
    {
        $item = $this->instance($instance)->find($buyableId);

        Assert::assertNull(
            $item,
            "Expected cart to not contain item with buyable ID [{$buyableId}], but it was found."
        );
    }

    /**
     * Assert that the cart has a specific row ID.
     */
    public function assertHasRowId(string $rowId, ?string $instance = null): void
    {
        $has = $this->instance($instance)->has($rowId);

        Assert::assertTrue(
            $has,
            "Expected cart to contain item with row ID [{$rowId}], but it was not found."
        );
    }

    /**
     * Assert that the cart total matches the expected value in cents.
     */
    public function assertTotal(int $expectedCents, ?string $instance = null): void
    {
        $actual = $this->instance($instance)->total();

        Assert::assertSame(
            $expectedCents,
            $actual,
            "Expected cart total to be {$expectedCents} cents, but got {$actual} cents."
        );
    }

    /**
     * Assert that the cart subtotal matches the expected value in cents.
     */
    public function assertSubtotal(int $expectedCents, ?string $instance = null): void
    {
        $actual = $this->instance($instance)->subtotal();

        Assert::assertSame(
            $expectedCents,
            $actual,
            "Expected cart subtotal to be {$expectedCents} cents, but got {$actual} cents."
        );
    }

    /**
     * Assert that the cart is empty.
     */
    public function assertEmpty(?string $instance = null): void
    {
        $isEmpty = $this->instance($instance)->isEmpty();

        Assert::assertTrue(
            $isEmpty,
            'Expected cart to be empty, but it contains items.'
        );
    }

    /**
     * Assert that the cart is not empty.
     */
    public function assertNotEmpty(?string $instance = null): void
    {
        $isNotEmpty = $this->instance($instance)->isNotEmpty();

        Assert::assertTrue(
            $isNotEmpty,
            'Expected cart to not be empty, but it is empty.'
        );
    }

    /**
     * Assert that a condition with the given name is applied to the cart.
     */
    public function assertConditionApplied(string $conditionName, ?string $instance = null): void
    {
        $hasCondition = $this->instance($instance)->hasCondition($conditionName);

        Assert::assertTrue(
            $hasCondition,
            "Expected condition [{$conditionName}] to be applied to cart, but it was not found."
        );
    }

    /**
     * Assert that a condition with the given name is not applied to the cart.
     */
    public function assertConditionNotApplied(string $conditionName, ?string $instance = null): void
    {
        $hasCondition = $this->instance($instance)->hasCondition($conditionName);

        Assert::assertFalse(
            $hasCondition,
            "Expected condition [{$conditionName}] to not be applied to cart, but it was found."
        );
    }

    /**
     * Assert that the cart has a specific quantity of a buyable item.
     */
    public function assertQuantity(int|string $buyableId, int $expectedQuantity, ?string $instance = null): void
    {
        $item = $this->instance($instance)->find($buyableId);

        Assert::assertNotNull(
            $item,
            "Expected cart to contain item with buyable ID [{$buyableId}], but it was not found."
        );

        Assert::assertSame(
            $expectedQuantity,
            $item->quantity,
            "Expected item [{$buyableId}] to have quantity {$expectedQuantity}, but got {$item->quantity}."
        );
    }

    /**
     * Assert that the tax total matches the expected value in cents.
     */
    public function assertTaxTotal(int $expectedCents, ?string $instance = null): void
    {
        $actual = $this->instance($instance)->taxTotal();

        Assert::assertSame(
            $expectedCents,
            $actual,
            "Expected tax total to be {$expectedCents} cents, but got {$actual} cents."
        );
    }

    /**
     * Assert that the discount total matches the expected value in cents.
     * Note: Discount total is typically negative.
     */
    public function assertDiscountTotal(int $expectedCents, ?string $instance = null): void
    {
        $actual = $this->instance($instance)->discountTotal();

        Assert::assertSame(
            $expectedCents,
            $actual,
            "Expected discount total to be {$expectedCents} cents, but got {$actual} cents."
        );
    }
}
