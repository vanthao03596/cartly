<?php

declare(strict_types=1);

namespace Cart;

use Illuminate\Support\Collection;

/**
 * @extends Collection<string, CartItem>
 */
class CartItemCollection extends Collection
{
    /**
     * Find an item by its row ID.
     */
    public function find(string $rowId): ?CartItem
    {
        return $this->get($rowId);
    }

    /**
     * Find an item by buyable ID.
     */
    public function findByBuyableId(int|string $buyableId): ?CartItem
    {
        return $this->first(fn (CartItem $item) => $item->buyableId === $buyableId);
    }

    /**
     * Find all items by buyable type.
     */
    public function findByBuyableType(string $buyableType): self
    {
        /** @var self $filtered */
        $filtered = $this->filter(fn (CartItem $item) => $item->buyableType === $buyableType);

        return $filtered;
    }

    /**
     * Get the total quantity of all items.
     */
    public function totalQuantity(): int
    {
        return $this->sum(fn (CartItem $item) => $item->quantity);
    }

    /**
     * Check if an item with the given row ID exists.
     */
    public function hasRowId(string $rowId): bool
    {
        return $this->has($rowId);
    }

    /**
     * Get items grouped by buyable type.
     *
     * @return Collection<string, static>
     */
    public function groupByBuyableType(): Collection
    {
        return $this->groupBy(fn (CartItem $item) => $item->buyableType);
    }

    /**
     * Create from array of item data.
     *
     * @param array<int|string, array<string, mixed>> $items
     */
    public static function fromArray(array $items): self
    {
        $collection = new self();

        foreach ($items as $data) {
            $item = CartItem::fromArray($data);
            $collection->put($item->rowId, $item);
        }

        return $collection;
    }

    /**
     * Convert collection to array for storage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toStorageArray(): array
    {
        /** @var array<int, array<string, mixed>> $result */
        $result = $this->map(fn (CartItem $item) => $item->toArray())->values()->all();

        return $result;
    }
}
