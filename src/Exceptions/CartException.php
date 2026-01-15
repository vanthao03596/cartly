<?php

declare(strict_types=1);

namespace Cart\Exceptions;

use Exception;

class CartException extends Exception
{
    /**
     * The cart instance name where the error occurred.
     */
    protected ?string $instance = null;

    /**
     * Set the cart instance name.
     */
    public function setInstance(string $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    /**
     * Get the cart instance name.
     */
    public function getInstance(): ?string
    {
        return $this->instance;
    }
}
