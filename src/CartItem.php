<?php

declare(strict_types=1);

namespace Cart;

use Cart\Contracts\Buyable;
use Cart\Contracts\Condition;
use Cart\Exceptions\UnresolvablePriceException;
use Illuminate\Support\Collection;

class CartItem
{
    /**
     * The unique identifier for this cart row (hash of id + options).
     */
    public readonly string $rowId;

    /**
     * The buyable identifier.
     */
    public readonly int|string $id;

    /**
     * The quantity of this item.
     */
    public int $quantity;

    /**
     * Item options (size, color, variant, etc.).
     *
     * @var Collection<string, mixed>
     */
    public Collection $options;

    /**
     * The buyable model class name.
     */
    public readonly ?string $buyableType;

    /**
     * The buyable model ID.
     */
    public readonly int|string|null $buyableId;

    /**
     * Custom metadata for this item.
     *
     * @var Collection<string, mixed>
     */
    public Collection $meta;

    /**
     * Cached resolved price.
     */
    protected ?ResolvedPrice $resolvedPrice = null;

    /**
     * Cached buyable model.
     */
    protected ?Buyable $buyableModel = null;

    /**
     * Item-level conditions.
     *
     * @var Collection<string, Condition>
     */
    protected Collection $conditions;

    /**
     * Callback to trigger batch price resolution.
     */
    protected ?\Closure $priceResolutionCallback = null;

    /**
     * Callback to trigger batch model loading.
     */
    protected ?\Closure $modelLoadingCallback = null;

    /**
     * @param  int|string  $id  The buyable identifier
     * @param  int  $quantity  The quantity
     * @param  array<string, mixed>  $options  Item options
     * @param  string|null  $buyableType  The buyable model class
     * @param  int|string|null  $buyableId  The buyable model ID
     * @param  array<string, mixed>  $meta  Custom metadata
     */
    public function __construct(
        int|string $id,
        int $quantity = 1,
        array $options = [],
        ?string $buyableType = null,
        int|string|null $buyableId = null,
        array $meta = [],
    ) {
        $this->id = $id;
        $this->quantity = $quantity;
        $this->options = collect($options);
        $this->buyableType = $buyableType;
        $this->buyableId = $buyableId ?? $id;
        $this->meta = collect($meta);
        $this->conditions = collect();
        $this->rowId = $this->generateRowId();
    }

    /**
     * Create a CartItem from a Buyable model.
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $meta
     */
    public static function fromBuyable(Buyable $buyable, int $quantity = 1, array $options = [], array $meta = []): self
    {
        return new self(
            id: $buyable->getBuyableIdentifier(),
            quantity: $quantity,
            options: $options,
            buyableType: $buyable->getBuyableType(),
            buyableId: $buyable->getBuyableIdentifier(),
            meta: $meta,
        );
    }

