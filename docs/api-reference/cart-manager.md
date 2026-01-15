# CartManager

The `CartManager` class manages cart instances, storage drivers, and price resolvers.

```php
use Cart\CartManager;

$manager = app(CartManager::class);
```

## Instance Management

### instance()

Get or create a cart instance.

```php
public function instance(?string $name = null): CartInstance
```

**Parameters:**
- `$name` - Instance name, or null for current instance

**Returns:** `CartInstance`

**Example:**
```php
$cart = $manager->instance();           // Current instance
$wishlist = $manager->instance('wishlist');
```

### currentInstance()

Get the name of the current instance.

```php
public function currentInstance(): string
```

**Returns:** `string` - Instance name (e.g., 'default', 'wishlist')

## Configuration

### setDriver()

Set the storage driver.

```php
public function setDriver(string|StorageDriver $driver): self
```

**Parameters:**
- `$driver` - Driver name or StorageDriver instance

**Returns:** `self` for chaining

**Available drivers:**
- `'session'` - SessionDriver
- `'database'` - DatabaseDriver
- `'cache'` - CacheDriver
- `'array'` - ArrayDriver

**Example:**
```php
$manager->setDriver('database');
$manager->setDriver(new RedisDriver());
```

### setPriceResolver()

Set the price resolver.

```php
public function setPriceResolver(PriceResolver $resolver): self
```

**Parameters:**
- `$resolver` - PriceResolver implementation

**Returns:** `self` for chaining

**Example:**
```php
$manager->setPriceResolver(new TieredPriceResolver());
```

## User Association

### associate()

Associate a user with all cart instances.

```php
public function associate(Authenticatable $user): self
```

**Parameters:**
- `$user` - User to associate

**Returns:** `self` for chaining

**Example:**
```php
$manager->associate(Auth::user());
```

### handleLogin()

Handle cart merging when user logs in.

```php
public function handleLogin(Authenticatable $user): void
```

**Parameters:**
- `$user` - The user who logged in

This method:
1. Gets the guest cart (session identifier)
2. Gets the user's existing cart
3. Merges based on `cart.associate.merge_strategy` config
4. Associates the user with the cart

**Example:**
```php
// Called automatically on Login event, or manually:
$manager->handleLogin($user);
```

## Moving Items

### moveTo()

Move an item to a specific instance.

```php
public function moveTo(string $rowId, string $targetInstance): CartItem
```

**Parameters:**
- `$rowId` - Item's row ID
- `$targetInstance` - Target instance name

**Returns:** `CartItem` - The moved item

**Throws:** `InvalidRowIdException` if rowId not found

**Example:**
```php
$item = $manager->moveTo($rowId, 'wishlist');
```

### moveToWishlist()

Move an item from current instance to wishlist.

```php
public function moveToWishlist(string $rowId): CartItem
```

**Parameters:**
- `$rowId` - Item's row ID

**Returns:** `CartItem` - The moved item

**Example:**
```php
$item = $manager->moveToWishlist($rowId);
```

### moveToCart()

Move an item from wishlist to default cart.

```php
public function moveToCart(string $rowId): CartItem
```

**Parameters:**
- `$rowId` - Item's row ID in wishlist

**Returns:** `CartItem` - The moved item

**Example:**
```php
$item = $manager->moveToCart($rowId);
```

## Testing

### fake()

Enable fake mode for testing.

```php
public function fake(?array $options = null): self
```

**Parameters:**
- `$options` - Array of options:
  - `events` (bool) - Enable events in fake mode (default: false)

**Returns:** `self` for chaining

**Example:**
```php
$manager->fake();
$manager->fake(['events' => true]);
```

### fakeResolver()

Set a fake price resolver for testing.

```php
public function fakeResolver(int|callable $resolver): self
```

**Parameters:**
- `$resolver` - Fixed price in cents, or callable `(CartItem) => int`

**Returns:** `self` for chaining

**Example:**
```php
// Fixed price
$manager->fakeResolver(999); // $9.99

// Dynamic price
$manager->fakeResolver(function (CartItem $item) {
    return $item->id * 100;
});
```

### factory()

Get a cart factory for building test carts.

```php
public function factory(): CartFactory
```

**Returns:** `CartFactory`

**Example:**
```php
$manager->factory()
    ->withItems([
        ['id' => 1, 'quantity' => 2, 'price' => 1000],
        ['id' => 2, 'quantity' => 1, 'price' => 2500],
    ])
    ->withCondition(new TaxCondition('Tax', 10))
    ->create();
```

## Magic Methods

### __call()

Handles magic method calls for:
1. Proxying to current CartInstance
2. Accessing configured instances

```php
public function __call(string $method, array $arguments): mixed
```

**Instance shortcuts:**
```php
$manager->wishlist();  // Returns instance('wishlist')
$manager->compare();   // Returns instance('compare')
```

**Proxied methods:**
All CartInstance methods are available directly on CartManager:
```php
$manager->add($product);      // Proxies to instance()->add()
$manager->content();          // Proxies to instance()->content()
$manager->total();            // Proxies to instance()->total()
```

## Assertions (Testing Trait)

When using `CartAssertions` trait:

```php
$manager->assertItemCount(5);
$manager->assertHas($productId);
$manager->assertTotal(9999);
$manager->assertEmpty();
$manager->assertConditionApplied('VAT');
```

See [Testing](../testing.md) for full assertion reference.

## Internal Methods

These methods are used internally but available if needed:

### getDriver()

Get the current storage driver.

```php
public function getDriver(): StorageDriver
```

### getPriceResolver()

Get the current price resolver.

```php
public function getPriceResolver(): PriceResolver
```

### getConfig()

Get cart configuration value.

```php
public function getConfig(string $key, mixed $default = null): mixed
```

**Example:**
```php
$maxItems = $manager->getConfig('instances.wishlist.max_items');
```
