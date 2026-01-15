<?php

declare(strict_types=1);

namespace Cart\Contracts;

use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\ResolvedPrice;

interface PriceResolver
{
    /**
     * Resolve the price for a single cart item.
     */
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice;

    /**
     * Resolve prices for multiple cart items in batch.
     * Returns associative array where key is rowId.
     *
     * @param CartItemCollection $items
     * @param CartContext $context
     * @return array<string, ResolvedPrice>
     */
    public function resolveMany(CartItemCollection $items, CartContext $context): array;
}
