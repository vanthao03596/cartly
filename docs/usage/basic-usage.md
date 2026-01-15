# Basic Usage

This guide covers the fundamental cart operations: adding, updating, removing items, and getting totals.

## Important: Prices in Cents

All monetary values in this package are stored as **integers in cents** to avoid floating-point precision errors.

```php
// $99.99 = 9999 cents
// $10.00 = 1000 cents
// $0.50 = 50 cents
```

## Adding Items

### Using a Buyable Model

The recommended way is to use models that implement `Buyable` and `Priceable` interfaces:

```php
use Cart\Cart;

$product = Product::find(1);

// Add 1 item
$item = Cart::add($product);

// Add with quantity
$item = Cart::add($product, quantity: 3);

// Add with options
$item = Cart::add($product, quantity: 1, options: [
    'size' => 'L',
    'color' => 'blue',
]);

// Add with metadata
$item = Cart::add($product, quantity: 1, options: [], meta: [
    'gift_wrap' => true,
    'message' => 'Happy Birthday!',
]);
```

### Using Raw ID

You can also add items without a Buyable model:

```php
// Add by ID (requires custom price resolver)
$item = Cart::add(123, quantity: 2);

// Add with string ID
$item = Cart::add('SKU-001', quantity: 1);
```

### Row ID

Each cart item has a unique `rowId` generated from the item ID and options:

```php
$item1 = Cart::add($product, options: ['size' => 'S']);
$item2 = Cart::add($product, options: ['size' => 'L']);

// Different rowIds because options differ
$item1->rowId !== $item2->rowId;

// Same product + same options = same rowId (quantity increases)
$item3 = Cart::add($product, options: ['size' => 'S']);
$item1->rowId === $item3->rowId; // true
```

## Retrieving Items

### Get Single Item

```php
// By rowId
$item = Cart::get($rowId);

// By buyable ID (returns first match)
$item = Cart::find($productId);

// Check if exists
if (Cart::has($rowId)) {
    // Item exists
}
```

### Get All Items

```php
// Get CartItemCollection
$items = Cart::content();

// Iterate
foreach (Cart::content() as $item) {
    echo $item->id;          // Buyable ID
    echo $item->quantity;    // Quantity
    echo $item->unitPrice(); // Price in cents
    echo $item->subtotal();  // quantity * unitPrice
}

// Collection methods
$items->count();            // Number of items
$items->isEmpty();
$items->filter(fn($item) => $item->quantity > 1);
```

## Updating Items

### Update Quantity

```php
// Set absolute quantity
Cart::update($rowId, 5);

// Or use array syntax
Cart::update($rowId, ['quantity' => 5]);
```

### Update Options

```php
Cart::update($rowId, [
    'options' => ['size' => 'XL'],
]);
```

### Update Multiple Attributes

```php
Cart::update($rowId, [
    'quantity' => 3,
    'options' => ['size' => 'M'],
    'meta' => ['gift_wrap' => false],
]);
```

## Removing Items

### Remove Single Item

```php
Cart::remove($rowId);
```

### Clear All Items

```php
// Clear items but keep conditions
Cart::clear();

// Destroy everything (items, conditions, storage)
Cart::destroy();
```

## Counting Items

```php
// Total quantity (sum of all item quantities)
$totalQty = Cart::count();
// Example: 2 items with qty 3 and 2 = returns 5

// Unique item count
$uniqueItems = Cart::countItems();
// Example: 2 items = returns 2

// Check empty
Cart::isEmpty();     // true if no items
Cart::isNotEmpty();  // true if has items
```

## Getting Totals

All totals are returned in cents.

### Subtotal

Sum of all item prices before conditions:

```php
$subtotal = Cart::subtotal();
// 2 items at 1000 cents each, qty 2 = 4000 cents
```

### Total

Final total after all conditions (tax, discounts, etc.):

```php
$total = Cart::total();
// Subtotal 4000 + 10% tax = 4400 cents
```

### Savings

Difference between original prices and current prices:

```php
$savings = Cart::savings();
// Items on sale: original 5000, current 4000 = savings 1000 cents
```

### Condition Totals

```php
// Total tax amount
$tax = Cart::taxTotal();

// Total discount amount (negative value)
$discount = Cart::discountTotal();

// Sum of all conditions
$conditions = Cart::conditionsTotal();
```

### Calculation Breakdown

Get detailed breakdown of how total was calculated:

```php
$breakdown = Cart::getCalculationBreakdown();

// Returns:
[
    'subtotal' => 4000,
    'steps' => [
        [
            'name' => 'Summer Sale',
            'type' => 'discount',
            'value' => -400,
            'running_total' => 3600,
        ],
        [
            'name' => 'VAT',
            'type' => 'tax',
            'value' => 360,
            'running_total' => 3960,
        ],
    ],
    'total' => 3960,
]
```

## CartItem Properties

```php
$item = Cart::get($rowId);

// Identifiers
$item->rowId;        // Unique hash
$item->id;           // Buyable identifier
$item->buyableType;  // Model class name
$item->buyableId;    // Model ID

// Quantity & Options
$item->quantity;     // Current quantity
$item->options;      // Collection of options
$item->meta;         // Collection of metadata

// Prices (in cents)
$item->unitPrice();          // Current unit price
$item->originalUnitPrice();  // Original unit price (before sale)
$item->subtotal();           // unitPrice * quantity
$item->total();              // With item-level conditions
$item->savings();            // originalSubtotal - subtotal

// Model access
$item->model();      // Lazy-load the Buyable model
```

## Refreshing Prices

If product prices change during the request, refresh the cart:

```php
Cart::refreshPrices();
```

This invalidates the price cache and re-resolves all prices on next access.

## Implementing Buyable

To use models with the cart, implement these interfaces:

```php
use Cart\Contracts\Buyable;
use Cart\Contracts\Priceable;
use Cart\CartContext;

class Product extends Model implements Buyable, Priceable
{
    public function getBuyableIdentifier(): int|string
    {
        return $this->id;
    }

    public function getBuyableDescription(): string
    {
        return $this->name;
    }

    public function getBuyableType(): string
    {
        return static::class;
    }

    public function getBuyablePrice(?CartContext $context = null): int
    {
        // Return price in cents
        // Can use context for user-specific pricing
        return $this->sale_price ?? $this->price;
    }

    public function getBuyableOriginalPrice(): int
    {
        return $this->price;
    }
}
```

### Using the CanBeBought Trait

For simpler setup, use the provided trait:

```php
use Cart\Contracts\Buyable;
use Cart\Contracts\Priceable;
use Cart\Traits\CanBeBought;

class Product extends Model implements Buyable, Priceable
{
    use CanBeBought;

    // The trait auto-detects price from these attributes:
    // - price, sale_price, current_price
    // - original_price, regular_price
}
```

## Next Steps

- [Multiple Instances](multiple-instances.md) - Use wishlist and compare lists
- [Conditions](conditions.md) - Add tax and discounts
