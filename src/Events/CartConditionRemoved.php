<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\Contracts\Condition;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a condition is removed from the cart.
 */
class CartConditionRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly string $instance,
        public readonly Condition $condition,
    ) {}
}
