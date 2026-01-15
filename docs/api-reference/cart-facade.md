# Cart Facade

The `Cart` facade provides static access to all cart functionality.

```php
use Cart\Cart;
```

## Manager Methods

These methods operate on the CartManager:

### instance()

Get or switch to a cart instance.

```php
Cart::instance(?string $name = null): CartInstance
```

**Parameters:**
- `$name` - Instance name (null returns current instance)

**Example:**
```php
Cart::instance('wishlist')->add($product);
```

### currentInstance()

Get the current instance name.

```php
Cart::currentInstance(): string
```

**Example:**
```php
$name = Cart::currentInstance(); // 'default'
```

### setDriver()

Set a custom storage driver.

```php
Cart::setDriver(string|StorageDriver $driver): CartManager
```

**Parameters:**
- `$driver` - Driver name ('session', 'database', 'cache', 'array') or StorageDriver instance

**Example:**
```php
Cart::setDriver('database');
Cart::setDriver(new CustomDriver());
```

### setPriceResolver()

Set a custom price resolver.

```php
Cart::setPriceResolver(PriceResolver $resolver): CartManager
```

**Example:**
```php
Cart::setPriceResolver(new TieredPriceResolver());
```

### associate()

Associate a user with the cart.

```php
Cart::associate(Authenticatable $user): CartManager
```

**Example:**
```php
Cart::associate(Auth::user());
```

### handleLogin()

Handle cart merging when a user logs in.

```php
Cart::handleLogin(Authenticatable $user): void
```

**Example:**
```php
Cart::handleLogin($user);
```

## Instance Movement

### moveTo()

Move an item to a specific instance.

```php
Cart::moveTo(string $rowId, string $targetInstance): CartItem
```

**Example:**
```php
$item = Cart::moveTo($rowId, 'wishlist');
```

### moveToWishlist()

Move an item from cart to wishlist.

```php
Cart::moveToWishlist(string $rowId): CartItem
```

**Example:**
```php
$item = Cart::moveToWishlist($rowId);
```

### moveToCart()

Move an item from wishlist to cart.

```php
Cart::moveToCart(string $rowId): CartItem
```

**Example:**
```php
$item = Cart::moveToCart($rowId);
```

## Item Operations

These methods are proxied to the current CartInstance:

### add()

Add an item to the cart.

```php
Cart::add(
    Buyable|int|string $item,
    int $quantity = 1,
    array $options = [],
    array $meta = []
): CartItem
```

**Parameters:**
- `$item` - Buyable model, or buyable ID
- `$quantity` - Quantity to add (default: 1)
- `$options` - Item options (size, color, etc.)
- `$meta` - Additional metadata

**Example:**
```php
$item = Cart::add($product, 2, ['size' => 'L']);
```

### update()

Update a cart item.

```php
Cart::update(string $rowId, int|array $attributes): CartItem
```

**Parameters:**
- `$rowId` - Item's row ID
- `$attributes` - New quantity or array of attributes

**Example:**
```php
Cart::update($rowId, 5);
Cart::update($rowId, ['quantity' => 3, 'options' => ['size' => 'XL']]);
```

### remove()

Remove an item from the cart.

```php
Cart::remove(string $rowId): void
```

**Example:**
```php
Cart::remove($rowId);
```

### get()

Get an item by row ID.

```php
Cart::get(string $rowId): ?CartItem
```

**Example:**
```php
$item = Cart::get($rowId);
```

### find()

Find an item by buyable ID.

```php
Cart::find(int|string $buyableId): ?CartItem
```

**Example:**
```php
$item = Cart::find($productId);
```

### has()

Check if an item exists by row ID.

```php
Cart::has(string $rowId): bool
```

**Example:**
```php
if (Cart::has($rowId)) {
    // Item exists
}
```

### content()

Get all cart items.

```php
Cart::content(): CartItemCollection
```

**Example:**
```php
foreach (Cart::content() as $item) {
    echo $item->quantity;
}
```

## Counting

### count()

Get total quantity of all items.

```php
Cart::count(): int
```

**Example:**
```php
$totalQty = Cart::count(); // Sum of all quantities
```

### countItems()

Get number of unique items.

```php
Cart::countItems(): int
```

**Example:**
```php
$uniqueItems = Cart::countItems();
```

