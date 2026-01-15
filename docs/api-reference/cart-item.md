# CartItem

The `CartItem` class represents a single item in the cart.

## Properties

### Identifiers

```php
public readonly string $rowId;
```
Unique hash generated from buyable ID + options. Used to identify items in the cart.

```php
public readonly int|string $id;
```
The buyable identifier (e.g., product ID).

```php
public readonly ?string $buyableType;
```
The buyable model class name (e.g., `App\Models\Product`).

```php
public readonly int|string|null $buyableId;
```
The buyable model ID.

### Quantity & Options

```php
public int $quantity;
```
Item quantity (mutable).

```php
public Collection $options;
```
Item options collection (size, color, etc.).

```php
public Collection $meta;
```
Additional metadata collection.

## Creating Items

### fromBuyable()

Create item from a Buyable model.

```php
public static function fromBuyable(
    Buyable $buyable,
    int $quantity = 1,
    array $options = [],
    array $meta = []
): self
```

**Example:**
```php
$item = CartItem::fromBuyable($product, 2, ['size' => 'L']);
```

### fromArray()

Create item from array data.

```php
public static function fromArray(array $data): self
```

**Required keys:**
- `rowId` - Unique identifier
- `id` - Buyable identifier
- `quantity` - Quantity

**Optional keys:**
- `options` - Options array
- `meta` - Metadata array
- `buyableType` - Model class
- `buyableId` - Model ID

**Example:**
```php
$item = CartItem::fromArray([
    'rowId' => 'abc123',
    'id' => 1,
    'quantity' => 2,
    'options' => ['size' => 'M'],
]);
```

## Price Methods

All prices are in **cents**.

### unitPrice()

Get current unit price.

```php
public function unitPrice(): int
```

**Returns:** Current price in cents

**Example:**
```php
$price = $item->unitPrice(); // 1999 = $19.99
```

### originalUnitPrice()

Get original unit price (before sale).

```php
public function originalUnitPrice(): int
```

**Returns:** Original unit price in cents

### subtotal()

Get item subtotal (price * quantity).

```php
public function subtotal(): int
```

**Returns:** `unitPrice() * quantity`

**Example:**
```php
// unitPrice: 1000, quantity: 3
$subtotal = $item->subtotal(); // 3000
```

### total()

Get item total with item-level conditions.

```php
public function total(): int
```

**Returns:** Subtotal with item conditions applied

### savings()

Get savings for this item.

```php
public function savings(): int
```

**Returns:** `originalSubtotal() - subtotal()`

**Example:**
```php
// Original: 2000, Current: 1500, Qty: 2
$savings = $item->savings(); // 1000
```

## Model Access

### model()

Get the buyable model (lazy loaded).

```php
public function model(): ?Buyable
```

**Returns:** The Buyable model or null

**Example:**
```php
$product = $item->model();
echo $product->name;
```

### setPriceResolutionCallback()

Set callback for price resolution.

```php
public function setPriceResolutionCallback(callable $callback): self
```

Used internally by CartInstance for lazy price loading.

## Item Conditions

### condition()

Add an item-level condition.

```php
public function condition(Condition $condition): void
```

**Example:**
```php
$item->condition(new DiscountCondition('Item Promo', 10, 'percentage', 'item'));
```

### removeCondition()

Remove an item-level condition.

```php
public function removeCondition(string $name): void
```

### getCondition()

Get an item-level condition.

```php
public function getCondition(string $name): ?Condition
```

### getConditions()

Get all item-level conditions.

```php
public function getConditions(): Collection
```

### hasCondition()

Check if item has a condition.

```php
public function hasCondition(string $name): bool
```

### clearConditions()

Remove all item-level conditions.

```php
public function clearConditions(): void
```

## Options Access

The `$options` property is a Laravel Collection. Use Collection methods to access options:

```php
// Get option value
$size = $item->options->get('size', 'M');

// Check if option exists
if ($item->options->has('color')) {
    // ...
}

// Get all options
$allOptions = $item->options->all();
```

### setOption()

Set or update an option.

```php
public function setOption(string $key, mixed $value): self
```

**Example:**
```php
$item->setOption('color', 'blue');
```

## Metadata Access

The `$meta` property is a Laravel Collection. Use Collection methods to access metadata:

```php
// Get metadata value
$isGift = $item->meta->get('gift_wrap', false);

// Check if metadata key exists
if ($item->meta->has('note')) {
    // ...
}
```

### setMeta()

Set a metadata value.

```php
public function setMeta(string $key, mixed $value): self
```

**Example:**
```php
$item->setMeta('note', 'Handle with care');
```

## Serialization

### toArray()

Convert item to array.

```php
public function toArray(): array
```

**Returns:**
```php
[
    'rowId' => 'abc123...',
    'id' => 1,
    'quantity' => 2,
    'options' => ['size' => 'L'],
    'meta' => ['gift' => true],
    'buyableType' => 'App\\Models\\Product',
    'buyableId' => 1,
    'conditions' => [...],
]
```

To convert to JSON, use Laravel's `json_encode()`:

```php
$json = json_encode($item->toArray());
```

## Row ID Generation

The `rowId` is generated from the buyable ID and options:

```php
// Same product, same options = same rowId
$item1 = Cart::add($product, 1, ['size' => 'L']);
$item2 = Cart::add($product, 1, ['size' => 'L']);
// $item1->rowId === $item2->rowId (quantity increased)

// Same product, different options = different rowId
$item3 = Cart::add($product, 1, ['size' => 'M']);
// $item3->rowId !== $item1->rowId (new item)
```

The algorithm uses xxh128 hash of:
- Buyable identifier
- Sorted options array

## Example Usage

```php
$item = Cart::add($product, 2, ['size' => 'L', 'color' => 'blue']);

// Access properties
echo $item->rowId;           // 'a1b2c3...'
echo $item->quantity;        // 2
echo $item->options->get('size'); // 'L'

// Get prices
echo $item->unitPrice();     // 2999
echo $item->subtotal();      // 5998
echo $item->savings();       // 1000 (if on sale)

// Access model
$product = $item->model();
echo $product->name;

// Check options
if ($item->options->has('size')) {
    echo "Size: " . $item->options->get('size');
}
```
