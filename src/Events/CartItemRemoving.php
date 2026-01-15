<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartItem;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before a cart item is removed.
 * Throw an exception to cancel the operation.
 */
class CartItemRemoving
{
    use Dispatchable;

    public function __construct(
        public readonly string $instance,
        public readonly CartItem $item,
    ) {}
}
