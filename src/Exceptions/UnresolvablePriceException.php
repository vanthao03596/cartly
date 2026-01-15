<?php

declare(strict_types=1);

namespace Cart\Exceptions;

class UnresolvablePriceException extends CartException
{
    /**
     * The row ID of the item with unresolvable price.
     */
    protected ?string $rowId = null;

    /**
     * The buyable type that failed to resolve.
     */
    protected ?string $buyableType = null;

    /**
     * The buyable ID that failed to resolve.
     */
    protected int|string|null $buyableId = null;

    /**
     * Create a new exception for an unresolvable price.
     */
    public static function forItem(string $rowId, ?string $buyableType = null, int|string|null $buyableId = null): self
    {
        $message = "Unable to resolve price for cart item [{$rowId}]";

        if ($buyableType !== null && $buyableId !== null) {
            $message .= " (type: {$buyableType}, id: {$buyableId})";
        }

        $exception = new self($message . '.');
        $exception->rowId = $rowId;
        $exception->buyableType = $buyableType;
        $exception->buyableId = $buyableId;

        return $exception;
    }

    /**
     * Create a new exception when the model is not found.
     */
    public static function modelNotFound(string $rowId, string $buyableType, int|string $buyableId): self
    {
        $exception = new self(
            "Cannot resolve price: Model [{$buyableType}] with ID [{$buyableId}] not found for item [{$rowId}]."
        );
        $exception->rowId = $rowId;
        $exception->buyableType = $buyableType;
        $exception->buyableId = $buyableId;

        return $exception;
    }

    /**
     * Create a new exception when the model doesn't implement Priceable.
     */
    public static function notPriceable(string $rowId, string $buyableType): self
    {
        $exception = new self(
            "Cannot resolve price: Model [{$buyableType}] does not implement Priceable interface for item [{$rowId}]."
        );
        $exception->rowId = $rowId;
        $exception->buyableType = $buyableType;

        return $exception;
    }

    /**
     * Get the row ID of the failed item.
     */
    public function getRowId(): ?string
    {
        return $this->rowId;
    }

    /**
     * Get the buyable type.
     */
    public function getBuyableType(): ?string
    {
        return $this->buyableType;
    }

    /**
     * Get the buyable ID.
     */
    public function getBuyableId(): int|string|null
    {
        return $this->buyableId;
    }
}
