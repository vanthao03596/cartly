# Laravel Cart Library - Technical Specification

> Version: 1.0.0-alpha
> Scope: P0 (Core) + P1 (Advanced)

---

## Table of Contents

1. [Overview](#overview)
2. [Core Concepts](#core-concepts)
3. [Design Decisions](#design-decisions)
4. [API Reference](#api-reference)
5. [Contracts](#contracts)
6. [Data Models](#data-models)
7. [Price Resolution System](#price-resolution-system)
8. [Storage Drivers](#storage-drivers)
9. [Multiple Instances](#multiple-instances)
10. [Conditions System](#conditions-system)
11. [Events](#events)
12. [Configuration](#configuration)
13. [Directory Structure](#directory-structure)
14. [Helper Functions](#helper-functions)
15. [Testing Utilities](#testing-utilities-p1)

---

## Overview

A flexible, customizable shopping cart library for Laravel with:

- **No price storage** - Prices resolved at runtime via customizable resolvers
- **Multiple storage drivers** - Session, Database, Cache, or custom
- **Multiple cart instances** - Cart, Wishlist, Compare, etc.
- **Extensible conditions** - Tax, discounts, shipping, custom fees
- **Event-driven** - Hook into any cart operation

### Design Principles

1. **Flexibility over convention** - Everything is customizable
2. **Real-time pricing** - Never store stale prices
3. **Laravel-native** - Follows Laravel patterns
4. **Performance-aware** - Batch operations, lazy loading, caching

---

## Core Concepts

### Cart Flow

```
User Action --> Cart Operation --> Event Dispatched --> Storage Updated
                                         |
                                         v
                                 Price Resolution (on read)
                                         |
                                         v
                                 Conditions Applied
                                         |
                                         v
                                    Final Totals
```

### Key Components

| Component | Responsibility |
|-----------|----------------|
| `CartManager` | Manages instances, resolves drivers |
| `CartInstance` | Single cart operations |
| `CartItem` | Individual item in cart |
| `CartContent` | Collection of items + conditions |
| `PriceResolver` | Resolves prices at runtime |
| `StorageDriver` | Persists cart data |
| `Condition` | Modifies totals |

---

## Design Decisions

### Error Handling

| Scenario | Behavior |
|----------|----------|
| Price resolve fails | Throw `UnresolvablePriceException` - cart cannot display without prices |
| Buyable model not found | Return `null` from `model()`, throw on price access |
| Invalid quantity (< 1) | Throw `InvalidQuantityException` |
| Invalid rowId | Throw `InvalidRowIdException` |
| Storage read fails | Return empty `CartContent`, log warning |
| Storage write fails | Throw exception, don't silently fail |

**Batch Resolution Failure:**
```
resolveMany() with 5 items, 1 item fails
        |
        v
Behavior: Throw UnresolvablePriceException immediately
        |
        v
Exception includes: failed item rowId, reason
```

**Rationale:** Cart totals require ALL prices. Partial results would show incorrect totals. Fail fast, let application handle (remove invalid item, show error).

**Philosophy:** Fail fast on user-facing operations (add, update, totals). Be lenient on background operations (storage read).

### Serialization

| Data | Format | Reason |
|------|--------|--------|
| Cart content to storage | JSON | Portable, debuggable, DB-friendly |
| Conditions | JSON + class name | Allow reconstruction via `fromArray()` |
| Options/Meta | JSON | Flexible structure |

**Note:** Buyable models are NOT serialized. Only `buyableType` + `buyableId` stored, model fetched on demand.

### Cart Lifecycle & Expiration

| Driver | Expiration |
|--------|-----------|
| Session | Follows session lifetime (config `session.lifetime`) |
| Database | Never expires, manual cleanup required |
| Cache | Configurable TTL (default 7 days) |

**Guest to User transition:**
```
Guest adds items --> Login --> Merge strategy applied --> Guest cart cleared
```

Merge strategies:
- `keep_guest`: User cart replaced with guest cart
- `keep_user`: Guest cart discarded
- `combine`: Items merged, quantities summed for same rowId

### Guest-to-User Merge Flow

**Trigger:** Laravel `Login` event (auto-registered listener)

```
┌─────────────────────────────────────────────────────────┐
│                    User Logs In                          │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────┐
│              Check: Guest cart exists?                   │
│              (SessionDriver, current session)            │
└─────────────────────────────────────────────────────────┘
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
           No cart                    Has cart
              │                           │
              ▼                           ▼
┌─────────────────────┐    ┌─────────────────────────────┐
│  Load user cart     │    │  Load user cart from DB     │
│  from DB (if any)   │    │  Apply merge strategy       │
└─────────────────────┘    └─────────────────────────────┘
                                          │
                                          ▼
                           ┌─────────────────────────────┐
                           │  Save merged cart to DB     │
                           │  Clear session cart         │
                           │  Switch driver to database  │
                           └─────────────────────────────┘
```

**Merge Strategy Details:**

| Strategy | Guest Cart | User Cart (DB) | Result |
|----------|------------|----------------|--------|
| `keep_guest` | 3 items | 2 items | 3 items (guest wins) |
| `keep_user` | 3 items | 2 items | 2 items (user wins) |
| `combine` | 3 items | 2 items | 3-5 items (merged) |

**Combine Logic:**
```php
foreach ($guestItems as $item) {
    if ($userCart->has($item->rowId)) {
        // Same rowId = same product + options
        $existing = $userCart->get($item->rowId);
        $existing->quantity += $item->quantity;
    } else {
        $userCart->add($item);
    }
}
```

**Conditions Handling:**
- Guest cart conditions: Discarded (not merged)
- User cart conditions: Preserved
- Rationale: Conditions often user-specific (loyalty discounts)

**Driver Switching:**
```
Before login: SessionDriver (guest)
After login:  DatabaseDriver (user_id as identifier)
```

**Events Dispatched:**
- `CartMerging` - Before merge (cancelable)
- `CartMerged` - After merge complete

### Tax Calculation Modes

**Config:** `tax.included_in_price`

| Mode | Description | Use Case |
|------|-------------|----------|
| `false` (default) | Tax added on top of price | US, Canada |
| `true` | Tax already included in display price | EU, UK, Australia |

**When `included_in_price = false`:**
```
Product price: 10000 cents ($100.00)
Tax 10%: +1000 cents
Total: 11000 cents ($110.00)

Display:
  Subtotal: $100.00
  Tax (10%): $10.00
  Total: $110.00
```

**When `included_in_price = true`:**
```
Product price: 11000 cents ($110.00) ← includes tax
Tax rate: 10%

Extract tax: price - (price / (1 + rate))
           = 11000 - (11000 / 1.10)
           = 11000 - 10000
           = 1000 cents

Display:
  Subtotal (excl. tax): $100.00
  Tax (10%): $10.00
  Total: $110.00
```

**Key Formulas:**
```php
// Tax excluded (add tax)
$taxAmount = (int) round($subtotal * $rate / 100);
$total = $subtotal + $taxAmount;

// Tax included (extract tax)
$subtotalExclTax = (int) round($priceInclTax * 100 / (100 + $rate));
$taxAmount = $priceInclTax - $subtotalExclTax;
```

**API Behavior:**
| Method | `included = false` | `included = true` |
|--------|-------------------|-------------------|
| `unitPrice()` | Price without tax | Price with tax |
| `subtotal()` | Sum without tax | Sum with tax |
| `taxTotal()` | Calculated tax | Extracted tax |
| `total()` | subtotal + tax | subtotal (tax already in) |

### Concurrency

**Approach:** Last-write-wins (optimistic)

| Scenario | Behavior |
|----------|----------|
| Two tabs update same cart | Last request wins |
| Race on quantity update | No locking, last value persisted |

**Rationale:**
- Shopping carts are user-scoped, low contention
- Complexity of locking outweighs benefits
- Users can refresh to see current state

**Future consideration:** Optional optimistic locking via `version` field for high-traffic scenarios.

### Immutability

| Property | Mutable | Reason |
|----------|---------|--------|
| `CartItem.rowId` | No | Identity |
| `CartItem.id` | No | Identity |
| `CartItem.quantity` | Yes | Core operation |
| `CartItem.options` | Yes | Allow updates |
| `CartItem.meta` | Yes | User data |
| `ResolvedPrice.*` | No | Value object |
| `CartContext.*` | No | Value object |

### Decimal Precision (Integer Cents)

**Strategy:** All monetary values stored and calculated as **integers in cents** (smallest currency unit).

| Layer | Format | Example |
|-------|--------|---------|
| Storage | Integer cents | `9999` |
| Calculation | Integer cents | `9999 + 500 = 10499` |
| Display | Formatted string | `$104.99` |

**Why Integer Cents:**
- No floating-point precision errors (`0.1 + 0.2 !== 0.3`)
- Exact arithmetic operations
- Database-friendly (INTEGER vs DECIMAL)
- Industry standard (Stripe, PayPal)

**Conversion:**
```php
// Input: Convert to cents immediately
$cents = (int) round($dollars * 100);

// Output: Convert to dollars for display only
$dollars = $cents / 100;
```

**Rounding Rules:**
| Operation | Rule |
|-----------|------|
| Percentage conditions | Round half-up after calculation |
| Division operations | Round half-up |
| Final display | Use `format.decimals` config |

**Multi-currency:** For currencies without cents (JPY, KRW), use smallest unit (1 JPY = 1 unit).

---

## API Reference

### Basic Operations

```php
// Adding items
Cart::add(string|int $id, int $quantity = 1, array $options = []): CartItem;
Cart::add(Buyable $buyable, int $quantity = 1, array $options = []): CartItem;

// Updating & Removing
Cart::update(string $rowId, int $quantity): CartItem;
Cart::update(string $rowId, array $attributes): CartItem;
Cart::remove(string $rowId): void;

// Retrieving
Cart::get(string $rowId): ?CartItem;
Cart::content(): CartItemCollection;
Cart::find(string|int $buyableId): ?CartItem;

// Checking
Cart::has(string $rowId): bool;
Cart::isEmpty(): bool;
Cart::isNotEmpty(): bool;

// Counting
Cart::count(): int;         // Total quantity
Cart::countItems(): int;    // Unique items

// Totals (all return integers in cents)
Cart::subtotal(): int;      // Before conditions, in cents
Cart::total(): int;         // After conditions, in cents
Cart::savings(): int;       // Original - current, in cents

// Clearing
Cart::clear(): void;
Cart::destroy(): void;

// Price management
Cart::refreshPrices(): void;
```

### Instance Management

```php
Cart::instance(string $name): CartInstance;
Cart::instance('wishlist')->add($product);
Cart::wishlist()->add($product);  // Magic method

Cart::instance('wishlist')->moveToCart(string $rowId): CartItem;
Cart::moveToWishlist(string $rowId): CartItem;
Cart::currentInstance(): string;
```

### Conditions

```php
Cart::condition(Condition $condition): void;
Cart::removeCondition(string $name): void;
Cart::clearConditions(): void;
Cart::getCondition(string $name): ?Condition;
Cart::getConditions(): Collection;

Cart::conditionsTotal(): int;   // In cents
Cart::taxTotal(): int;          // In cents
Cart::discountTotal(): int;     // In cents (negative value)
```

### Runtime Configuration

```php
Cart::setPriceResolver(PriceResolver $resolver): void;
Cart::setDriver(string|StorageDriver $driver): void;
Cart::associate(Authenticatable $user): void;
```

---

## Contracts

### Buyable

```php
interface Buyable
{
    public function getBuyableIdentifier(): int|string;
    public function getBuyableDescription(): string;
    public function getBuyableType(): string;
}
```

### Priceable

```php
interface Priceable
{
    public function getBuyablePrice(?CartContext $context = null): int;  // In cents
    public function getBuyableOriginalPrice(): int;                       // In cents
}
```

### PriceResolver

```php
interface PriceResolver
{
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice;

    /**
     * @param CartItemCollection $items
     * @return array<string, ResolvedPrice>  Key = rowId
     */
    public function resolveMany(CartItemCollection $items, CartContext $context): array;
}
```

### StorageDriver

```php
interface StorageDriver
{
    public function get(string $instance, ?string $identifier = null): ?CartContent;
    public function put(string $instance, CartContent $content, ?string $identifier = null): void;
    public function forget(string $instance, ?string $identifier = null): void;
    public function flush(?string $identifier = null): void;
}
```

### Condition

```php
interface Condition
{
    public function getName(): string;
    public function getType(): string;       // tax, discount, shipping, fee
    public function getTarget(): string;     // subtotal, total, item
    public function getOrder(): int;
    public function calculate(int $valueCents): int;              // Input/output in cents
    public function getCalculatedValue(int $baseValueCents): int; // In cents
    public function toArray(): array;
    public static function fromArray(array $data): static;
}
```

**Serialization Format:**
```php
// TaxCondition::toArray()
[
    'class' => 'App\\Cart\\Conditions\\TaxCondition',
    'name' => 'VAT',
    'type' => 'tax',
    'target' => 'subtotal',
    'order' => 100,
    'attributes' => [
        'rate' => 10,  // percentage
    ],
]

// Reconstruction
$data = json_decode($stored, true);
$condition = $data['class']::fromArray($data);
```

---

## Data Models

### CartItem

```php
class CartItem
{
    public readonly string $rowId;           // Hash of id + options
    public readonly int|string $id;          // Buyable identifier
    public int $quantity;
    public Collection $options;              // size, color, variant...
    public readonly ?string $buyableType;    // Model class
    public readonly int|string|null $buyableId;
    public Collection $meta;                 // Custom metadata

    protected ?ResolvedPrice $resolvedPrice;
    protected ?Buyable $buyableModel;        // Cached model
    protected Collection $conditions;        // Item-level conditions
}
```

**Key Methods:**
- `model(): ?Buyable` - Lazy load buyable model
- `unitPrice(): int` - Get resolved unit price in cents
- `subtotal(): int` - unitPrice * quantity, in cents
- `savings(): int` - originalSubtotal - subtotal, in cents

**RowId Generation:**
```php
$sorted = collect($options)->sortKeys()->toArray();
$rowId = hash('xxh128', $id . json_encode($sorted));
```

**Why xxh128:** Faster than MD5, no collision risk for this use case.

### CartContent

```php
class CartContent
{
    public CartItemCollection $items;
    public Collection $conditions;    // Cart-level conditions
    public array $meta;
}
```

### CartContext

```php
class CartContext
{
    public readonly ?Authenticatable $user;
    public readonly string $instance;
    public readonly ?string $currency;
    public readonly ?string $locale;
    public readonly array $meta;

    public static function current(): static;
}
```

### ResolvedPrice

```php
class ResolvedPrice
{
    public readonly int $unitPrice;         // In cents (e.g., 9999 = $99.99)
    public readonly int $originalPrice;     // In cents
    public readonly ?string $priceSource;   // 'base', 'sale', 'tier'...
    public readonly array $meta;

    public function hasDiscount(): bool;
    public function discountPercent(): float;   // Returns percentage (e.g., 15.0)
    public function discountAmount(): int;      // In cents
}
```

---

## Price Resolution System

### Overview

Prices are **never stored** in cart items. They are resolved at runtime, enabling:

- Real-time pricing (flash sales)
- User-specific pricing (membership tiers)
- Quantity-based pricing (bulk discounts)
- Time-based pricing (happy hour)

### Performance Strategy

**Problem:** Resolving prices on every `content()` call causes N+1 queries.

**Solution:** Three-layer optimization:

```
┌─────────────────────────────────────────────────────────┐
│                   Layer 1: Lazy Resolution               │
│  Prices resolved only when accessed (unitPrice, total)   │
└─────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────┐
│                Layer 2: Request-Scoped Cache             │
│  Once resolved, cached for entire HTTP request lifecycle │
└─────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────┐
│                  Layer 3: Batch Loading                  │
│  When resolution triggered, resolve ALL items at once    │
└─────────────────────────────────────────────────────────┘
```

#### Layer 1: Lazy Resolution

Prices are NOT resolved when calling `content()`. Resolution only happens when:
- Accessing `$item->unitPrice()` or `$item->subtotal()`
- Calling `Cart::subtotal()` or `Cart::total()`
- Explicitly calling `Cart::refreshPrices()`

```php
// No price resolution happens here
$items = Cart::content();

// Price resolution triggered here (batch)
$total = Cart::total();
```

#### Layer 2: Request-Scoped Cache

Resolved prices are cached in memory for the current request:

| Scope | Lifetime | Storage |
|-------|----------|---------|
| Request | Single HTTP request | In-memory (CartInstance property) |

**Cache Key:** `{instance}:{rowId}:{contextHash}`

**Context Hash includes:** user_id, currency, locale

**Context Hash Computation:**
- Computed once at first price access in the request
- Stored in `CartInstance` for reuse
- If context changes mid-request (rare), call `Cart::refreshPrices()` to recompute

#### Layer 3: Batch Loading

When any price is accessed, ALL unresolved items are resolved in a single batch:

```php
interface PriceResolver
{
    // Single item (fallback)
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice;

    // Batch resolution (preferred)
    public function resolveMany(CartItemCollection $items, CartContext $context): array;
}
```

**Implementation guidance for resolvers:**
- `resolveMany()` should eager-load all buyable models in one query
- Return associative array: `[rowId => ResolvedPrice]`
- Default implementation falls back to N calls of `resolve()`
- For multi-type carts, group by `buyableType` before batch loading

**Multi-Buyable Type Pattern:**
```php
public function resolveMany(CartItemCollection $items, CartContext $context): array
{
    $results = [];

    // Group items by buyable type (Product, Service, etc.)
    $grouped = $items->groupBy('buyableType');

    foreach ($grouped as $buyableType => $typeItems) {
        $ids = $typeItems->pluck('buyableId')->unique();
        $models = $buyableType::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($typeItems as $item) {
            $model = $models->get($item->buyableId);
            $results[$item->rowId] = $this->resolveFromModel($model, $context);
        }
    }

    return $results;
}
```

### Resolution Flow

```
$item->unitPrice() called
        |
        v
Check request cache ────► HIT ────► Return cached price
        |
      MISS
        |
        v
Collect all unresolved items
        |
        v
PriceResolver::resolveMany($unresolvedItems, $context)
        |
        v
Cache all results (request-scoped)
        |
        v
Return requested price
```

### Cache Invalidation

Request cache invalidated when:
- Item added/updated/removed
- `Cart::refreshPrices()` called manually
- User authentication changes
- Cart instance switched

**Note:** Cache auto-clears at end of request. No explicit cleanup needed.

### Built-in Resolvers

| Resolver | Behavior | Batch Optimized |
|----------|----------|-----------------|
| `BuyablePriceResolver` | Uses `Priceable` interface on model (default) | Yes - eager loads models |
| `ChainPriceResolver` | Tries resolvers in order, first success wins | Yes |
| `BestPriceResolver` | Tries all resolvers, returns lowest price | Yes |

### Custom Resolver Example

```php
class TieredPriceResolver implements PriceResolver
{
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice
    {
        return $this->resolveFromModel($item->model(), $context);
    }

    public function resolveMany(CartItemCollection $items, CartContext $context): array
    {
        // Use multi-type pattern from above for production
        // Simplified single-type example:
        $ids = $items->pluck('buyableId')->unique();
        $models = Product::whereIn('id', $ids)->get()->keyBy('id');

        $results = [];
        foreach ($items as $item) {
            $results[$item->rowId] = $this->resolveFromModel(
                $models->get($item->buyableId),
                $context
            );
        }
        return $results;
    }

    private function resolveFromModel(Buyable $model, CartContext $context): ResolvedPrice
    {
        $basePriceCents = $model->price;

        $multiplier = match ($context->user?->tier) {
            'vip' => 0.80,
            'gold' => 0.90,
            default => 1.0,
        };

        return new ResolvedPrice(
            unitPrice: (int) round($basePriceCents * $multiplier),
            originalPrice: $basePriceCents,
            priceSource: $context->user?->tier ?? 'base',
        );
    }
}
```

---

## Storage Drivers

### Overview

| Driver | Use Case | Persistence |
|--------|----------|-------------|
| `SessionDriver` | Guest users (default) | Session lifetime |
| `DatabaseDriver` | Logged-in users | Permanent |
| `CacheDriver` | High-traffic, Redis | Configurable TTL |

### Identifier Behavior

| Driver | Identifier |
|--------|-----------|
| `session` | Ignored (session scopes data) |
| `database` | Required (user_id or session_id) |
| `cache` | Required (part of cache key) |

**Identifier Resolution:**
```php
// Authenticated: "user_42"
// Guest: "session_abc123"
```

### Database Migration

```php
Schema::create('carts', function (Blueprint $table) {
    $table->id();
    $table->string('instance')->default('default');
    $table->string('identifier')->nullable();
    $table->longText('content');
    $table->timestamps();

    $table->unique(['instance', 'identifier']);
});
```

---

## Multiple Instances

### Usage

```php
Cart::add($product);                          // default instance
Cart::instance('wishlist')->add($product);    // wishlist
Cart::wishlist()->add($product);              // magic method

Cart::instance('wishlist')->moveToCart($rowId);
Cart::moveToWishlist($rowId);
```

### Instance Configuration

```php
'instances' => [
    'default' => [
        'conditions' => ['tax', 'shipping'],
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

---

## Conditions System

### Overview

Conditions modify cart totals (tax, discounts, shipping, fees).

### Resolution Order (Item vs Cart)

```
┌─────────────────────────────────────────────────────────┐
│                    For Each CartItem                     │
│  1. Apply item-level conditions (sorted by order)        │
│  2. Calculate item final price                           │
└─────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────┐
│                      Cart Level                          │
│  3. Sum all item final prices = Subtotal                 │
│  4. Apply cart-level conditions (sorted by order)        │
│  5. Calculate final total                                │
└─────────────────────────────────────────────────────────┘
```

**Example:**
```
Item A: 5000 cents
  - Item discount -10%: -500 → 4500 cents

Item B: 3000 cents
  - No item conditions → 3000 cents

Subtotal: 4500 + 3000 = 7500 cents

Cart conditions:
  - [order 50] Cart discount -5%: -375 → 7125 cents
  - [order 100] Tax 10%: +713 → 7838 cents

Total: 7838 cents ($78.38)
```

**Key Rules:**
- Item conditions only affect that item's subtotal
- Cart conditions apply to aggregated subtotal
- Same `order` property works independently at item and cart level

### Condition Name Uniqueness

**Scope:** Name must be unique within its scope (cart-level OR per-item).

| Scope | Uniqueness |
|-------|------------|
| Cart-level conditions | Unique per cart instance |
| Item-level conditions | Unique per item |
| Cross-scope | Same name allowed (cart "VAT" + item "VAT" = OK) |

**Behavior on Duplicate Name:**

```php
// Cart-level: REPLACE (silent)
Cart::condition(new TaxCondition('VAT', 10));
Cart::condition(new TaxCondition('VAT', 15));  // Replaces previous
Cart::getCondition('VAT')->rate;  // 15

// Item-level: REPLACE (silent)
$item->condition(new DiscountCondition('Promo', 10));
$item->condition(new DiscountCondition('Promo', 20));  // Replaces
```

**Rationale:**
- Replace is more practical than error (re-applying coupon, updating tax rate)
- Explicit `removeCondition()` not required before update
- Prevents duplicate conditions stacking unintentionally

**Cross-scope Example:**
```
Cart conditions:
  - "VAT" (10% tax)           ← cart-level

Item A conditions:
  - "VAT" (5% reduced rate)   ← item-level, different scope = OK

Result: Item A gets 5% tax, other items get 10% tax
```

**API for Checking:**
```php
Cart::hasCondition('VAT'): bool;
$item->hasCondition('Promo'): bool;
```

### Condition Properties

| Property | Description |
|----------|-------------|
| `name` | Unique identifier |
| `type` | tax, discount, shipping, fee |
| `target` | subtotal, total, item |
| `order` | Apply order (lower = first) |

### Built-in Conditions

| Condition | Type | Description |
|-----------|------|-------------|
| `TaxCondition` | tax | Percentage-based, order: 100 |
| `DiscountCondition` | discount | Percentage or fixed, order: 50 |
| `ShippingCondition` | shipping | Fixed amount, order: 200 |
| `PercentageCondition` | - | Base class for percentage |
| `FixedCondition` | - | Base class for fixed amount |

### Calculation Order

```
Subtotal: 10000 cents ($100.00)
    |
[order: 50] Discount -15%: -1500 --> 8500 cents ($85.00)
    |
[order: 100] Tax 10%: +850 --> 9350 cents ($93.50)
    |
[order: 200] Shipping: +599 --> 9949 cents ($99.49)
    |
Total: 9949 cents ($99.49)
```

**Rounding in Conditions:**
- Percentage calculations: `(int) round($cents * $percent / 100)`
- Always round half-up to avoid losing cents

### Usage

```php
Cart::condition(new TaxCondition('VAT', 10));                    // 10%
Cart::condition(new DiscountCondition('Sale', 15, 'percentage')); // 15%
Cart::condition(new ShippingCondition('Standard', 599));          // 599 cents = $5.99

// Item-level
$item = Cart::get($rowId);
$item->condition(new DiscountCondition('Promo', 10));            // 10%

// Totals (all in cents)
Cart::subtotal();      // 10000 ($100.00)
Cart::discountTotal(); // -1500 (-$15.00)
Cart::taxTotal();      // 850 ($8.50)
Cart::total();         // 9949 ($99.49)

// Formatted for display
format_price(Cart::total());  // "$99.49"
```

---

## Events

### Available Events

| Event | When | Cancelable |
|-------|------|------------|
| `CartItemAdding` | Before add | Yes (throw exception) |
| `CartItemAdded` | After add | No |
| `CartItemUpdating` | Before update | Yes |
| `CartItemUpdated` | After update | No |
| `CartItemRemoving` | Before remove | Yes |
| `CartItemRemoved` | After remove | No |
| `CartClearing` | Before clear | Yes |
| `CartCleared` | After clear | No |
| `CartConditionAdded` | After condition added | No |
| `CartConditionRemoved` | After condition removed | No |
| `CartMerging` | Before guest-to-user merge | Yes |
| `CartMerged` | After merge complete | No |

### Event Structures

```php
// Item events
class CartItemAdding
{
    public readonly string $instance;
    public readonly CartItem $item;
    public readonly ?Buyable $buyable;
}

class CartItemAdded
{
    public readonly string $instance;
    public readonly CartItem $item;
}

class CartItemUpdating
{
    public readonly string $instance;
    public readonly CartItem $item;
    public readonly array $changes;  // ['quantity' => 5, 'meta' => [...]]
}

class CartItemUpdated
{
    public readonly string $instance;
    public readonly CartItem $item;
    public readonly array $changes;
}

class CartItemRemoved
{
    public readonly string $instance;
    public readonly CartItem $item;  // Item that was removed
}

// Merge events
class CartMerging
{
    public readonly CartContent $guestCart;
    public readonly CartContent $userCart;
    public readonly string $strategy;
    public readonly Authenticatable $user;
}

class CartMerged
{
    public readonly CartContent $resultCart;
    public readonly int $itemsMerged;
    public readonly Authenticatable $user;
}
```

### Canceling Operations

Throw exception to cancel:

```php
Event::listen(CartItemAdding::class, function ($event) {
    if ($event->buyable->stock < $event->item->quantity) {
        throw new InsufficientStockException($event->buyable);
    }
});
```

---

## Configuration

```php
// config/cart.php
return [
    'driver' => env('CART_DRIVER', 'session'),

    'default_instance' => 'default',

    'drivers' => [
        'session' => ['key' => 'cart'],
        'database' => ['table' => 'carts', 'connection' => null],
        'cache' => ['store' => null, 'prefix' => 'cart', 'ttl' => 60 * 24 * 7],
    ],

    'price_resolver' => null,  // Default: BuyablePriceResolver

    'instances' => [
        'default' => ['conditions' => [], 'max_items' => null],
        'wishlist' => ['conditions' => [], 'max_items' => 50],
        'compare' => ['max_items' => 4],
    ],

    'tax' => [
        'enabled' => true,
        'rate' => 0,
        'included_in_price' => false,
    ],

    'format' => [
        'decimals' => 2,
        'decimal_separator' => '.',
        'thousand_separator' => ',',
    ],

    'associate' => [
        'auto_associate' => true,
        'merge_on_login' => true,
        'merge_strategy' => 'combine',  // keep_guest, keep_user, combine
    ],

    'events' => ['enabled' => true],
];
```

---

## Directory Structure

```
src/
├── Cart.php                     # Facade
├── CartManager.php              # Manager (driver resolution)
├── CartInstance.php             # Single cart operations
├── CartItem.php
├── CartContent.php
├── CartContext.php
├── CartItemCollection.php
├── ResolvedPrice.php
│
├── Contracts/
│   ├── Buyable.php
│   ├── Priceable.php
│   ├── PriceResolver.php
│   ├── StorageDriver.php
│   └── Condition.php
│
├── Drivers/
│   ├── SessionDriver.php
│   ├── DatabaseDriver.php
│   ├── CacheDriver.php
│   └── ArrayDriver.php          # For testing
│
├── Resolvers/
│   ├── BuyablePriceResolver.php
│   ├── ChainPriceResolver.php
│   └── BestPriceResolver.php
│
├── Conditions/
│   ├── Condition.php            # Base class
│   ├── PercentageCondition.php
│   ├── FixedCondition.php
│   ├── TaxCondition.php
│   ├── DiscountCondition.php
│   └── ShippingCondition.php
│
├── Events/
│   ├── CartItemAdding.php
│   ├── CartItemAdded.php
│   ├── CartItemUpdating.php
│   ├── CartItemUpdated.php
│   ├── CartItemRemoving.php
│   ├── CartItemRemoved.php
│   ├── CartClearing.php
│   ├── CartCleared.php
│   ├── CartConditionAdded.php
│   ├── CartConditionRemoved.php
│   ├── CartMerging.php
│   └── CartMerged.php
│
├── Traits/
│   └── CanBeBought.php          # Buyable + Priceable trait
│
├── Exceptions/
│   ├── CartException.php
│   ├── InvalidRowIdException.php
│   ├── UnresolvablePriceException.php
│   └── ...
│
├── Support/
│   ├── helpers.php              # cart(), cart_count(), cart_total()
│   └── CalculationPipeline.php
│
└── CartServiceProvider.php

config/cart.php
database/migrations/create_carts_table.php
```

---

## Helper Functions

```php
cart(?string $instance = null): CartInstance|CartManager;
cart_count(?string $instance = null): int;
cart_subtotal(?string $instance = null, bool $formatted = false): int|string;   // Cents or formatted
cart_total(?string $instance = null, bool $formatted = false): int|string;      // Cents or formatted
format_price(int $cents, ?string $currency = null): string;                     // Cents to display
cents_to_dollars(int $cents): float;                                            // For external APIs
dollars_to_cents(float $dollars): int;                                          // From user input
```

---

## Testing Utilities (P1)

### Fake Driver

```php
// In tests
Cart::fake();

// Uses ArrayDriver (in-memory, no persistence)
// Events still dispatched by default
Cart::fake(['events' => false]);  // Disable events
```

### Fake Resolver

```php
// Fixed price for all items
Cart::fakeResolver(1000);  // All items = 1000 cents

// Custom resolver
Cart::fakeResolver(function (CartItem $item) {
    return new ResolvedPrice(
        unitPrice: $item->id * 100,
        originalPrice: $item->id * 100,
    );
});
```

### Assertions

```php
Cart::assertItemCount(3);
Cart::assertHas($productId);
Cart::assertTotal(5000);  // In cents
Cart::assertEmpty();
Cart::assertConditionApplied('VAT');
```

### Factory

```php
// Create cart with predefined items (for testing)
$cart = Cart::factory()
    ->withItems([
        ['id' => 1, 'quantity' => 2, 'price' => 1000],
        ['id' => 2, 'quantity' => 1, 'price' => 2000],
    ])
    ->withCondition(new TaxCondition('VAT', 10))
    ->create();
```

---

## Next Steps (P2 - Future)

- Coupon/Voucher system
- Validation pipeline
- Bundle/Package support
- Blade directives

---

*Last updated: January 2026*
