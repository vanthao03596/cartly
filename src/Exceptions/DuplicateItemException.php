<?php

declare(strict_types=1);

namespace Cart\Exceptions;

class DuplicateItemException extends CartException
{
    /**
     * The buyable ID that was duplicated.
     */
    protected int|string $buyableId;

    /**
     * The existing row ID.
     */
    protected string $existingRowId;

    /**
     * Create a new exception for duplicate item.
     */
    public static function forBuyable(string $instance, int|string $buyableId, string $existingRowId): self
    {
        $exception = new self(
            "Cannot add item: cart instance [{$instance}] does not allow duplicates and item [{$buyableId}] already exists."
        );
        $exception->buyableId = $buyableId;
        $exception->existingRowId = $existingRowId;
        $exception->setInstance($instance);

        return $exception;
    }

    /**
     * Get the buyable ID.
     */
    public function getBuyableId(): int|string
    {
        return $this->buyableId;
    }

    /**
     * Get the existing row ID.
     */
    public function getExistingRowId(): string
    {
        return $this->existingRowId;
    }
}
