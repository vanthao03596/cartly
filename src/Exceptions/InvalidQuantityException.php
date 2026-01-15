<?php

declare(strict_types=1);

namespace Cart\Exceptions;

class InvalidQuantityException extends CartException
{
    /**
     * The invalid quantity that was provided.
     */
    protected int $quantity;

    /**
     * Create a new exception for an invalid quantity.
     */
    public static function forQuantity(int $quantity): self
    {
        $exception = new self("The quantity [{$quantity}] is invalid. Quantity must be at least 1.");
        $exception->quantity = $quantity;

        return $exception;
    }

    /**
     * Get the invalid quantity.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
