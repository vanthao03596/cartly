<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartContent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before guest cart is merged with user cart on login.
 * Throw an exception to cancel the merge.
 */
class CartMerging
{
    use Dispatchable;

    public function __construct(
        public readonly CartContent $guestCart,
        public readonly CartContent $userCart,
        public readonly string $strategy,
        public readonly Authenticatable $user,
    ) {}
}