### isEmpty()

Check if cart is empty.

```php
Cart::isEmpty(): bool
```

### isNotEmpty()

Check if cart has items.

```php
Cart::isNotEmpty(): bool
```

## Totals

All totals return values in cents.

### subtotal()

Get subtotal before conditions.

```php
Cart::subtotal(): int
```

### total()

Get final total after conditions.

```php
Cart::total(): int
```

### savings()

Get total savings amount.

```php
Cart::savings(): int
```

### conditionsTotal()

Get sum of all conditions.

```php
Cart::conditionsTotal(): int
```

### taxTotal()

Get total tax amount.

```php
Cart::taxTotal(): int
```

### discountTotal()

Get total discount amount (negative).

```php
Cart::discountTotal(): int
```

### getCalculationBreakdown()

Get detailed calculation breakdown.

```php
Cart::getCalculationBreakdown(): array
```

**Returns:**
```php
[
    'subtotal' => 10000,
    'steps' => [
        ['name' => 'Discount', 'type' => 'discount', 'value' => -1000, 'running_total' => 9000],
        ['name' => 'Tax', 'type' => 'tax', 'value' => 900, 'running_total' => 9900],
    ],
    'total' => 9900,
]
```

## Conditions

### condition()

Add a condition to the cart.

```php
Cart::condition(Condition $condition): void
```

**Example:**
```php
Cart::condition(new TaxCondition('VAT', 20));
```

### removeCondition()

Remove a condition by name.

```php
Cart::removeCondition(string $name): void
```

### getCondition()

Get a condition by name.

```php
Cart::getCondition(string $name): ?Condition
```

### getConditions()

Get all conditions.

```php
Cart::getConditions(): Collection
```

### hasCondition()

Check if a condition exists.

```php
Cart::hasCondition(string $name): bool
```

### clearConditions()

Remove all conditions.

```php
Cart::clearConditions(): void
```

## Cart Management

### clear()

Clear all items (keeps conditions).

```php
Cart::clear(): void
```

### destroy()

Destroy cart completely (items, conditions, storage).

```php
Cart::destroy(): void
```

### refreshPrices()

Refresh all item prices.

```php
Cart::refreshPrices(): void
```

## Testing Methods

### fake()

Enable fake mode for testing.

```php
Cart::fake(?array $options = null): CartManager
```

**Options:**
- `events` - Enable/disable events (default: false)

**Example:**
```php
Cart::fake();
Cart::fake(['events' => true]);
```

### fakeResolver()

Set a fake price resolver.

```php
Cart::fakeResolver(int|callable $resolver): CartManager
```

**Example:**
```php
Cart::fakeResolver(1000); // Fixed price: $10.00
Cart::fakeResolver(fn($item) => $item->id * 100);
```

### factory()

Get a cart factory for testing.

```php
Cart::factory(): CartFactory
```

**Example:**
```php
Cart::factory()
    ->withItems([['id' => 1, 'price' => 1000]])
    ->create();
```

## Assertions

Available in test environment:

```php
Cart::assertItemCount(int $expected, ?string $instance = null): void
Cart::assertUniqueItemCount(int $expected, ?string $instance = null): void
Cart::assertHas(int|string $buyableId, ?string $instance = null): void
Cart::assertDoesNotHave(int|string $buyableId, ?string $instance = null): void
Cart::assertHasRowId(string $rowId, ?string $instance = null): void
Cart::assertTotal(int $expectedCents, ?string $instance = null): void
Cart::assertSubtotal(int $expectedCents, ?string $instance = null): void
Cart::assertEmpty(?string $instance = null): void
Cart::assertNotEmpty(?string $instance = null): void
Cart::assertConditionApplied(string $conditionName, ?string $instance = null): void
Cart::assertConditionNotApplied(string $conditionName, ?string $instance = null): void
Cart::assertQuantity(int|string $buyableId, int $expectedQuantity, ?string $instance = null): void
Cart::assertTaxTotal(int $expectedCents, ?string $instance = null): void
Cart::assertDiscountTotal(int $expectedCents, ?string $instance = null): void
```

## Magic Methods

Access configured instances directly:

```php
Cart::wishlist(): CartInstance    // Same as Cart::instance('wishlist')
Cart::compare(): CartInstance     // Same as Cart::instance('compare')
```
