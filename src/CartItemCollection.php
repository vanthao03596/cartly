<?php

declare(strict_types=1);

namespace Cart;

use Cart\Contracts\Buyable;
use Illuminate\Database\Eloquent\Model;
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
     * Batch load models for all items.
     *
     * Groups items by buyable type and uses whereIn for efficient loading,
     * avoiding N+1 query problems when accessing item models.
     */
    public function loadModels(): void
    {
        if ($this->isEmpty()) {
            return;
        }

        // Skip items that already have models loaded
        $unloaded = $this->filter(fn (CartItem $item) => !$item->hasModelLoaded());

        if ($unloaded->isEmpty()) {
            return;
        }

        // Group by buyable type for batch loading
        $grouped = $unloaded->groupByBuyableType();

        foreach ($grouped as $buyableType => $items) {
            $buyableTypeStr = (string) $buyableType;

            if ($buyableTypeStr === '' || !class_exists($buyableTypeStr)) {
                continue;
            }

            // Get unique IDs
            $ids = $items->pluck('buyableId')->filter()->unique()->values()->all();

            if (empty($ids)) {
                continue;
            }

            // Batch load models using findMany (handles custom primary keys)
            /** @var class-string<Buyable&Model> $buyableTypeStr */
            $models = $buyableTypeStr::findMany($ids)->keyBy(
                fn (Model $model): int|string => $model instanceof Buyable
                    ? $model->getBuyableIdentifier()
                    : $model->getKey()
            );

            // Assign models to items
            foreach ($items as $item) {
                $model = $models->get($item->buyableId);
                if ($model !== null && $model instanceof Buyable) {
                    $item->setModel($model);
                }
            }
        }
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
