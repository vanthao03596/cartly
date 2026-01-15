<?php

declare(strict_types=1);

namespace Cart\Exceptions;

class InvalidRowIdException extends CartException
{
    /**
     * The invalid row ID that was provided.
     */
    protected string $rowId;

    /**
     * Create a new exception for an invalid row ID.
     */
    public static function forRowId(string $rowId): self
    {
        $exception = new self("The cart does not contain an item with rowId [{$rowId}].");
        $exception->rowId = $rowId;

        return $exception;
    }

    /**
     * Get the invalid row ID.
     */
    public function getRowId(): string
    {
        return $this->rowId;
    }
}
