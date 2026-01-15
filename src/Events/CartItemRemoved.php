<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartItem;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a cart item is removed.
 */
class CartItemRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly string $instance,
        public readonly CartItem $item,
    ) {}
}
