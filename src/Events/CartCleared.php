<?php

declare(strict_types=1);

namespace Cart\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the cart is cleared.
 */
class CartCleared
{
    use Dispatchable;

    public function __construct(
        public readonly string $instance,
        public readonly int $itemsCleared,
    ) {}
}
