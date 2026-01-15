# ADR-003: Lazy Price Resolution with Batch Optimization

## Status

Accepted

## Context

Given that we don't store prices (ADR-002), we need to resolve them at runtime. The naive approach has performance issues:

```php
// Naive: N+1 query problem
foreach (Cart::content() as $item) {
    $price = Product::find($item->buyableId)->price;  // Query per item!
}
```

With 10 items, this executes 10 database queries. We need a strategy that:

1. Minimizes database queries
2. Avoids resolving prices when not needed
3. Caches within the request to avoid duplicate resolution
4. Supports complex pricing logic (tiers, user-specific, etc.)

## Decision

**Implement lazy price resolution with batch optimization:**

1. **Lazy** - Don't resolve prices until they're accessed
2. **Batch** - When resolving, resolve all items at once
3. **Cached** - Cache resolved prices for the request lifetime
4. **Context-Aware** - Invalidate cache when context changes

## Implementation

### Lazy Resolution via Callback

```php
class CartItem
{
    private ?callable $priceResolutionCallback = null;
    private ?ResolvedPrice $resolvedPrice = null;

    public function setPriceResolutionCallback(callable $callback): self
    {
        $this->priceResolutionCallback = $callback;
        return $this;
    }

    public function unitPrice(): int
    {
        if ($this->resolvedPrice === null && $this->priceResolutionCallback) {
            // Triggers batch resolution for all items
            ($this->priceResolutionCallback)();
        }

        return $this->resolvedPrice?->unitPrice ?? 0;
    }
}
```

### Batch Resolution

```php
class CartInstance
{
    private bool $pricesResolved = false;

    private function ensurePricesResolved(): void
    {
        if ($this->pricesResolved) {
            return;
        }

        $items = $this->content();

        if ($items->isEmpty()) {
            return;
        }

        // Single call resolves ALL items
        $prices = $this->priceResolver->resolveMany($items, $this->getContext());

        foreach ($prices as $rowId => $resolvedPrice) {
            $items->get($rowId)?->setResolvedPrice($resolvedPrice);
        }

        $this->pricesResolved = true;
    }
}
```

### Context-Based Cache Invalidation

```php
class CartContext
{
    public function hash(): string
    {
        return md5(json_encode([
            'user_id' => $this->user?->id,
            'instance' => $this->instance,
            'currency' => $this->currency,
            'locale' => $this->locale,
        ]));
    }
}

// In CartInstance
private ?string $contextHash = null;

private function ensurePricesResolved(): void
{
    $currentHash = $this->getContext()->hash();

    if ($this->pricesResolved && $this->contextHash === $currentHash) {
        return;  // Cache hit
    }

    // Context changed, re-resolve
    $this->pricesResolved = false;
    $this->contextHash = $currentHash;

    // ... resolve prices
}
```

### Optimized Resolver

```php
class BuyablePriceResolver implements PriceResolver
{
    public function resolveMany(CartItemCollection $items, CartContext $context): array
    {
        // Group by buyable type for optimized loading
        $grouped = $items->groupBy('buyableType');

        $models = [];

        foreach ($grouped as $type => $typeItems) {
            $ids = $typeItems->pluck('buyableId')->unique()->toArray();

            // Single query per type
            $models[$type] = $type::whereIn('id', $ids)->get()->keyBy('id');
        }

        // Resolve prices from loaded models
        $prices = [];

        foreach ($items as $item) {
            $model = $models[$item->buyableType][$item->buyableId] ?? null;

            if (!$model) {
                throw UnresolvablePriceException::modelNotFound(...);
            }

            $prices[$item->rowId] = new ResolvedPrice(
                unitPrice: $model->getBuyablePrice($context),
                originalPrice: $model->getBuyableOriginalPrice()
            );
        }

        return $prices;
    }
}
```

## Flow Diagram

```
Cart::total() called
       |
       v
ensurePricesResolved()
       |
       v
   Cache hit? ----Yes----> Return cached total
       |
       No
       |
       v
Group items by buyableType
       |
       v
Load models per type (N queries for N types, not N items)
       |
       v
Calculate prices from models
       |
       v
Cache prices + context hash
       |
       v
Return total
```

## Consequences

### Positive

1. **Minimal Queries** - Usually 1 query per buyable type (not per item)
2. **No Wasted Work** - Prices only resolved when accessed
3. **Request Scoped** - No stale data between requests
4. **Context Aware** - Prices update when user/currency changes
5. **Extensible** - Custom resolvers can add their own caching

### Negative

1. **First Access Cost** - First price access triggers batch load
2. **Memory Usage** - All prices held in memory during request
3. **Complexity** - Callback pattern adds indirection

### Trade-offs

| Scenario | Queries | Notes |
|----------|---------|-------|
| 10 items, 1 type | 1 | Optimal |
| 10 items, 3 types | 3 | Still good |
| Add item | 0 | No price needed yet |
| Display cart | 1-3 | Batch resolves all |
| Checkout | 0 | Already cached |

## Alternatives Considered

### Eager Loading on Add

Resolve price when item added:

```php
Cart::add($product, 2);  // Immediately resolve price
```

**Rejected because**: Wasteful if cart never displayed, doesn't adapt to context changes.

### Per-Item Caching with TTL

Cache each item's price with time-to-live:

```php
Cache::remember("price:{$productId}", 60, fn() => $product->price);
```

**Rejected because**: Stale prices within TTL, complex invalidation, doesn't support context.

### Pre-fetch on Cart Load

Load all prices when cart loaded from storage:

```php
$cart = Storage::get('cart');
$this->prefetchPrices($cart);
```

**Rejected because**: Prices resolved even if never accessed, adds latency to every cart operation.

## References

- Laravel Eloquent uses similar lazy loading with `load()` for batch optimization
- The callback pattern is similar to React's `useEffect` lazy execution
