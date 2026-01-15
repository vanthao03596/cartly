<?php

declare(strict_types=1);

namespace Cart\Conditions;

/**
 * Shipping condition for adding shipping costs.
 */
class ShippingCondition extends FixedCondition
{
    protected string $type = 'shipping';

    protected int $order = 200;

    /**
     * Free shipping threshold in cents.
     */
    protected ?int $freeShippingThreshold;

    /**
     * @param string $name Unique name for this shipping option
     * @param int $amount The shipping cost in cents
     * @param int|null $freeShippingThreshold Order amount in cents for free shipping
     */
    public function __construct(
        string $name,
        int $amount,
        ?int $freeShippingThreshold = null,
    ) {
        parent::__construct($name, $amount, [
            'freeShippingThreshold' => $freeShippingThreshold,
        ]);

        $this->freeShippingThreshold = $freeShippingThreshold;
        $this->target = 'subtotal';
    }

    /**
     * Get the free shipping threshold.
     */
    public function getFreeShippingThreshold(): ?int
    {
        return $this->freeShippingThreshold;
    }

    /**
     * Check if free shipping applies for the given amount.
     */
    public function hasFreeShipping(int $subtotalCents): bool
    {
        return $this->freeShippingThreshold !== null
            && $subtotalCents >= $this->freeShippingThreshold;
    }

    /**
     * {@inheritdoc}
     */
    public function calculate(int $valueCents): int
    {
        if ($this->hasFreeShipping($valueCents)) {
            return $valueCents;
        }

        return $valueCents + $this->amount;
    }

    /**
     * {@inheritdoc}
     */
    public function getCalculatedValue(int $baseValueCents): int
    {
        if ($this->hasFreeShipping($baseValueCents)) {
            return 0;
        }

        return $this->amount;
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
            amount: (int) ($attrs['amount'] ?? 0),
            freeShippingThreshold: $attrs['freeShippingThreshold'] ?? null,
        );
    }
}
