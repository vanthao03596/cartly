<?php

declare(strict_types=1);

namespace Cart\Conditions;

/**
 * Discount condition supporting both percentage and fixed discounts.
 */
class DiscountCondition extends BaseCondition
{
    protected string $type = 'discount';

    protected int $order = 50;

    /**
     * The discount value (percentage or cents).
     */
    protected float|int $value;

    /**
     * The discount mode: 'percentage' or 'fixed'.
     */
    protected string $mode;

    /**
     * Maximum discount amount in cents (for percentage discounts).
     */
    protected ?int $maxAmount;

    /**
     * Minimum order amount in cents required for discount.
     */
    protected ?int $minOrderAmount;

    /**
     * @param  string  $name  Unique name for this discount
     * @param  float|int  $value  The discount value (percentage 0-100 or fixed cents)
     * @param  string  $mode  'percentage' or 'fixed'
     * @param  string  $target  'subtotal' or 'item'
     * @param  int|null  $maxAmount  Maximum discount in cents (percentage only)
     * @param  int|null  $minOrderAmount  Minimum order for discount to apply
     *
     * @throws \InvalidArgumentException If value is invalid for the mode
     */
    public function __construct(
        string $name,
        float|int $value,
        string $mode = 'percentage',
        string $target = 'subtotal',
        ?int $maxAmount = null,
        ?int $minOrderAmount = null,
    ) {
        if ($value < 0) {
            throw new \InvalidArgumentException("Discount value cannot be negative. Got: {$value}");
        }

        if ($mode === 'percentage' && $value > 100) {
            throw new \InvalidArgumentException("Percentage discount cannot exceed 100. Got: {$value}");
        }

        if (! in_array($mode, ['percentage', 'fixed'], true)) {
            throw new \InvalidArgumentException("Invalid discount mode. Expected 'percentage' or 'fixed', got: {$mode}");
        }

        parent::__construct($name, [
            'value' => $value,
            'mode' => $mode,
            'maxAmount' => $maxAmount,
            'minOrderAmount' => $minOrderAmount,
        ]);

        $this->value = $value;
        $this->mode = $mode;
        $this->target = $target;
        $this->maxAmount = $maxAmount;
        $this->minOrderAmount = $minOrderAmount;
    }

    /**
     * Get the discount value.
     */
    public function getValue(): float|int
    {
        return $this->value;
    }

    /**
     * Get the discount mode.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Check if this is a percentage discount.
     */
    public function isPercentage(): bool
    {
        return $this->mode === 'percentage';
    }

    /**
     * Check if this is a fixed discount.
     */
    public function isFixed(): bool
    {
        return $this->mode === 'fixed';
    }

    /**
     * {@inheritdoc}
     */
    public function calculate(int $valueCents): int
    {
        $discount = $this->getCalculatedValue($valueCents);
        $result = $valueCents + $discount; // discount is negative

        return max(0, $result);
    }

    /**
     * {@inheritdoc}
     *
     * Returns negative value (discount).
     */
    public function getCalculatedValue(int $baseValueCents): int
    {
        // Check minimum order requirement
        if ($this->minOrderAmount !== null && $baseValueCents < $this->minOrderAmount) {
            return 0;
        }

        if ($this->mode === 'percentage') {
            $discount = (int) round($baseValueCents * $this->value / 100);

            // Apply max amount cap
            if ($this->maxAmount !== null) {
                $discount = min($discount, $this->maxAmount);
            }

            return -$discount;
        }

        // Fixed discount
        $discount = (int) $this->value;

        // Don't discount more than the base value
        return -min($discount, $baseValueCents);
    }

    /**
     * {@inheritdoc}
     */
    public static function fromArray(array $data): static
    {
        $attrs = $data['attributes'] ?? [];

        /** @phpstan-ignore-next-line new.static */
        return new static(
            name: $data['name'],
            value: $attrs['value'] ?? 0,
            mode: $attrs['mode'] ?? 'percentage',
            target: $data['target'] ?? 'subtotal',
            maxAmount: $attrs['maxAmount'] ?? null,
            minOrderAmount: $attrs['minOrderAmount'] ?? null,
        );
    }
}
