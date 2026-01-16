<?php

declare(strict_types=1);

namespace Cart\Conditions;

/**
 * Base class for fixed amount conditions.
 */
abstract class FixedCondition extends BaseCondition
{
    /**
     * The fixed amount in cents.
     */
    protected int $amount;

    /**
     * Whether this is a negative adjustment (discount).
     */
    protected bool $negative = false;

    /**
     * @param  string  $name  Unique name for this condition
     * @param  int  $amount  The fixed amount in cents
     * @param  array<string, mixed>  $attributes  Additional attributes
     */
    public function __construct(string $name, int $amount, array $attributes = [])
    {
        parent::__construct($name, $attributes);
        $this->amount = $amount;
        $this->attributes['amount'] = $amount;
    }

    /**
     * Get the fixed amount in cents.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * {@inheritdoc}
     */
    public function calculate(int $valueCents): int
    {
        $adjustment = $this->getCalculatedValue($valueCents);
        $result = $valueCents + $adjustment;

        // Ensure we don't go below zero
        return max(0, $result);
    }

    /**
     * {@inheritdoc}
     */
    public function getCalculatedValue(int $baseValueCents): int
    {
        return $this->negative ? -$this->amount : $this->amount;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromArray(array $data): static
    {
        $amount = $data['attributes']['amount'] ?? 0;

        /** @phpstan-ignore-next-line new.static */
        return new static(
            name: $data['name'],
            amount: (int) $amount,
            attributes: $data['attributes'] ?? [],
        );
    }
}
