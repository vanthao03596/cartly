# ADR-002: No Stored Prices in Cart

## Status

Accepted

## Context

Traditional shopping cart implementations store the product price at the time of adding to cart:

```php
// Traditional approach
$cartItem = [
    'product_id' => 123,
    'quantity' => 2,
    'price' => 2999,  // Stored at add time
];
```

This approach has significant problems:

1. **Price Staleness** - If a flash sale starts, cart shows old prices
2. **Price Inconsistency** - Different users see different prices for same product
3. **Checkout Surprises** - Price might change between cart and checkout
4. **Complex Sync** - Need to update all carts when prices change
5. **Security Risk** - Stored prices can be manipulated

## Decision

**Never store prices in the cart. Resolve all prices at runtime through a pluggable PriceResolver.**

```php
// Our approach
$cartItem = [
    'product_id' => 123,
    'quantity' => 2,
    'buyableType' => 'App\Models\Product',
    'buyableId' => 123,
    // NO price field!
];

// Price resolved when accessed
$item->unitPrice();  // Calls PriceResolver->resolve($item, $context)
```

## Implementation

### Price Resolution

```php
interface PriceResolver
{
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice;
    public function resolveMany(CartItemCollection $items, CartContext $context): array;
}
```

### Context-Aware Pricing

```php
readonly class CartContext
{
    public ?Authenticatable $user;  // For user-specific pricing
    public string $instance;         // Cart instance
    public ?string $currency;        // For multi-currency
    public ?string $locale;          // For regional pricing
}
```

### Lazy Resolution

```php
// Price not resolved until accessed
$item = Cart::add($product, 2);

// Later, when displayed
$price = $item->unitPrice();  // NOW resolves price
```

## Consequences

### Positive

1. **Always Fresh Prices** - Users see current prices
2. **Dynamic Pricing** - Easy to implement:
   - Flash sales
   - User-specific pricing
   - Tiered pricing
   - Currency conversion
   - Time-based pricing
3. **No Sync Required** - Price changes apply instantly
4. **Simpler Storage** - Less data to store and serialize
5. **Security** - No price manipulation possible
6. **Flexibility** - Can chain multiple resolvers

### Negative

1. **Performance Overhead** - Need to query prices on each request
2. **Missing Product Risk** - If product deleted, price resolution fails
3. **Complexity** - Resolver pattern adds abstraction layer
4. **Testing** - Need to mock resolver in tests

### Mitigations

1. **Batch Resolution** - `resolveMany()` loads all prices in one query
2. **Request Caching** - Prices cached for request lifetime
3. **Graceful Failure** - `UnresolvablePriceException` for missing products
4. **Fake Resolver** - Easy testing with `Cart::fakeResolver(1000)`

## Example Flow

```php
// 1. Add item (no price stored)
Cart::add($product, 2);

// 2. Display cart
foreach (Cart::content() as $item) {
    // Price resolved here, using context
    echo $item->unitPrice();
}

// 3. Checkout
$total = Cart::total();  // Fresh prices at checkout time
```

## Alternatives Considered

### Store Price + Validate at Checkout

Store price but validate at checkout:

```php
if ($storedPrice !== $currentPrice) {
    // Notify user, update cart
}
```

**Rejected because**: Still requires price storage, sync logic, and creates UX issues.

### Price Snapshots

Store price with timestamp, invalidate after X minutes:

```php
$cartItem['price'] = 2999;
$cartItem['price_cached_at'] = now();
```

**Rejected because**: Arbitrary TTL, still shows stale prices within window.

## References

- Real-time pricing is standard in modern e-commerce (Amazon, Shopify)
- Stripe/PayPal calculate prices at transaction time, not from stored values
