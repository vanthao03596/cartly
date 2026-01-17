<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\Contracts\Condition;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a condition is automatically invalidated and removed from the cart.
 */
class CartConditionInvalidated
{
    use Dispatchable;

    /**
     * @param  string  $instance  The cart instance name
     * @param  Condition  $condition  The invalidated condition
     * @param  string|null  $reason  Debug message (not for end-user display)
     */
    public function __construct(
        public readonly string $instance,
        public readonly Condition $condition,
        public readonly ?string $reason,
    ) {}
}
