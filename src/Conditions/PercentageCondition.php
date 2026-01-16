<?php

declare(strict_types=1);

namespace Cart\Conditions;

/**
 * Base class for percentage-based conditions.
 */
abstract class PercentageCondition extends BaseCondition
{
    /**
     * The percentage rate.
     */
    protected float $rate;

    /**
     * Whether this is a negative adjustment (discount).
     */
    protected bool $negative = false;

    /**
     * @param  string  $name  Unique name for this condition
     * @param  float  $rate  The percentage rate (0-100)
     * @param  array<string, mixed>  $attributes  Additional attributes
     *
     * @throws \InvalidArgumentException If rate is negative
     */
    public function __construct(string $name, float $rate, array $attributes = [])
    {
        if ($rate < 0) {
            throw new \InvalidArgumentException("Percentage rate cannot be negative. Got: {$rate}");
        }

        parent::__construct($name, $attributes);
        $this->rate = $rate;
        $this->attributes['rate'] = $rate;
    }

    /**
     * Get the percentage rate.
     */
    public function getRate(): float
    {
        return $this->rate;
    }

    /**
     * {@inheritdoc}
     */
    public function calculate(int $valueCents): int
    {
        $adjustment = $this->getCalculatedValue($valueCents);

        return $valueCents + $adjustment;
    }

    /**
     * {@inheritdoc}
     */
    public function getCalculatedValue(int $baseValueCents): int
    {
        $value = (int) round($baseValueCents * $this->rate / 100);

        return $this->negative ? -$value : $value;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromArray(array $data): static
    {
        $rate = $data['attributes']['rate'] ?? 0;

        /** @phpstan-ignore-next-line new.static */
        return new static(
            name: $data['name'],
            rate: (float) $rate,
            attributes: $data['attributes'] ?? [],
        );
    }
}
