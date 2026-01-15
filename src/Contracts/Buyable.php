<?php

declare(strict_types=1);

namespace Cart\Contracts;

interface Buyable
{
    /**
     * Get the identifier of the buyable item.
     */
    public function getBuyableIdentifier(): int|string;

    /**
     * Get the description of the buyable item.
     */
    public function getBuyableDescription(): string;

    /**
     * Get the type (class name) of the buyable item.
     */
    public function getBuyableType(): string;
}
