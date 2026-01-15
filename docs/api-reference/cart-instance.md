# CartInstance

The `CartInstance` class handles all operations for a single cart instance.

```php
use Cart\Cart;

$instance = Cart::instance('default');
```

## Item Operations

### add()

Add an item to the cart.

```php
public function add(
    Buyable|int|string $item,
    int $quantity = 1,
    array $options = [],
    array $meta = []
): CartItem
```

**Parameters:**
- `$item` - Buyable model or buyable identifier
- `$quantity` - Quantity to add (must be >= 1)
- `$options` - Item options (affects rowId)
- `$meta` - Additional metadata (does not affect rowId)

**Returns:** `CartItem` - The added or updated item

**Throws:**
- `InvalidQuantityException` - If quantity < 1
- `MaxItemsExceededException` - If max_items limit reached
- `DuplicateItemException` - If allow_duplicates is false

**Behavior:**
- Same buyable + options = increments quantity
- Different options = creates new item

**Example:**
```php
$item = $instance->add($product, 2, ['size' => 'L'], ['gift' => true]);
```

### update()

Update an existing cart item.

```php
public function update(string $rowId, int|array $attributes): CartItem
```

**Parameters:**
- `$rowId` - Item's unique row ID
- `$attributes` - New quantity (int) or array of attributes

**Attribute keys:**
- `quantity` - New quantity
- `options` - Replace options
- `meta` - Replace metadata

**Returns:** `CartItem` - The updated item

**Throws:**
- `InvalidRowIdException` - If rowId not found
- `InvalidQuantityException` - If quantity < 1

**Example:**
```php
$instance->update($rowId, 5);
$instance->update($rowId, [
    'quantity' => 3,
    'options' => ['size' => 'XL'],
    'meta' => ['note' => 'Updated'],
]);
```

### remove()

Remove an item from the cart.

```php
public function remove(string $rowId): void
```

**Parameters:**
- `$rowId` - Item's unique row ID

**Throws:** `InvalidRowIdException` - If rowId not found

**Example:**
```php
$instance->remove($rowId);
```

### get()

Get an item by row ID.

```php
public function get(string $rowId): ?CartItem
```

**Parameters:**
- `$rowId` - Item's unique row ID

**Returns:** `CartItem|null`

**Example:**
```php
$item = $instance->get($rowId);
if ($item) {
    echo $item->quantity;
}
```

### find()

Find an item by buyable ID.

```php
public function find(int|string $buyableId): ?CartItem
```

**Parameters:**
- `$buyableId` - The buyable identifier

**Returns:** `CartItem|null` - First matching item

**Example:**
```php
$item = $instance->find($productId);
```

### has()

Check if an item exists.

```php
public function has(string $rowId): bool
```

### content()

Get all cart items.

```php
public function content(): CartItemCollection
```

**Returns:** `CartItemCollection` - Collection of CartItem objects

**Example:**
```php
foreach ($instance->content() as $item) {
    echo "{$item->id}: {$item->quantity}\n";
}
```

### moveTo()

Move an item to another instance.

```php
public function moveTo(string $rowId, CartInstance $targetInstance): CartItem
```

**Parameters:**
- `$rowId` - Item's row ID
- `$targetInstance` - Target CartInstance

**Returns:** `CartItem` - The moved item (in target instance)

**Example:**
```php
$wishlist = Cart::instance('wishlist');
$item = $instance->moveTo($rowId, $wishlist);
```

## Counting

### count()

Get total quantity of all items.

```php
public function count(): int
```

**Returns:** Sum of all item quantities

**Example:**
```php
// 2 items: qty 3 + qty 2 = 5
$totalQty = $instance->count(); // 5
```

### countItems()

Get number of unique items.

```php
public function countItems(): int
```

**Returns:** Number of distinct items

**Example:**
```php
// 2 items: qty 3 + qty 2
$uniqueItems = $instance->countItems(); // 2
```

### isEmpty()

Check if cart has no items.

```php
public function isEmpty(): bool
```

### isNotEmpty()

Check if cart has items.

```php
public function isNotEmpty(): bool
```

## Totals

All methods return values in **cents**.

### subtotal()

Get subtotal before conditions.

```php
public function subtotal(): int
```

**Returns:** Sum of (unitPrice * quantity) for all items

