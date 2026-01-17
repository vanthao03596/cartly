<?php

declare(strict_types=1);

namespace Cart;

use Cart\Conditions\TaxCondition;
use Cart\Contracts\Buyable;
use Cart\Contracts\Condition;
use Cart\Contracts\PriceResolver;
use Cart\Contracts\StorageDriver;
use Cart\Events\CartCleared;
use Cart\Events\CartClearing;
use Cart\Events\CartConditionAdded;
use Cart\Events\CartConditionInvalidated;
use Cart\Events\CartConditionRemoved;
use Cart\Events\CartItemAdded;
use Cart\Events\CartItemAdding;
use Cart\Events\CartItemRemoved;
use Cart\Events\CartItemRemoving;
use Cart\Events\CartItemUpdated;
use Cart\Events\CartItemUpdating;
use Cart\Exceptions\DuplicateItemException;
use Cart\Exceptions\InvalidQuantityException;
use Cart\Exceptions\InvalidRowIdException;
use Cart\Exceptions\MaxItemsExceededException;
use Cart\Support\CalculationPipeline;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

class CartInstance
{
    /**
     * The instance name.
     */
    protected string $instanceName;

    /**
     * The storage driver.
     */
    protected StorageDriver $driver;

    /**
     * The price resolver.
     */
    protected PriceResolver $resolver;

    /**
     * The user identifier for storage.
     */
    protected ?string $identifier = null;

    /**
     * The associated user.
     */
    protected ?Authenticatable $user = null;

    /**
     * Cached cart content.
     */
    protected ?CartContent $content = null;

    /**
     * Whether prices have been resolved for this request.
     */
    protected bool $pricesResolved = false;

    /**
     * Cached context hash.
     */
    protected ?string $contextHash = null;

    /**
     * Whether events are enabled.
     */
    protected bool $eventsEnabled = true;

    /**
     * Whether conditions have been validated for this request.
     */
    protected bool $conditionsValidated = false;

    public function __construct(
        string $instanceName,
        StorageDriver $driver,
        PriceResolver $resolver,
    ) {
        $this->instanceName = $instanceName;
        $this->driver = $driver;
        $this->resolver = $resolver;
    }

    /**
     * Add an item to the cart.
     *
     * @param  Buyable|int|string  $item  The buyable model or ID
     * @param  int  $quantity  The quantity to add
     * @param  array<string, mixed>  $options  Item options
     * @param  array<string, mixed>  $meta  Item metadata
     */
    public function add(
        Buyable|int|string $item,
        int $quantity = 1,
        array $options = [],
        array $meta = [],
    ): CartItem {
        if ($quantity < 1) {
            throw InvalidQuantityException::forQuantity($quantity);
        }

        $content = $this->getContent();

        // Create cart item first (will be reused for all checks)
        if ($item instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($item, $quantity, $options, $meta);
            $cartItem->setModel($item);
        } else {
            $cartItem = new CartItem(
                id: $item,
                quantity: $quantity,
                options: $options,
                meta: $meta,
            );
        }

        // Check if item already exists (same rowId) - if so, update quantity
        $isExistingItem = $content->items->hasRowId($cartItem->rowId);
        if ($isExistingItem) {
            $existing = $content->items->get($cartItem->rowId);
            $newQuantity = $existing->quantity + $quantity;

            return $this->update($cartItem->rowId, $newQuantity);
        }

        // From here on, we're adding a NEW item
        // Set callbacks only for new items (existing items already have them)
        $cartItem->setPriceResolutionCallback(fn () => $this->resolvePrices());
        $cartItem->setModelLoadingCallback(fn () => $this->loadModels());

        $instanceConfig = $this->getInstanceConfig();

        // Check max_items constraint (only for new items)
        $maxItems = $instanceConfig['max_items'] ?? null;
        if ($maxItems !== null && $content->countItems() >= $maxItems) {
            throw MaxItemsExceededException::forInstance(
                $this->instanceName,
                $maxItems,
                $content->countItems()
            );
        }

        // Check allow_duplicates constraint (only for new items)
        $allowDuplicates = $instanceConfig['allow_duplicates'] ?? true;
        if (! $allowDuplicates) {
            $buyableId = $cartItem->buyableId;
            $existingItem = $content->items->findByBuyableId($buyableId);

            if ($existingItem !== null) {
                throw DuplicateItemException::forBuyable(
                    $this->instanceName,
                    $buyableId,
                    $existingItem->rowId
                );
            }
        }

        // Dispatch adding event
        $this->dispatchEvent(new CartItemAdding(
            $this->instanceName,
            $cartItem,
            $item instanceof Buyable ? $item : null,
        ));

        // Add to content
        $content->items->put($cartItem->rowId, $cartItem);

        // Invalidate price cache
        $this->invalidatePriceCache();

        // Save to storage
        $this->saveContent();

        // Dispatch added event
        $this->dispatchEvent(new CartItemAdded($this->instanceName, $cartItem));

        return $cartItem;
    }

