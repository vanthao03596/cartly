<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartItem;
use Cart\Contracts\Buyable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before an item is added to the cart.
 * Throw an exception to cancel the operation.
 */
class CartItemAdding
{
    use Dispatchable;

    public function __construct(
        public readonly string $instance,
        public readonly CartItem $item,
        public readonly ?Buyable $buyable = null,
    ) {}
}
