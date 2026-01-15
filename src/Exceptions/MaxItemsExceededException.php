<?php

declare(strict_types=1);

namespace Cart\Exceptions;

class MaxItemsExceededException extends CartException
{
    /**
     * The maximum allowed items.
     */
    protected int $maxItems;

    /**
     * The current item count.
     */
    protected int $currentCount;

    /**
     * Create a new exception for exceeding max items.
     */
    public static function forInstance(string $instance, int $maxItems, int $currentCount): self
    {
        $exception = new self(
            "Cannot add item: cart instance [{$instance}] has reached its maximum of {$maxItems} items."
        );
        $exception->maxItems = $maxItems;
        $exception->currentCount = $currentCount;
        $exception->setInstance($instance);

        return $exception;
    }

    /**
     * Get the maximum allowed items.
     */
    public function getMaxItems(): int
    {
        return $this->maxItems;
    }

    /**
     * Get the current item count.
     */
    public function getCurrentCount(): int
    {
        return $this->currentCount;
    }
}