    /**
     * Update a cart item.
     *
     * @param  string  $rowId  The row ID
     * @param  int|array<string, mixed>  $attributes  Quantity or array of attributes
     */
    public function update(string $rowId, int|array $attributes): CartItem
    {
        $content = $this->getContent();

        if (! $content->items->hasRowId($rowId)) {
            throw InvalidRowIdException::forRowId($rowId);
        }

        $item = $content->items->get($rowId);

        // Normalize attributes
        if (is_int($attributes)) {
            $attributes = ['quantity' => $attributes];
        }

        // Validate quantity if provided
        if (isset($attributes['quantity']) && $attributes['quantity'] < 1) {
            throw InvalidQuantityException::forQuantity($attributes['quantity']);
        }

        // Dispatch updating event
        $this->dispatchEvent(new CartItemUpdating($this->instanceName, $item, $attributes));

        // Apply updates
        if (isset($attributes['quantity'])) {
            $item->setQuantity($attributes['quantity']);
        }

        if (isset($attributes['options']) && is_array($attributes['options'])) {
            foreach ($attributes['options'] as $key => $value) {
                $item->setOption($key, $value);
            }
        }

        if (isset($attributes['meta']) && is_array($attributes['meta'])) {
            foreach ($attributes['meta'] as $key => $value) {
                $item->setMeta($key, $value);
            }
        }

        // Invalidate price cache
        $this->invalidatePriceCache();

        // Save to storage
        $this->saveContent();

        // Dispatch updated event
        $this->dispatchEvent(new CartItemUpdated($this->instanceName, $item, $attributes));

        return $item;
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(string $rowId): void
    {
        $content = $this->getContent();

        if (! $content->items->hasRowId($rowId)) {
            throw InvalidRowIdException::forRowId($rowId);
        }

        $item = $content->items->get($rowId);

        // Dispatch removing event
        $this->dispatchEvent(new CartItemRemoving($this->instanceName, $item));

        // Remove from content
        $content->items->forget($rowId);

        // Invalidate price cache
        $this->invalidatePriceCache();

        // Save to storage
        $this->saveContent();

        // Dispatch removed event
        $this->dispatchEvent(new CartItemRemoved($this->instanceName, $item));
    }

    /**
     * Get a cart item by row ID.
     */
    public function get(string $rowId): ?CartItem
    {
        return $this->getContent()->items->get($rowId);
    }

    /**
     * Find an item by buyable ID.
     */
    public function find(int|string $buyableId): ?CartItem
    {
        return $this->getContent()->items->findByBuyableId($buyableId);
    }

    /**
     * Check if a row ID exists in the cart.
     */
    public function has(string $rowId): bool
    {
        return $this->getContent()->items->hasRowId($rowId);
    }

    /**
     * Get all cart items.
     */
    public function content(): CartItemCollection
    {
        return $this->getContent()->items;
    }

    /**
     * Check if the cart is empty.
     */
    public function isEmpty(): bool
    {
        return $this->getContent()->isEmpty();
    }

    /**
     * Check if the cart is not empty.
     */
    public function isNotEmpty(): bool
    {
        return $this->getContent()->isNotEmpty();
    }

    /**
     * Get the total quantity of items.
     */
    public function count(): int
    {
        return $this->getContent()->totalQuantity();
    }

    /**
     * Get the count of unique items.
     */
    public function countItems(): int
    {
        return $this->getContent()->countItems();
    }

    /**
     * Get the subtotal (before conditions) in cents.
     */
    public function subtotal(): int
    {
        $this->resolvePrices();

        $subtotal = 0;
        foreach ($this->getContent()->items as $item) {
            $subtotal += $item->total(); // Item total includes item-level conditions
        }

        return $subtotal;
    }

    /**
     * Get the total (after all conditions) in cents.
     */
    public function total(): int
    {
        $subtotal = $this->subtotal();

        return $this->createCalculationPipeline()->process($subtotal);
    }

    /**
     * Get detailed calculation breakdown.
     *
     * Returns an array containing:
     * - subtotal: The subtotal before cart-level conditions (in cents)
     * - total: The final total after all conditions (in cents)
     * - steps: Array of calculation steps showing each condition applied
     * - breakdown: Totals grouped by condition type (tax, discount, shipping, fee)
     *
     * @return array{subtotal: int, total: int, steps: array<int, array{name: string, type: string, order: int, before: int, after: int, change: int}>, breakdown: array<string, int>}
     */
    public function getCalculationBreakdown(): array
    {
        $subtotal = $this->subtotal();
        $pipeline = $this->createCalculationPipeline();
        $total = $pipeline->process($subtotal);

        return [
            'subtotal' => $subtotal,
            'total' => $total,
            'steps' => $pipeline->getSteps(),
            'breakdown' => $pipeline->getBreakdown(),
        ];
    }

    /**
     * Create a calculation pipeline with cart-level conditions.
     */
    protected function createCalculationPipeline(): CalculationPipeline
    {
        $conditions = $this->getEffectiveConditions()
            ->filter(fn (Condition $c) => in_array($c->getTarget(), ['subtotal', 'total'], true));

        return CalculationPipeline::make()->through($conditions);
    }

    /**
     * Get total savings (original - current) in cents.
     */
    public function savings(): int
    {
        $this->resolvePrices();

        $savings = 0;
        foreach ($this->getContent()->items as $item) {
            $savings += $item->savings();
        }

        return $savings;
    }

    /**
     * Get total of all conditions.
     */
    public function conditionsTotal(): int
    {
        return $this->total() - $this->subtotal();
    }

    /**
     * Get the total tax amount in cents.
     */
    public function taxTotal(): int
    {
        $subtotal = $this->subtotal();
        $total = 0;

        foreach ($this->getEffectiveConditions() as $condition) {
            if ($condition->getType() === 'tax') {
                $total += $condition->getCalculatedValue($subtotal);
            }
        }

        return $total;
    }

    /**
     * Get the total discount amount in cents (negative value).
     */
    public function discountTotal(): int
    {
        $subtotal = $this->subtotal();
        $total = 0;

        foreach ($this->getContent()->conditions as $condition) {
            if ($condition->getType() === 'discount') {
                $total += $condition->getCalculatedValue($subtotal);
            }
        }

        return $total;
    }

    /**
     * Add a condition to the cart.
     */
    public function condition(Condition $condition): void
    {
        $this->getContent()->addCondition($condition);
        $this->saveContent();

        $this->dispatchEvent(new CartConditionAdded($this->instanceName, $condition));
    }

    /**
     * Remove a condition by name.
     */
    public function removeCondition(string $name): void
    {
        $content = $this->getContent();
        $condition = $content->getCondition($name);

        if ($condition === null) {
            return;
        }

        $content->removeCondition($name);
        $this->saveContent();

        $this->dispatchEvent(new CartConditionRemoved($this->instanceName, $condition));
    }

    /**
     * Get a condition by name.
     */
    public function getCondition(string $name): ?Condition
    {
        return $this->getContent()->getCondition($name);
    }

    /**
     * Get all conditions.
     *
     * @return Collection<string, Condition>
     */
    public function getConditions(): Collection
    {
        return $this->getContent()->conditions;
    }

    /**
     * Check if a condition exists.
     */
    public function hasCondition(string $name): bool
    {
        return $this->getContent()->hasCondition($name);
    }

    /**
     * Clear all conditions.
     */
    public function clearConditions(): void
    {
        $this->getContent()->clearConditions();
        $this->saveContent();
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(): void
    {
        $content = $this->getContent();

        if ($content->isEmpty()) {
            return;
        }

        // Dispatch clearing event
        $this->dispatchEvent(new CartClearing($this->instanceName, $content));

        $itemCount = $content->countItems();

        // Clear items
        $content->items = new CartItemCollection;
        $this->invalidatePriceCache();
        $this->saveContent();

        // Dispatch cleared event
        $this->dispatchEvent(new CartCleared($this->instanceName, $itemCount));
    }

    /**
     * Destroy the cart (clear items and conditions, remove from storage).
     */
    public function destroy(): void
    {
        $this->clear();
        $this->clearConditions();
        $this->driver->forget($this->instanceName, $this->identifier);
        $this->content = null;
    }

    /**
     * Refresh all prices (invalidate cache and re-resolve).
     */
    public function refreshPrices(): void
    {
        $this->invalidatePriceCache();
        $this->resolvePrices();
    }

    /**
     * Load models for all cart items (batch operation).
     *
     * This method batch-loads all buyable models in a single query per type,
     * avoiding N+1 query problems when accessing item models.
     */
    public function loadModels(): void
    {
        $this->getContent()->items->loadModels();
    }

    /**
     * Set the storage driver.
     */
    public function setDriver(StorageDriver $driver): self
    {
        $this->driver = $driver;
        $this->content = null; // Clear cached content
        $this->conditionsValidated = false;

        return $this;
    }

    /**
     * Set the price resolver.
     */
    public function setPriceResolver(PriceResolver $resolver): self
    {
        $this->resolver = $resolver;
        $this->invalidatePriceCache();

        return $this;
    }

    /**
     * Associate a user with this cart instance.
     */
    public function associate(Authenticatable $user): self
    {
        $this->user = $user;
        $this->identifier = 'user_'.$user->getAuthIdentifier();
        $this->content = null; // Reload content for new identifier
        $this->conditionsValidated = false;

        return $this;
    }

    /**
     * Set the identifier for storage.
     */
    public function setIdentifier(?string $identifier): self
    {
        $this->identifier = $identifier;
        $this->content = null;
        $this->conditionsValidated = false;

        return $this;
    }

    /**
     * Get the current identifier.
     */
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * Get the instance name.
     */
    public function getInstanceName(): string
    {
        return $this->instanceName;
    }

    /**
     * Enable or disable events.
     */
    public function setEventsEnabled(bool $enabled): self
    {
        $this->eventsEnabled = $enabled;

        return $this;
    }

    /**
     * Move an item to another cart instance.
     */
    public function moveTo(string $rowId, CartInstance $targetInstance): CartItem
    {
        $item = $this->get($rowId);

        if ($item === null) {
            throw InvalidRowIdException::forRowId($rowId);
        }

        // Add to target
        $newItem = $targetInstance->add(
            item: $item->id,
            quantity: $item->quantity,
            options: $item->options->toArray(),
            meta: $item->meta->toArray(),
        );

        // Remove from source
        $this->remove($rowId);

        return $newItem;
    }

    /**
     * Get the cart content.
     */
    protected function getContent(): CartContent
    {
        if ($this->content === null) {
            // IMPORTANT: Assign content BEFORE validateConditions() to prevent circular calls.
            // validateConditions() -> isValid() -> subtotal() -> getContent()
            // Without this order, an infinite loop would occur.
            $this->content = $this->driver->get($this->instanceName, $this->identifier)
                ?? new CartContent;

            // Set callbacks on all items
            foreach ($this->content->items as $item) {
                $item->setPriceResolutionCallback(fn () => $this->resolvePrices());
                $item->setModelLoadingCallback(fn () => $this->loadModels());
            }

            // Validate conditions after loading from storage
            $this->validateConditions();
        }

        return $this->content;
    }

    /**
     * Validate all conditions and remove invalid ones.
     *
     * @return array<string, Condition> Array of invalidated conditions keyed by name
     */
    protected function validateConditions(): array
    {
        if ($this->conditionsValidated) {
            return [];
        }

        $this->conditionsValidated = true;

        if (! config('cart.conditions.auto_remove_invalid', true)) {
            return [];
        }

        $content = $this->content;
        if ($content === null || $content->conditions->isEmpty()) {
            return [];
        }

        $invalidated = [];

        foreach ($content->conditions as $name => $condition) {
            if (! $condition->isValid($this)) {
                $invalidated[$name] = $condition;
            }
        }

        foreach ($invalidated as $name => $condition) {
            $content->removeCondition($name);

            $this->dispatchEvent(new CartConditionInvalidated(
                $this->instanceName,
                $condition,
                $condition->getValidationError()
            ));
        }

        if (! empty($invalidated)) {
            $this->saveContent();
        }

        return $invalidated;
    }

    /**
     * Save the cart content to storage.
     */
    protected function saveContent(): void
    {
        if ($this->content !== null) {
            $this->driver->put($this->instanceName, $this->content, $this->identifier);
        }
    }

    /**
     * Resolve prices for all items.
     */
    protected function resolvePrices(): void
    {
        $content = $this->getContent();

        if ($content->isEmpty()) {
            return;
        }

        // Check if we need to resolve
        $context = $this->createContext();
        $contextHash = $context->hash();

        if ($this->pricesResolved && $this->contextHash === $contextHash) {
            return;
        }

        // Collect unresolved items
        $unresolved = $content->items->filter(fn (CartItem $item) => ! $item->hasPriceResolved());

        if ($unresolved->isEmpty()) {
            $this->pricesResolved = true;
            $this->contextHash = $contextHash;

            return;
        }

        // Batch resolve
        $resolved = $this->resolver->resolveMany($unresolved, $context);

        // Apply resolved prices
        foreach ($resolved as $rowId => $price) {
            $item = $content->items->get($rowId);
            if ($item !== null) {
                $item->setResolvedPrice($price);
            }
        }

        $this->pricesResolved = true;
        $this->contextHash = $contextHash;
    }

    /**
     * Invalidate the price cache.
     */
    protected function invalidatePriceCache(): void
    {
        $this->pricesResolved = false;
        $this->contextHash = null;

        foreach ($this->getContent()->items as $item) {
            $item->clearResolvedPrice();
        }
    }

    /**
     * Create a cart context.
     */
    protected function createContext(): CartContext
    {
        return new CartContext(
            user: $this->user,
            instance: $this->instanceName,
            currency: config('cart.format.currency_symbol', '$'),
            locale: app()->getLocale(),
        );
    }

    /**
     * Get the configuration for this cart instance.
     *
     * @return array{conditions?: array<int, string>, max_items?: int|null, allow_duplicates?: bool}
     */
    protected function getInstanceConfig(): array
    {
        return config("cart.instances.{$this->instanceName}", []);
    }

    /**
     * Get all effective conditions (including instance and global config conditions).
     *
     * Priority: Manual conditions > Instance conditions > Global tax config
     *
     * @return Collection<string, Condition>
     */
    protected function getEffectiveConditions(): Collection
    {
        // Clone to avoid modifying original collection
        $conditions = clone $this->getContent()->conditions;

        // 1. Add instance conditions from config (if not already exists)
        foreach ($this->getInstanceConditions() as $condition) {
            if (! $conditions->has($condition->getName())) {
                $conditions->put($condition->getName(), $condition);
            }
        }

        // 2. Add global config tax (if enabled and no tax condition exists)
        $configTax = $this->getConfigTaxCondition();
        if ($configTax !== null) {
            $hasManualTax = $conditions->contains(fn (Condition $c) => $c->getType() === 'tax');
            if (! $hasManualTax) {
                $conditions->put($configTax->getName(), $configTax);
            }
        }

        return $conditions;
    }

    /**
     * Get conditions defined in instance config.
     *
     * @return array<int, Condition>
     */
    protected function getInstanceConditions(): array
    {
        $instanceConfig = $this->getInstanceConfig();
        /** @var array<int, array<string, mixed>> $conditionsConfig */
        $conditionsConfig = $instanceConfig['conditions'] ?? [];

        if (empty($conditionsConfig)) {
            return [];
        }

        $conditions = [];

        foreach ($conditionsConfig as $config) {
            $condition = $this->createConditionFromConfig($config);
            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        return $conditions;
    }

    /**
     * Create a condition instance from config array.
     *
     * @param  array<string, mixed>  $config
     */
    protected function createConditionFromConfig(array $config): ?Condition
    {
        if (! isset($config['class']) || ! is_string($config['class']) || ! isset($config['name'])) {
            return null;
        }

        $class = $config['class'];

        if (! class_exists($class)) {
            return null;
        }

        if (! is_subclass_of($class, Condition::class)) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $args = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();

                if (array_key_exists($paramName, $config)) {
                    $args[] = $config[$paramName];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    // Missing required parameter, skip this condition
                    return null;
                }
            }

            return $reflection->newInstanceArgs($args);
        } catch (\Throwable) {
            // If instantiation fails, skip this condition
            return null;
        }
    }

    /**
     * Get a TaxCondition from config if tax is enabled.
     */
    protected function getConfigTaxCondition(): ?TaxCondition
    {
        $taxEnabled = config('cart.tax.enabled', false);
        $taxRate = (float) config('cart.tax.rate', 0);

        if (! $taxEnabled || $taxRate <= 0 || $taxRate > 100) {
            return null;
        }

        $includedInPrice = (bool) config('cart.tax.included_in_price', false);

        return new TaxCondition(
            name: '_config_tax',
            rate: $taxRate,
            includedInPrice: $includedInPrice,
        );
    }

    /**
     * Dispatch an event if events are enabled.
     */
    protected function dispatchEvent(object $event): void
    {
        if ($this->eventsEnabled && config('cart.events.enabled', true)) {
            Event::dispatch($event);
        }
    }
}
