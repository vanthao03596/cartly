<?php

declare(strict_types=1);

namespace Cart\Contracts;

use Cart\CartContext;

interface Priceable
{
    /**
     * Get the current price of the buyable item in cents.
     * May vary based on context (user tier, time, etc.)
     */
    public function getBuyablePrice(?CartContext $context = null): int;

    /**
     * Get the original (regular) price of the buyable item in cents.
     */
    public function getBuyableOriginalPrice(): int;
}
