<?php

declare(strict_types=1);

namespace Cart\Contracts;

use Cart\CartInstance;

interface Condition
{
    /**
     * Get the unique name of the condition.
     */
    public function getName(): string;

    /**
     * Get the type of condition: 'tax', 'discount', 'shipping', 'fee'.
     */
    public function getType(): string;

    /**
     * Get the target: 'subtotal', 'total', or 'item'.
     */
    public function getTarget(): string;

    /**
     * Get the order/priority for applying conditions.
     * Lower values are applied first.
     */
    public function getOrder(): int;

    /**
     * Calculate and return the new value after applying the condition.
     *
     * @param  int  $valueCents  The input value in cents
     * @return int The resulting value in cents after condition is applied
     */
    public function calculate(int $valueCents): int;

    /**
     * Get the calculated adjustment value (positive or negative).
     *
     * @param  int  $baseValueCents  The base value to calculate against
     * @return int The adjustment amount in cents (can be negative for discounts)
     */
    public function getCalculatedValue(int $baseValueCents): int;

    /**
     * Serialize the condition to an array for storage.
     *
     * @return array{class: string, name: string, type: string, target: string, order: int, attributes: array<string, mixed>}
     */
    public function toArray(): array;

    /**
     * Create a condition instance from an array.
     *
     * @param  array{class?: string, name: string, type?: string, target?: string, order?: int, attributes?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data): static;

    /**
     * Check if the condition is valid against the current cart state.
     *
     * This method is called when the cart is loaded from storage.
     * Invalid conditions may be automatically removed based on configuration.
     *
     * @param  CartInstance|null  $cart  The cart instance to validate against
     * @return bool True if the condition is valid, false otherwise
     */
    public function isValid(?CartInstance $cart = null): bool;

    /**
     * Get the validation error message if the condition is invalid.
     *
     * Note: These messages are intended for logging/debugging purposes.
     * Applications should provide user-friendly translations for end-users.
     *
     * @return string|null The error message, or null if valid
     */
    public function getValidationError(): ?string;
}
