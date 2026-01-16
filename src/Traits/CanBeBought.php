<?php

declare(strict_types=1);

namespace Cart\Traits;

use Cart\CartContext;
use Cart\Contracts\Buyable;
use Cart\Contracts\Priceable;

/**
 * Trait for Eloquent models that can be added to a cart.
 * Implements both Buyable and Priceable interfaces.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait CanBeBought // @phpstan-ignore trait.unused
{
    /**
     * Get the identifier of the buyable item.
     * Defaults to the model's primary key.
     */
    public function getBuyableIdentifier(): int|string
    {
        return $this->getKey();
    }

    /**
     * Get the description of the buyable item.
     * Override this method to customize the description.
     */
    public function getBuyableDescription(): string
    {
        // Try common attribute names
        foreach (['name', 'title', 'description'] as $attribute) {
            if (isset($this->{$attribute})) {
                return (string) $this->{$attribute};
            }
        }

        return static::class.' #'.$this->getBuyableIdentifier();
    }

    /**
     * Get the type (class name) of the buyable item.
     */
    public function getBuyableType(): string
    {
        return static::class;
    }

    /**
     * Get the current price of the buyable item in cents.
     * Override this method for dynamic pricing.
     */
    public function getBuyablePrice(?CartContext $context = null): int
    {
        // Try common price attribute names
        foreach (['price', 'sale_price', 'current_price'] as $attribute) {
            if (isset($this->{$attribute})) {
                $price = $this->{$attribute};

                // If it's already an integer, return it
                if (is_int($price)) {
                    return $price;
                }

                // If it's a float/decimal, convert to cents
                if (is_float($price) || is_numeric($price)) {
                    return (int) round((float) $price * 100);
                }
            }
        }

        return 0;
    }

    /**
     * Get the original (regular) price of the buyable item in cents.
     * Override this method if you have sale prices.
     */
    public function getBuyableOriginalPrice(): int
    {
        // Try common original price attribute names
        foreach (['original_price', 'regular_price', 'price'] as $attribute) {
            if (isset($this->{$attribute})) {
                $price = $this->{$attribute};

                if (is_int($price)) {
                    return $price;
                }

                if (is_float($price) || is_numeric($price)) {
                    return (int) round((float) $price * 100);
                }
            }
        }

        // Fall back to current price
        return $this->getBuyablePrice();
    }

    /**
     * Check if the model is on sale.
     */
    public function isOnSale(): bool
    {
        return $this->getBuyablePrice() < $this->getBuyableOriginalPrice();
    }

    /**
     * Get the discount percentage.
     */
    public function getDiscountPercent(): float
    {
        $original = $this->getBuyableOriginalPrice();

        if ($original === 0) {
            return 0.0;
        }

        $current = $this->getBuyablePrice();

        return round((($original - $current) / $original) * 100, 2);
    }
}
