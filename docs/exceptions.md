# Exceptions

Cartly provides custom exceptions for common error scenarios.

## Exception Hierarchy

```
CartException (base)
├── InvalidQuantityException
├── InvalidRowIdException
├── UnresolvablePriceException
├── MaxItemsExceededException
└── DuplicateItemException
```

## CartException

Base exception for all cart-related errors.

```php
use Cart\Exceptions\CartException;
```

### Methods

```php
$exception->setInstance(string $instance): self
$exception->getInstance(): ?string
```

### Example

```php
try {
    Cart::add($product);
} catch (CartException $e) {
    Log::error('Cart error', [
        'message' => $e->getMessage(),
        'instance' => $e->getInstance(),
    ]);
}
```

## InvalidQuantityException

Thrown when quantity is less than 1.

```php
use Cart\Exceptions\InvalidQuantityException;
```

### When Thrown

- `Cart::add($item, quantity: 0)`
- `Cart::add($item, quantity: -1)`
- `Cart::update($rowId, 0)`
- `Cart::update($rowId, ['quantity' => -5])`

### Methods

```php
InvalidQuantityException::forQuantity(int $quantity): self
$exception->getQuantity(): int
```

### Message Format

```
The quantity [X] is invalid. Quantity must be at least 1.
```

### Example

```php
try {
    Cart::add($product, quantity: 0);
} catch (InvalidQuantityException $e) {
    return back()->with('error', 'Quantity must be at least 1');
}
```

## InvalidRowIdException

Thrown when a row ID is not found in the cart.

```php
use Cart\Exceptions\InvalidRowIdException;
```

### When Thrown

- `Cart::get($nonExistentRowId)`
- `Cart::update($nonExistentRowId, 5)`
- `Cart::remove($nonExistentRowId)`
- `Cart::moveTo($nonExistentRowId, 'wishlist')`

### Methods

```php
InvalidRowIdException::forRowId(string $rowId): self
$exception->getRowId(): string
```

### Message Format

```
The cart does not contain an item with rowId [abc123...].
```

### Example

```php
try {
    Cart::update($rowId, 5);
} catch (InvalidRowIdException $e) {
    return back()->with('error', 'Item not found in cart');
}
```

## UnresolvablePriceException

Thrown when a price cannot be resolved for an item.

```php
use Cart\Exceptions\UnresolvablePriceException;
```

### When Thrown

- Model not found for buyable ID
- Model doesn't implement `Priceable` interface
- Custom price resolver fails

### Factory Methods

```php
UnresolvablePriceException::forItem(
    string $rowId,
    ?string $buyableType,
    int|string|null $buyableId
): self

UnresolvablePriceException::modelNotFound(
    string $rowId,
    string $buyableType,
    int|string $buyableId
): self

UnresolvablePriceException::notPriceable(
    string $rowId,
    string $buyableType
): self
```

### Getters

```php
$exception->getRowId(): string
$exception->getBuyableType(): ?string
$exception->getBuyableId(): int|string|null
```

### Message Formats

```
Unable to resolve price for item [rowId]. Buyable not found: Type\Class (ID: 123)
Unable to resolve price for item [rowId]. Type\Class does not implement Priceable.
Unable to resolve price for item [rowId].
```

### Example

```php
try {
    $total = Cart::total();
} catch (UnresolvablePriceException $e) {
    Log::error('Price resolution failed', [
        'rowId' => $e->getRowId(),
        'type' => $e->getBuyableType(),
        'id' => $e->getBuyableId(),
    ]);

    // Remove problematic item
    Cart::remove($e->getRowId());

    return back()->with('error', 'Some items are no longer available');
}
```

## MaxItemsExceededException

Thrown when adding items would exceed the instance's `max_items` limit.

```php
use Cart\Exceptions\MaxItemsExceededException;
```

### When Thrown

- Adding item when `max_items` limit is reached
- Configured in `cart.instances.{name}.max_items`

### Factory Method

```php
MaxItemsExceededException::forInstance(
    string $instance,
    int $maxItems,
    int $currentCount
): self
```

### Getters

```php
$exception->getMaxItems(): int
$exception->getCurrentCount(): int
```

### Message Format

```
Cannot add item. Maximum of [4] items allowed in [compare] (current: [4]).
```

### Example

```php
try {
    Cart::compare()->add($product);
} catch (MaxItemsExceededException $e) {
    return back()->with('error',
        "Compare list is full. Maximum {$e->getMaxItems()} items allowed."
    );
}
```

## DuplicateItemException

Thrown when adding a duplicate item and `allow_duplicates` is false.

```php
use Cart\Exceptions\DuplicateItemException;
```

### When Thrown

- Adding item that already exists in cart
- Configured in `cart.instances.{name}.allow_duplicates = false`

### Factory Method

```php
DuplicateItemException::forBuyable(
    string $instance,
    int|string $buyableId,
    string $existingRowId
): self
```

### Getters

```php
$exception->getBuyableId(): int|string
$exception->getExistingRowId(): string
```

### Message Format

```
Item [123] already exists in [compare] (rowId: abc123...).
```

### Example

```php
try {
    Cart::compare()->add($product);
} catch (DuplicateItemException $e) {
    return back()->with('error', 'This product is already in your compare list');
}
```

## Handling Exceptions

### Controller Example

```php
use Cart\Exceptions\CartException;
use Cart\Exceptions\InvalidQuantityException;
use Cart\Exceptions\MaxItemsExceededException;

class CartController extends Controller
{
    public function add(Request $request, Product $product)
    {
        try {
            $item = Cart::add($product, $request->quantity);

            return response()->json([
                'success' => true,
                'item' => $item->toArray(),
                'count' => Cart::count(),
            ]);

        } catch (InvalidQuantityException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid quantity',
            ], 422);

        } catch (MaxItemsExceededException $e) {
            return response()->json([
                'success' => false,
                'error' => "Cart is full (max {$e->getMaxItems()} items)",
            ], 422);

        } catch (CartException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to add item to cart',
            ], 500);
        }
    }
}
```

### Global Exception Handler

```php
// app/Exceptions/Handler.php

use Cart\Exceptions\CartException;
use Cart\Exceptions\InvalidRowIdException;

public function register(): void
{
    $this->renderable(function (InvalidRowIdException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Item not found in cart',
            ], 404);
        }

        return redirect()->route('cart.index')
            ->with('error', 'Item not found in cart');
    });

    $this->renderable(function (CartException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        return back()->with('error', $e->getMessage());
    });
}
```

## Exception Summary

| Exception | Cause | Recovery |
|-----------|-------|----------|
| `InvalidQuantityException` | qty < 1 | Validate input |
| `InvalidRowIdException` | Item not found | Refresh cart view |
| `UnresolvablePriceException` | Missing/invalid model | Remove item |
| `MaxItemsExceededException` | Limit reached | Inform user |
| `DuplicateItemException` | Already exists | Inform user |
