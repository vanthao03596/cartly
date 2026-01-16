<?php

declare(strict_types=1);

namespace Cart;

use Cart\Contracts\PriceResolver;
use Cart\Contracts\StorageDriver;
use Cart\Drivers\ArrayDriver;
use Cart\Events\CartMerged;
use Cart\Events\CartMerging;
use Cart\Resolvers\BuyablePriceResolver;
use Cart\Testing\CartAssertions;
use Cart\Testing\CartFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Cart Manager - manages multiple cart instances and driver resolution.
 *
 * @method CartInstance wishlist() Get the wishlist instance
 * @method CartInstance compare() Get the compare instance
 */
class CartManager
{
    use CartAssertions;
    /**
     * Active cart instances.
     *
     * @var array<string, CartInstance>
     */
    protected array $instances = [];

    /**
     * The current instance name.
     */
    protected string $currentInstance;

    /**
     * Custom storage driver.
     */
    protected ?StorageDriver $customDriver = null;

    /**
     * Custom price resolver.
     */
    protected ?PriceResolver $customResolver = null;

    /**
     * The associated user.
     */
    protected ?Authenticatable $user = null;

    /**
     * Whether the cart is in fake mode (for testing).
     */
    protected bool $fakeMode = false;

    /**
     * Fake driver for testing.
     */
    protected ?ArrayDriver $fakeDriver = null;

    /**
     * Fake resolver for testing.
     */
    protected ?PriceResolver $fakeResolver = null;

    public function __construct()
    {
        $this->currentInstance = config('cart.default_instance', 'default');

        // Auto-associate current user if enabled
        if (config('cart.associate.auto_associate', true)) {
            $user = Auth::user();
            if ($user !== null) {
                $this->user = $user;
            }
        }
    }

    /**
     * Get or switch to a cart instance.
     */
    public function instance(?string $name = null): CartInstance
    {
        $name = $name ?? $this->currentInstance;
        $this->currentInstance = $name;

        if (!isset($this->instances[$name])) {
            $this->instances[$name] = $this->createInstance($name);
        }

        return $this->instances[$name];
    }

    /**
     * Get the current instance name.
     */
    public function currentInstance(): string
    {
        return $this->currentInstance;
    }

    /**
     * Set a custom storage driver.
     */
    public function setDriver(string|StorageDriver $driver): self
    {
        if (is_string($driver)) {
            $this->customDriver = $this->resolveDriver($driver);
        } else {
            $this->customDriver = $driver;
        }

        // Update existing instances
        foreach ($this->instances as $instance) {
            $instance->setDriver($this->customDriver);
        }

        return $this;
    }

    /**
     * Set a custom price resolver.
     */
    public function setPriceResolver(PriceResolver $resolver): self
    {
        $this->customResolver = $resolver;

        foreach ($this->instances as $instance) {
            $instance->setPriceResolver($resolver);
        }

        return $this;
    }

    /**
     * Associate a user with all cart instances.
     */
    public function associate(Authenticatable $user): self
    {
        $this->user = $user;

        foreach ($this->instances as $instance) {
            $instance->associate($user);
        }

        return $this;
    }

    /**
     * Handle user login - merge guest cart with user cart.
     */
    public function handleLogin(Authenticatable $user): void
    {
        if (!config('cart.associate.merge_on_login', true)) {
            $this->associate($user);

            return;
        }

        $strategy = config('cart.associate.merge_strategy', 'combine');

        // Get guest cart (from session)
        $sessionDriver = $this->resolveDriver('session');
        $guestContent = $sessionDriver->get($this->currentInstance);

        // Get user cart (from database)
        $databaseDriver = $this->resolveDriver('database');
        $userId = 'user_' . $user->getAuthIdentifier();
        $userContent = $databaseDriver->get($this->currentInstance, $userId);

        // If no guest cart, just associate user
        if ($guestContent === null || $guestContent->isEmpty()) {
            $this->associate($user);
            $this->setDriver($databaseDriver);

            return;
        }

        // If no user cart, move guest cart to user
        if ($userContent === null || $userContent->isEmpty()) {
            $databaseDriver->put($this->currentInstance, $guestContent, $userId);
            $sessionDriver->forget($this->currentInstance);
            $this->associate($user);
            $this->setDriver($databaseDriver);

            return;
        }

        // Dispatch merging event
        Event::dispatch(new CartMerging($guestContent, $userContent, $strategy, $user));

        // Merge based on strategy
        $mergedContent = $this->mergeCarts($guestContent, $userContent, $strategy);

        // Save merged cart
        $databaseDriver->put($this->currentInstance, $mergedContent, $userId);

        // Clear guest cart
        $sessionDriver->forget($this->currentInstance);

        // Update manager state
        $this->associate($user);
        $this->setDriver($databaseDriver);

        // Clear cached instance
        unset($this->instances[$this->currentInstance]);

        // Dispatch merged event
        Event::dispatch(new CartMerged(
            $mergedContent,
            $mergedContent->countItems(),
            $user,
        ));
    }

    /**
     * Move an item from current cart to another instance.
     */
    public function moveTo(string $rowId, string $targetInstance): CartItem
    {
        $source = $this->instance();
        $target = $this->instance($targetInstance);

        return $source->moveTo($rowId, $target);
    }

    /**
     * Move an item from current cart to wishlist.
     */
    public function moveToWishlist(string $rowId): CartItem
    {
        return $this->moveTo($rowId, 'wishlist');
    }

