<?php

declare(strict_types=1);

namespace Cart\Testing;

use Cart\CartInstance;
use Cart\CartItem;
use Cart\CartManager;
use Cart\Contracts\Condition;
use Cart\Contracts\PriceResolver;
use Cart\Drivers\ArrayDriver;
use Cart\ResolvedPrice;

/**
 * Factory for creating cart instances with predefined data for testing.
 */
class CartFactory
{
    /**
     * Items to add to the cart.
     *
     * @var array<int, array{id: int|string, quantity: int, price: int, originalPrice?: int, options?: array<string, mixed>, meta?: array<string, mixed>}>
     */
    protected array $items = [];

    /**
     * Conditions to apply to the cart.
     *
     * @var array<int, Condition>
     */
    protected array $conditions = [];

    /**
     * The instance name for the cart.
     */
    protected string $instanceName = 'default';

    /**
     * Cart metadata.
     *
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * The cart manager reference.
     */
    protected ?CartManager $manager = null;

    /**
     * Create a new factory instance.
     */
    public function __construct(?CartManager $manager = null)
    {
        $this->manager = $manager;
    }

    /**
     * Set the instance name.
     */
    public function instance(string $name): self
    {
        $clone = clone $this;
        $clone->instanceName = $name;

        return $clone;
    }

    /**
     * Add items to the cart.
     *
     * Each item should have:
     * - id: int|string (required)
     * - quantity: int (required)
     * - price: int in cents (required)
     * - originalPrice: int in cents (optional, defaults to price)
     * - options: array (optional)
     * - meta: array (optional)
     *
     * @param  array<int, array{id: int|string, quantity: int, price: int, originalPrice?: int, options?: array<string, mixed>, meta?: array<string, mixed>}>  $items
     */
    public function withItems(array $items): self
    {
        $clone = clone $this;
        $clone->items = array_merge($clone->items, $items);

        return $clone;
    }

    /**
     * Add a single item to the cart.
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $meta
     */
    public function withItem(
        int|string $id,
        int $quantity = 1,
        int $price = 0,
        ?int $originalPrice = null,
        array $options = [],
        array $meta = [],
    ): self {
        $clone = clone $this;
        $clone->items[] = [
            'id' => $id,
            'quantity' => $quantity,
            'price' => $price,
            'originalPrice' => $originalPrice ?? $price,
            'options' => $options,
            'meta' => $meta,
        ];

        return $clone;
    }

    /**
     * Add a condition to the cart.
     */
    public function withCondition(Condition $condition): self
    {
        $clone = clone $this;
        $clone->conditions[] = $condition;

        return $clone;
    }

    /**
     * Add multiple conditions to the cart.
     *
     * @param  array<int, Condition>  $conditions
     */
    public function withConditions(array $conditions): self
    {
        $clone = clone $this;
        $clone->conditions = array_merge($clone->conditions, $conditions);

        return $clone;
    }

    /**
     * Set cart metadata.
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        $clone = clone $this;
        $clone->meta = array_merge($clone->meta, $meta);

        return $clone;
    }

    /**
     * Create the cart instance with the configured data.
     */
    public function create(): CartInstance
    {
        // Create array driver for in-memory storage
        $driver = new ArrayDriver;

        // Create a fake resolver that returns prices from our items config
        $resolver = $this->createFakeResolver();

        // Create the cart instance
        $instance = new CartInstance($this->instanceName, $driver, $resolver);

        // Add items
        foreach ($this->items as $itemData) {
            $cartItem = $instance->add(
                item: $itemData['id'],
                quantity: $itemData['quantity'],
                options: $itemData['options'] ?? [],
                meta: $itemData['meta'] ?? [],
            );

            // Set the resolved price directly
            $cartItem->setResolvedPrice(new ResolvedPrice(
                unitPrice: $itemData['price'],
                originalPrice: $itemData['originalPrice'] ?? $itemData['price'],
                priceSource: 'factory',
            ));
        }

        // Add conditions
        foreach ($this->conditions as $condition) {
            $instance->condition($condition);
        }

        return $instance;
    }

    /**
     * Create a fake price resolver based on configured items.
     */
    protected function createFakeResolver(): PriceResolver
    {
        $itemPrices = [];
        foreach ($this->items as $itemData) {
            $itemPrices[$itemData['id']] = [
                'price' => $itemData['price'],
                'originalPrice' => $itemData['originalPrice'] ?? $itemData['price'],
            ];
        }

        return new class($itemPrices) implements PriceResolver
        {
            /**
             * @param  array<int|string, array{price: int, originalPrice: int}>  $prices
             */
            public function __construct(
                protected array $prices,
            ) {}

            public function resolve(CartItem $item, \Cart\CartContext $context): ResolvedPrice
            {
                $priceData = $this->prices[$item->id] ?? ['price' => 0, 'originalPrice' => 0];

                return new ResolvedPrice(
                    unitPrice: $priceData['price'],
                    originalPrice: $priceData['originalPrice'],
                    priceSource: 'factory',
                );
            }

            /**
             * @return array<string, ResolvedPrice>
             */
            public function resolveMany(\Cart\CartItemCollection $items, \Cart\CartContext $context): array
            {
                $results = [];
                foreach ($items as $item) {
                    $results[$item->rowId] = $this->resolve($item, $context);
                }

                return $results;
            }
        };
    }
}