    /**
     * Create from an array (for deserialization).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $item = new self(
            id: $data['id'],
            quantity: $data['quantity'] ?? 1,
            options: $data['options'] ?? [],
            buyableType: $data['buyableType'] ?? null,
            buyableId: $data['buyableId'] ?? $data['id'],
            meta: $data['meta'] ?? [],
        );

        // Restore conditions if present
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            foreach ($data['conditions'] as $conditionData) {
                if (isset($conditionData['class']) && class_exists($conditionData['class'])) {
                    /** @var class-string<Condition> $conditionClass */
                    $conditionClass = $conditionData['class'];
                    $condition = $conditionClass::fromArray($conditionData);
                    $item->conditions->put($condition->getName(), $condition);
                }
            }
        }

        return $item;
    }

    /**
     * Generate a unique row ID based on id and options.
     */
    protected function generateRowId(): string
    {
        $sorted = $this->options->sortKeys()->toArray();

        return hash('xxh128', $this->id.json_encode($sorted));
    }

    /**
     * Get the associated buyable model (lazy loaded).
     *
     * If a model loading callback is set, it will trigger batch loading
     * for all cart items on first access, avoiding N+1 queries.
     */
    public function model(): ?Buyable
    {
        if ($this->buyableModel !== null) {
            return $this->buyableModel;
        }

        // Trigger batch loading if callback is set
        if ($this->modelLoadingCallback !== null) {
            ($this->modelLoadingCallback)();

            // Check if model was loaded by batch
            if ($this->buyableModel !== null) {
                return $this->buyableModel;
            }
        }

        // Fallback to individual query (for edge cases or when no callback)
        if ($this->buyableType === null) {
            return null;
        }

        if (! class_exists($this->buyableType)) {
            return null;
        }

        /** @var class-string<Buyable&\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $this->buyableType;
        /** @var Buyable|null $model */
        $model = $modelClass::find($this->buyableId);
        $this->buyableModel = $model;

        return $this->buyableModel;
    }

    /**
     * Set the buyable model (for pre-loading).
     */
    public function setModel(Buyable $model): self
    {
        $this->buyableModel = $model;

        return $this;
    }

    /**
     * Get the resolved price.
     *
     * @throws UnresolvablePriceException
     */
    public function getResolvedPrice(): ResolvedPrice
    {
        $resolvedPrice = $this->resolvedPrice;
        if ($resolvedPrice === null) {
            // Trigger batch resolution if callback is set
            if ($this->priceResolutionCallback !== null) {
                ($this->priceResolutionCallback)();
            }

            $resolvedPrice = $this->resolvedPrice;
            if ($resolvedPrice === null) {
                throw UnresolvablePriceException::forItem($this->rowId, $this->buyableType, $this->buyableId);
            }
        }

        return $resolvedPrice;
    }

    /**
     * Set the resolved price.
     */
    public function setResolvedPrice(ResolvedPrice $price): self
    {
        $this->resolvedPrice = $price;

        return $this;
    }

    /**
     * Check if the price has been resolved.
     */
    public function hasPriceResolved(): bool
    {
        return $this->resolvedPrice !== null;
    }

    /**
     * Clear the resolved price (for cache invalidation).
     */
    public function clearResolvedPrice(): self
    {
        $this->resolvedPrice = null;

        return $this;
    }

    /**
     * Set the price resolution callback.
     */
    public function setPriceResolutionCallback(callable $callback): self
    {
        $this->priceResolutionCallback = $callback;

        return $this;
    }

    /**
     * Set the model loading callback.
     */
    public function setModelLoadingCallback(callable $callback): self
    {
        $this->modelLoadingCallback = $callback;

        return $this;
    }

    /**
     * Check if the model has been loaded.
     */
    public function hasModelLoaded(): bool
    {
        return $this->buyableModel !== null;
    }

    /**
     * Get the unit price in cents.
     *
     * @throws UnresolvablePriceException
     */
    public function unitPrice(): int
    {
        return $this->getResolvedPrice()->unitPrice;
    }

    /**
     * Get the original unit price in cents.
     *
     * @throws UnresolvablePriceException
     */
    public function originalUnitPrice(): int
    {
        return $this->getResolvedPrice()->originalPrice;
    }

    /**
     * Get the subtotal (unitPrice * quantity) in cents.
     *
     * @throws UnresolvablePriceException
     */
    public function subtotal(): int
    {
        return $this->unitPrice() * $this->quantity;
    }

    /**
     * Get the original subtotal in cents.
     *
     * @throws UnresolvablePriceException
     */
    public function originalSubtotal(): int
    {
        return $this->originalUnitPrice() * $this->quantity;
    }

    /**
     * Get the savings (original - current) in cents.
     *
     * @throws UnresolvablePriceException
     */
    public function savings(): int
    {
        return $this->originalSubtotal() - $this->subtotal();
    }

    /**
     * Get the subtotal after applying item-level conditions.
     *
     * @throws UnresolvablePriceException
     */
    public function total(): int
    {
        $subtotal = $this->subtotal();

        if ($this->conditions->isEmpty()) {
            return $subtotal;
        }

        // Sort conditions by order and apply
        $sortedConditions = $this->conditions->sortBy(fn (Condition $c) => $c->getOrder());

        $total = $subtotal;
        foreach ($sortedConditions as $condition) {
            $total = $condition->calculate($total);
        }

        return $total;
    }

    /**
     * Add a condition to this item.
     */
    public function condition(Condition $condition): self
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
     * Check if item has a condition.
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
     * Get all conditions.
     *
     * @return Collection<string, Condition>
     */
    public function getConditions(): Collection
    {
        return $this->conditions;
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
     * Update the quantity.
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Set or update an option.
     */
    public function setOption(string $key, mixed $value): self
    {
        $this->options->put($key, $value);

        return $this;
    }

    /**
     * Set or update meta value.
     */
    public function setMeta(string $key, mixed $value): self
    {
        $this->meta->put($key, $value);

        return $this;
    }

    /**
     * Convert to array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rowId' => $this->rowId,
            'id' => $this->id,
            'quantity' => $this->quantity,
            'options' => $this->options->toArray(),
            'buyableType' => $this->buyableType,
            'buyableId' => $this->buyableId,
            'meta' => $this->meta->toArray(),
            'conditions' => $this->conditions->map(fn (Condition $c) => $c->toArray())->values()->toArray(),
        ];
    }
}
