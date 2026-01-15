<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartItem;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a cart item is updated.
 */
class CartItemUpdated
{
    use Dispatchable;

    /**
     * @param string $instance The cart instance name
     * @param CartItem $item The updated item
     * @param array<string, mixed> $changes The changes that were applied
     */
    public function __construct(
        public readonly string $instance,
        public readonly CartItem $item,
        public readonly array $changes,
    ) {}
}
