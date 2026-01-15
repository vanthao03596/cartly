# Multiple Instances

Cartly supports multiple cart instances, allowing you to manage separate lists like shopping cart, wishlist, and product comparison.

## Built-in Instances

Three instances are pre-configured:

| Instance | Purpose | Max Items | Allow Duplicates |
|----------|---------|-----------|------------------|
| `default` | Main shopping cart | Unlimited | Yes |
| `wishlist` | Saved for later | 50 | Yes |
| `compare` | Product comparison | 4 | No |

## Switching Instances

### Using instance() Method

```php
use Cart\Cart;

// Work with default cart
Cart::add($product);
Cart::total();

// Switch to wishlist
Cart::instance('wishlist')->add($product);
Cart::instance('wishlist')->content();

// Switch to compare
Cart::instance('compare')->add($product);
```

### Using Magic Methods

Pre-configured instances have magic method shortcuts:

```php
// These are equivalent:
Cart::instance('wishlist')->add($product);
Cart::wishlist()->add($product);

Cart::instance('compare')->add($product);
Cart::compare()->add($product);
```

### Get Current Instance

```php
$instanceName = Cart::currentInstance();
// Returns: 'default', 'wishlist', etc.
```

## Moving Items Between Instances

### Move to Wishlist

```php
// Add to cart
$item = Cart::add($product);

// Move to wishlist
$movedItem = Cart::moveToWishlist($item->rowId);

// Item is now in wishlist, removed from cart
Cart::has($item->rowId);           // false
Cart::wishlist()->has($movedItem->rowId); // true
```

### Move to Cart

```php
// Move from wishlist back to cart
$item = Cart::moveToCart($rowId);
```

### Move to Any Instance

```php
// Move item to specific instance
$item = Cart::moveTo($rowId, 'compare');

// Or from instance
$item = Cart::instance('wishlist')->moveTo($rowId, Cart::instance('default'));
```

## Instance Isolation

Each instance is completely isolated:

```php
// Add to cart
Cart::add($productA, quantity: 2);

// Add to wishlist
Cart::wishlist()->add($productB, quantity: 1);

// Cart operations don't affect wishlist
Cart::count();              // 2
Cart::wishlist()->count();  // 1

Cart::clear();              // Only clears default cart
Cart::wishlist()->count();  // Still 1
```

## Configuring Instances

### In Configuration File

```php
// config/cart.php
'instances' => [
    'default' => [
        'conditions' => [],
        'max_items' => null,
    ],

    'wishlist' => [
        'conditions' => [],
        'max_items' => 50,
    ],

    'compare' => [
        'max_items' => 4,
        'allow_duplicates' => false,
    ],

    // Add custom instance
    'saved_for_later' => [
        'conditions' => [],
        'max_items' => 100,
    ],
],
```

### Instance Options

| Option | Type | Description |
|--------|------|-------------|
| `conditions` | array | Pre-configured conditions to apply |
| `max_items` | int\|null | Maximum unique items allowed |
| `allow_duplicates` | bool | Allow same item multiple times |

### Max Items Limit

When `max_items` is set, adding beyond the limit throws an exception:

```php
// config: 'compare' => ['max_items' => 4]

Cart::compare()->add($product1);
Cart::compare()->add($product2);
Cart::compare()->add($product3);
Cart::compare()->add($product4);
Cart::compare()->add($product5); // Throws MaxItemsExceededException
```

### Duplicate Prevention

When `allow_duplicates` is `false`, adding the same item throws an exception:

```php
// config: 'compare' => ['allow_duplicates' => false]

Cart::compare()->add($product);
Cart::compare()->add($product); // Throws DuplicateItemException
```

## Per-Instance Conditions

Configure conditions that only apply to specific instances:

```php
// config/cart.php
'instances' => [
    'default' => [
        'conditions' => [
            [
                'class' => \Cart\Conditions\TaxCondition::class,
                'params' => ['name' => 'VAT', 'rate' => 20],
            ],
        ],
    ],

    // Wishlist has no tax
    'wishlist' => [
        'conditions' => [],
    ],
],
```

## Custom Instances

Create custom instances for any purpose:

```php
// Access custom instance
Cart::instance('saved_for_later')->add($product);

// Or register magic method in service provider
// Then use: Cart::savedForLater()->add($product);
```

### Dynamic Instances

Create instances on the fly:

```php
// Per-store carts
Cart::instance("store_{$storeId}")->add($product);

// Per-user temporary carts
Cart::instance("temp_{$userId}")->add($product);
```

## Storage Considerations

Each instance stores separately:

- **Session Driver**: Stored under `cart.{instance}` key
- **Database Driver**: Separate row per instance (unique on instance + identifier)
- **Cache Driver**: Separate cache key per instance

```sql
-- Database storage example
SELECT * FROM carts WHERE identifier = 'user_123';

-- Returns multiple rows:
-- instance: 'default', content: {...}
-- instance: 'wishlist', content: {...}
```

## Common Patterns

### Save for Later

```php
// User clicks "Save for Later"
public function saveForLater(string $rowId)
{
    Cart::moveToWishlist($rowId);

    return back()->with('message', 'Item saved to wishlist');
}

// User clicks "Move to Cart"
public function moveToCart(string $rowId)
{
    Cart::moveToCart($rowId);

    return back()->with('message', 'Item moved to cart');
}
```

### Quick Compare

```php
// Toggle compare
public function toggleCompare(Product $product)
{
    $compare = Cart::compare();
    $existing = $compare->find($product->id);

    if ($existing) {
        $compare->remove($existing->rowId);
        $message = 'Removed from compare';
    } else {
        try {
            $compare->add($product);
            $message = 'Added to compare';
        } catch (MaxItemsExceededException $e) {
            $message = 'Compare list is full (max 4 items)';
        }
    }

    return back()->with('message', $message);
}
```

### Display All Instances

```php
// In controller
public function index()
{
    return view('cart.index', [
        'cart' => Cart::content(),
        'wishlist' => Cart::wishlist()->content(),
        'compare' => Cart::compare()->content(),
    ]);
}
```

## Next Steps

- [Conditions](conditions.md) - Apply tax and discounts
- [User Association](user-association.md) - Handle logged-in users
