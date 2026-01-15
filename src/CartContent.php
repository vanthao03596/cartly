<?php

declare(strict_types=1);

namespace Cart;

use Cart\Contracts\Condition;
use Illuminate\Support\Collection;

class CartContent
{
    /**
     * The cart items.
     */
    public CartItemCollection $items;

    /**
     * Cart-level conditions.
     *
     * @var Collection<string, Condition>
     */
    public Collection $conditions;

    /**
     * Cart metadata.
     *
     * @var array<string, mixed>
     */
    public array $meta;

    /**
     * @param CartItemCollection|null $items
     * @param Collection<string, Condition>|null $conditions
     * @param array<string, mixed> $meta
     */
    public function __construct(
        ?CartItemCollection $items = null,
        ?Collection $conditions = null,
        array $meta = [],
    ) {
        $this->items = $items ?? new CartItemCollection();
        $this->conditions = $conditions ?? collect();
        $this->meta = $meta;
    }

    /**
     * Check if the cart is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Check if the cart is not empty.
     */
    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    /**
     * Get the count of unique items.
     */
    public function countItems(): int
    {
        return $this->items->count();
    }

    /**
     * Get the total quantity of all items.
     */
    public function totalQuantity(): int
    {
        return $this->items->totalQuantity();
    }

    /**
     * Add a condition to the cart.
     */
    public function addCondition(Condition $condition): self
    {
        $this->conditions->put($condition->getName(), $condition);

        return $this;
    }

    /**
     * Remove a condition by name.
     */
    public function removeCondition(string $name): self
    {
        $this->conditions->forget($name);

        return $this;
    }

    /**
     * Check if a condition exists.
     */
    public function hasCondition(string $name): bool
    {
        return $this->conditions->has($name);
    }

    /**
     * Get a condition by name.
     */
    public function getCondition(string $name): ?Condition
    {
        return $this->conditions->get($name);
    }

    /**
     * Clear all conditions.
     */
    public function clearConditions(): self
    {
        $this->conditions = collect();

        return $this;
    }

    /**
     * Set a meta value.
     */
    public function setMeta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * Get a meta value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * Create from array (deserialization).
     *
     * @param array{items?: array<int, array<string, mixed>>, conditions?: array<int, array<string, mixed>>, meta?: array<string, mixed>} $data
     */
    public static function fromArray(array $data): self
    {
        $items = CartItemCollection::fromArray($data['items'] ?? []);

        $conditions = collect();
        if (isset($data['conditions'])) {
            foreach ($data['conditions'] as $conditionData) {
                if (isset($conditionData['class']) && class_exists($conditionData['class'])) {
                    /** @var class-string<Condition> $conditionClass */
                    $conditionClass = $conditionData['class'];
                    $condition = $conditionClass::fromArray($conditionData);
                    $conditions->put($condition->getName(), $condition);
                }
            }
        }

        return new self(
            items: $items,
            conditions: $conditions,
            meta: $data['meta'] ?? [],
        );
    }

    /**
     * Convert to array (serialization).
     *
     * @return array{items: array<int, array<string, mixed>>, conditions: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items->toStorageArray(),
            'conditions' => $this->conditions->map(fn (Condition $c) => $c->toArray())->values()->toArray(),
            'meta' => $this->meta,
        ];
    }

    /**
     * Convert to JSON.
     *
     * @throws \JsonException If JSON encoding fails
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_THROW_ON_ERROR);

        return $json;
    }

    /**
     * Create from JSON.
     * Returns empty cart if JSON is invalid (with warning logged).
     */
    public static function fromJson(string $json): self
    {
        if ($json === '' || $json === '{}' || $json === '[]') {
            return new self();
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            \Illuminate\Support\Facades\Log::warning('Cart: Failed to decode JSON content', [
                'error' => $e->getMessage(),
                'json_preview' => substr($json, 0, 100),
            ]);

            return new self();
        }

        if (!is_array($data)) {
            \Illuminate\Support\Facades\Log::warning('Cart: Decoded JSON is not an array');

            return new self();
        }

        return self::fromArray($data);
    }
}