### total()

Get final total after all conditions.

```php
public function total(): int
```

**Returns:** Subtotal with all conditions applied

### savings()

Get total savings (original vs current prices).

```php
public function savings(): int
```

**Returns:** Sum of (originalPrice - unitPrice) * quantity

### conditionsTotal()

Get sum of all condition values.

```php
public function conditionsTotal(): int
```

### taxTotal()

Get total tax amount.

```php
public function taxTotal(): int
```

**Returns:** Sum of all conditions where type = 'tax'

### discountTotal()

Get total discount amount.

```php
public function discountTotal(): int
```

**Returns:** Sum of all conditions where type = 'discount' (negative value)

### getCalculationBreakdown()

Get detailed calculation steps.

```php
public function getCalculationBreakdown(): array
```

**Returns:**
```php
[
    'subtotal' => 10000,
    'total' => 10800,
    'steps' => [
        [
            'name' => 'Summer Sale',
            'type' => 'discount',
            'order' => 100,
            'before' => 10000,
            'after' => 9000,
            'change' => -1000,
        ],
        [
            'name' => 'VAT',
            'type' => 'tax',
            'order' => 200,
            'before' => 9000,
            'after' => 10800,
            'change' => 1800,
        ],
    ],
    'breakdown' => [
        'discount' => -1000,
        'tax' => 1800,
    ],
]
```

**Keys:**
- `subtotal` - Subtotal before cart-level conditions (cents)
- `total` - Final total after all conditions (cents)
- `steps` - Array of calculation steps with:
  - `name` - Condition name
  - `type` - Condition type (tax, discount, shipping, fee)
  - `order` - Condition order
  - `before` - Amount before this condition
  - `after` - Amount after this condition
  - `change` - Amount changed by this condition
- `breakdown` - Totals grouped by condition type

## Conditions

### condition()

Add a condition to the cart.

```php
public function condition(Condition $condition): void
```

**Parameters:**
- `$condition` - Condition instance

**Example:**
```php
$instance->condition(new TaxCondition('VAT', 20));
$instance->condition(new DiscountCondition('Coupon', 500, 'fixed'));
```

### removeCondition()

Remove a condition by name.

```php
public function removeCondition(string $name): void
```

### getCondition()

Get a condition by name.

```php
public function getCondition(string $name): ?Condition
```

### getConditions()

Get all conditions.

```php
public function getConditions(): Collection
```

**Returns:** `Collection<string, Condition>` keyed by name

### hasCondition()

Check if a condition exists.

```php
public function hasCondition(string $name): bool
```

### clearConditions()

Remove all conditions.

```php
public function clearConditions(): void
```

## Cart Management

### clear()

Clear all items but keep conditions.

```php
public function clear(): void
```

### destroy()

Destroy cart completely.

```php
public function destroy(): void
```

Removes:
- All items
- All conditions
- Storage data

### refreshPrices()

Invalidate price cache and re-resolve.

```php
public function refreshPrices(): void
```

Use when product prices may have changed during the request.

## Configuration

### setDriver()

Set storage driver for this instance.

```php
public function setDriver(StorageDriver $driver): self
```

### setPriceResolver()

Set price resolver for this instance.

```php
public function setPriceResolver(PriceResolver $resolver): self
```

### associate()

Associate a user with this instance.

```php
public function associate(Authenticatable $user): self
```

### setIdentifier()

Set storage identifier manually.

```php
public function setIdentifier(?string $identifier): self
```

### getIdentifier()

Get current storage identifier.

```php
public function getIdentifier(): ?string
```

### getInstanceName()

Get this instance's name.

```php
public function getInstanceName(): string
```

### setEventsEnabled()

Enable or disable events.

```php
public function setEventsEnabled(bool $enabled): self
```

## Events

The following events are dispatched (when enabled):

| Method | Before Event | After Event |
|--------|--------------|-------------|
| `add()` | `CartItemAdding` | `CartItemAdded` |
| `update()` | `CartItemUpdating` | `CartItemUpdated` |
| `remove()` | `CartItemRemoving` | `CartItemRemoved` |
| `clear()` | `CartClearing` | `CartCleared` |
| `condition()` | - | `CartConditionAdded` |
| `removeCondition()` | - | `CartConditionRemoved` |
