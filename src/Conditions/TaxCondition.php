<?php

declare(strict_types=1);

namespace Cart\Conditions;

/**
 * Tax condition for adding tax to cart totals.
 */
class TaxCondition extends PercentageCondition
{
    protected string $type = 'tax';

    protected int $order = 100;

    /**
     * Whether tax is included in the price.
     */
    protected bool $includedInPrice = false;

    /**
     * @param  string  $name  Unique name for this tax (e.g., 'VAT', 'GST')
     * @param  float  $rate  The tax rate percentage (0-100)
     * @param  bool  $includedInPrice  Whether tax is already included in prices
     * @param  string  $target  The target: 'subtotal' or 'item'
     *
     * @throws \InvalidArgumentException If rate is not between 0 and 100
     */
    public function __construct(
        string $name,
        float $rate,
        bool $includedInPrice = false,
        string $target = 'subtotal',
    ) {
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException("Tax rate must be between 0 and 100. Got: {$rate}");
        }

        parent::__construct($name, $rate, ['includedInPrice' => $includedInPrice]);
        $this->includedInPrice = $includedInPrice;
        $this->target = $target;
    }

    /**
     * Check if tax is included in prices.
     */
    public function isIncludedInPrice(): bool
    {
        return $this->includedInPrice;
    }

    /**
     * {@inheritdoc}
     *
     * When tax is included in price, this extracts the tax from the price.
     * When tax is excluded, this adds tax on top.
     */
    public function calculate(int $valueCents): int
    {
        if ($this->includedInPrice) {
            // Tax already included - value stays the same
            return $valueCents;
        }

        // Tax excluded - add tax on top
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    /**
     * {@inheritdoc}
     *
     * Returns the tax amount (always positive).
     */
    public function getCalculatedValue(int $baseValueCents): int
    {
        if ($this->includedInPrice) {
            // Extract tax from price: price - (price / (1 + rate/100))
            $subtotalExclTax = (int) round($baseValueCents * 100 / (100 + $this->rate));

            return $baseValueCents - $subtotalExclTax;
        }

        // Calculate tax to add: base * rate / 100
        return (int) round($baseValueCents * $this->rate / 100);
    }

    /**
     * Get the subtotal excluding tax (when tax is included).
     */
    public function getSubtotalExcludingTax(int $totalCents): int
    {
        if (! $this->includedInPrice) {
            return $totalCents;
        }

        return (int) round($totalCents * 100 / (100 + $this->rate));
    }

    /**
     * {@inheritdoc}
     */
    public static function fromArray(array $data): static
    {
        $rate = $data['attributes']['rate'] ?? 0;
        $includedInPrice = $data['attributes']['includedInPrice'] ?? false;
        $target = $data['target'] ?? 'subtotal';

        /** @phpstan-ignore-next-line new.static */
        return new static(
            name: $data['name'],
            rate: (float) $rate,
            includedInPrice: (bool) $includedInPrice,
            target: $target,
        );
    }
}
