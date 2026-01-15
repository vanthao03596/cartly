# ADR-004: Multiple Cart Instances

## Status

Accepted

## Context

E-commerce applications often need multiple "cart-like" collections:

- **Shopping Cart** - Items to purchase
- **Wishlist** - Items saved for later
- **Compare List** - Items to compare features
- **Save for Later** - Items removed from cart but saved
- **Gift Registry** - Items others can purchase for you

Traditional implementations require separate tables/storage for each:

```php
// Traditional approach
$cart = new ShoppingCart();
$wishlist = new Wishlist();  // Different class
$compare = new CompareList(); // Another class
```

This leads to:
- Code duplication
- Inconsistent APIs
- Complex migrations when adding new list types
- Difficult to move items between lists

## Decision

**Support multiple cart instances through a single, configurable system.**

```php
// All use the same Cart class
Cart::add($product);                      // Default cart
Cart::instance('wishlist')->add($product); // Wishlist
Cart::instance('compare')->add($product);  // Compare list
Cart::instance('custom')->add($product);   // Any custom list
```

## Implementation

### Instance Management

```php
class CartManager
{
    private array $instances = [];
    private string $currentInstance = 'default';

    public function instance(?string $name = null): CartInstance
    {
        $name ??= $this->currentInstance;

        if (!isset($this->instances[$name])) {
            $this->instances[$name] = $this->createInstance($name);
        }

        $this->currentInstance = $name;

        return $this->instances[$name];
    }
}
```

### Per-Instance Configuration

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
],
```

### Instance Isolation

Each instance has:
- Separate storage key/row
- Own conditions
- Own configuration
- Independent item collection

```php
// Storage keys
'cart:user_123:default'   // Shopping cart
'cart:user_123:wishlist'  // Wishlist
'cart:user_123:compare'   // Compare list
```

### Moving Between Instances

```php
// Built-in move methods
Cart::moveToWishlist($rowId);
Cart::moveToCart($rowId);
Cart::moveTo($rowId, 'compare');

// Or manually
$item = Cart::get($rowId);
Cart::remove($rowId);
Cart::instance('wishlist')->add($item->model(), $item->quantity, $item->options->toArray());
```

### Magic Methods for Configured Instances

```php
// These are equivalent
Cart::instance('wishlist')->add($product);
Cart::wishlist()->add($product);  // Magic method
```

## Consequences

### Positive

1. **Code Reuse** - Same logic for all list types
2. **Consistent API** - Same methods for cart, wishlist, etc.
3. **Easy Extensions** - Add new list types via config
4. **Item Movement** - Built-in methods to move between lists
5. **Flexible Configuration** - Per-instance settings (limits, conditions)
6. **Shared Infrastructure** - Same storage, events, resolvers

### Negative

1. **Feature Mismatch** - Some features don't apply to all instances (e.g., tax on wishlist)
2. **Naming Confusion** - "Cart" might be confusing for wishlist
3. **Configuration Complexity** - Must configure each instance

### Mitigations

1. **Conditions Per-Instance** - Only apply conditions where relevant
2. **Type Property** - Items can have instance-specific behavior
3. **Default Config** - Sensible defaults for common instances

## Use Cases

### Shopping Cart + Wishlist

```php
// Add to cart
Cart::add($product);

// Save for later (move to wishlist)
Cart::moveToWishlist($rowId);

// Add back to cart
Cart::moveToCart($rowId);
```

### Product Comparison

```php
// config: max_items: 4, allow_duplicates: false
Cart::compare()->add($product);

// Check if in compare
$inCompare = Cart::compare()->find($productId) !== null;

// Get comparison items
$items = Cart::compare()->content();
```

### Gift Registry

```php
// Custom instance
Cart::instance('registry:wedding:123')->add($product);

// Share link shows this instance
$items = Cart::instance("registry:{$type}:{$id}")->content();
```

### Multiple Carts per Store

```php
// Multi-vendor marketplace
Cart::instance("store:{$storeId}")->add($product);

// Checkout per store
foreach ($stores as $store) {
    $cart = Cart::instance("store:{$store->id}");
    // Process order for this store
}
```

## Alternatives Considered

### Separate Classes

Create distinct classes for each list type:

```php
class ShoppingCart { }
class Wishlist { }
class CompareList { }
```

**Rejected because**: Code duplication, inconsistent APIs, complex item movement.

### Single Cart with Item Flags

Store all items in one cart with flags:

```php
$item->setMeta('list_type', 'wishlist');
```

**Rejected because**: Complex queries, no per-list configuration, mixing concerns.

### Polymorphic Storage

Different storage backends per list type:

```php
'wishlist' => ['driver' => 'database'],
'compare' => ['driver' => 'session'],
```

**Rejected because**: Adds complexity without significant benefit (can still do this via custom driver).

## References

- Amazon uses similar multi-list approach (Cart, Save for Later, Lists)
- WooCommerce and Magento support wishlist as separate feature (we unified it)
