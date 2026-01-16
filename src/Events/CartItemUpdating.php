<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartItem;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before a cart item is updated.
 * Throw an exception to cancel the operation.
 */
class CartItemUpdating
{
    use Dispatchable;

    /**
     * @param  string  $instance  The cart instance name
     * @param  CartItem  $item  The item being updated
     * @param  array<string, mixed>  $changes  The changes being applied
     */
    public function __construct(
        public readonly string $instance,
        public readonly CartItem $item,
        public readonly array $changes,
    ) {}
}
