<?php

declare(strict_types=1);

namespace Cart;

final class ResolvedPrice
{
    /**
     * @param  int  $unitPrice  Current unit price in cents
     * @param  int  $originalPrice  Original/regular price in cents
     * @param  string|null  $priceSource  Source of price: 'base', 'sale', 'tier', etc.
     * @param  array<string, mixed>  $meta  Additional metadata
     */
    public function __construct(
        public readonly int $unitPrice,
        public readonly int $originalPrice,
        public readonly ?string $priceSource = null,
        public readonly array $meta = [],
    ) {}

    /**
     * Check if the current price is discounted from original.
     */
    public function hasDiscount(): bool
    {
        return $this->unitPrice < $this->originalPrice;
    }

    /**
     * Get the discount percentage (0-100).
     */
    public function discountPercent(): float
    {
        if ($this->originalPrice === 0) {
            return 0.0;
        }

        return round(
            (($this->originalPrice - $this->unitPrice) / $this->originalPrice) * 100,
            2
        );
    }

    /**
     * Get the discount amount in cents.
     */
    public function discountAmount(): int
    {
        return $this->originalPrice - $this->unitPrice;
    }

    /**
     * Create from array (for deserialization).
     *
     * @param  array{unitPrice: int, originalPrice: int, priceSource?: string|null, meta?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            unitPrice: $data['unitPrice'],
            originalPrice: $data['originalPrice'],
            priceSource: $data['priceSource'] ?? null,
            meta: $data['meta'] ?? [],
        );
    }

    /**
     * Convert to array (for serialization).
     *
     * @return array{unitPrice: int, originalPrice: int, priceSource: string|null, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'unitPrice' => $this->unitPrice,
            'originalPrice' => $this->originalPrice,
            'priceSource' => $this->priceSource,
            'meta' => $this->meta,
        ];
    }
}
