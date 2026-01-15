<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartContent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before the cart is cleared.
 * Throw an exception to cancel the operation.
 */
class CartClearing
{
    use Dispatchable;

    public function __construct(
        public readonly string $instance,
        public readonly CartContent $content,
    ) {}
}
