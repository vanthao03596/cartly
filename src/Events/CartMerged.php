<?php

declare(strict_types=1);

namespace Cart\Events;

use Cart\CartContent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after guest cart is merged with user cart on login.
 */
class CartMerged
{
    use Dispatchable;

    public function __construct(
        public readonly CartContent $resultCart,
        public readonly int $itemsMerged,
        public readonly Authenticatable $user,
    ) {}
}