    /**
     * Move an item from wishlist to default cart instance.
     */
    public function moveToCart(string $rowId): CartItem
    {
        $wishlist = $this->instance('wishlist');
        $defaultInstance = config('cart.default_instance', 'default');
        $cart = $this->instance($defaultInstance);

        return $wishlist->moveTo($rowId, $cart);
    }

    /**
     * Whether events are enabled in fake mode.
     */
    protected bool $fakeEventsEnabled = true;

    /**
     * Enable fake mode for testing.
     *
     * @param array{events?: bool}|null $options
     */
    public function fake(?array $options = null): self
    {
        $this->fakeMode = true;
        $this->fakeDriver = new ArrayDriver();
        $this->fakeEventsEnabled = $options['events'] ?? true;

        // Clear existing instances so they get recreated with fake driver
        $this->instances = [];

        return $this;
    }

    /**
     * Set a fake resolver for testing.
     *
     * @param int|callable $resolver Fixed price in cents or callable
     */
    public function fakeResolver(int|callable $resolver): self
    {
        if (is_int($resolver)) {
            $price = $resolver;
            $resolver = fn (CartItem $item, CartContext $context) => new ResolvedPrice(
                unitPrice: $price,
                originalPrice: $price,
            );
        }

        $this->fakeResolver = new class($resolver) implements PriceResolver {
            public function __construct(
                protected \Closure $callback,
            ) {}

            public function resolve(CartItem $item, CartContext $context): ResolvedPrice
            {
                return ($this->callback)($item, $context);
            }

            public function resolveMany(CartItemCollection $items, CartContext $context): array
            {
                $results = [];
                foreach ($items as $item) {
                    $results[$item->rowId] = $this->resolve($item, $context);
                }

                return $results;
            }
        };

        foreach ($this->instances as $instance) {
            $instance->setPriceResolver($this->fakeResolver);
        }

        return $this;
    }

    /**
     * Create a cart factory for building test carts.
     */
    public function factory(): CartFactory
    {
        return new CartFactory($this);
    }

    /**
     * Proxy method calls to the current instance.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        // Check if it's an instance name (wishlist, compare, etc.)
        $configuredInstances = array_keys(config('cart.instances', []));

        if (in_array($method, $configuredInstances, true)) {
            return $this->instance($method);
        }

        // Proxy to current instance
        return $this->instance()->$method(...$arguments);
    }

    /**
     * Create a new cart instance.
     */
    protected function createInstance(string $name): CartInstance
    {
        $driver = $this->getDriver();
        $resolver = $this->getResolver();

        $instance = new CartInstance($name, $driver, $resolver);

        // Set identifier if user is associated
        if ($this->user !== null) {
            $instance->associate($this->user);
        }

        // Apply fake mode settings
        if ($this->fakeMode) {
            $instance->setEventsEnabled($this->fakeEventsEnabled);
        }

        return $instance;
    }

    /**
     * Get the storage driver.
     */
    protected function getDriver(): StorageDriver
    {
        if ($this->fakeMode && $this->fakeDriver !== null) {
            return $this->fakeDriver;
        }

        if ($this->customDriver !== null) {
            return $this->customDriver;
        }

        $driverName = config('cart.driver', 'session');

        return $this->resolveDriver($driverName);
    }

    /**
     * Resolve a driver by name.
     */
    protected function resolveDriver(string $name): StorageDriver
    {
        $class = config("cart.drivers.{$name}.class");

        if ($class === null) {
            throw new InvalidArgumentException("Cart driver [{$name}] is not configured.");
        }

        if (!class_exists($class)) {
            throw new InvalidArgumentException("Cart driver class [{$class}] does not exist.");
        }

        if (!is_subclass_of($class, StorageDriver::class)) {
            throw new InvalidArgumentException("Cart driver [{$class}] must implement StorageDriver.");
        }

        return app($class);
    }

    /**
     * Get the price resolver.
     */
    protected function getResolver(): PriceResolver
    {
        if ($this->fakeResolver !== null) {
            return $this->fakeResolver;
        }

        if ($this->customResolver !== null) {
            return $this->customResolver;
        }

        $resolverClass = config('cart.price_resolver');

        if ($resolverClass !== null && class_exists($resolverClass)) {
            return new $resolverClass();
        }

        return new BuyablePriceResolver();
    }

    /**
     * Merge two cart contents based on strategy.
     */
    protected function mergeCarts(CartContent $guest, CartContent $user, string $strategy): CartContent
    {
        return match ($strategy) {
            'keep_guest' => $guest,
            'keep_user' => $user,
            'combine' => $this->combineCarts($guest, $user),
            default => $this->combineCarts($guest, $user),
        };
    }

    /**
     * Combine two cart contents.
     */
    protected function combineCarts(CartContent $guest, CartContent $user): CartContent
    {
        $combined = new CartContent(
            items: new CartItemCollection($user->items->all()),
            conditions: $user->conditions,
            meta: array_merge($guest->meta, $user->meta),
        );

        foreach ($guest->items as $guestItem) {
            if ($combined->items->hasRowId($guestItem->rowId)) {
                // Same rowId = same product + options, sum quantities
                $existingItem = $combined->items->get($guestItem->rowId);
                $existingItem->setQuantity($existingItem->quantity + $guestItem->quantity);
            } else {
                // New item, add to combined
                $combined->items->put($guestItem->rowId, $guestItem);
            }
        }

        return $combined;
    }
}
