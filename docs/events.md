# Events

Cartly dispatches events for all cart operations, allowing you to hook into the cart lifecycle.

## Configuration

Events can be enabled/disabled in config:

```php
// config/cart.php
'events' => [
    'enabled' => true,
],
```

## Event List

### Item Events

| Event | Trigger | Cancelable |
|-------|---------|------------|
| `CartItemAdding` | Before item added | Yes |
| `CartItemAdded` | After item added | No |
| `CartItemUpdating` | Before item updated | Yes |
| `CartItemUpdated` | After item updated | No |
| `CartItemRemoving` | Before item removed | Yes |
| `CartItemRemoved` | After item removed | No |

### Cart Events

| Event | Trigger | Cancelable |
|-------|---------|------------|
| `CartClearing` | Before cart cleared | Yes |
| `CartCleared` | After cart cleared | No |
| `CartConditionAdded` | After condition added | No |
| `CartConditionRemoved` | After condition removed | No |
| `CartConditionInvalidated` | After condition auto-removed due to validation | No |
| `CartMerging` | Before carts merged | Yes |
| `CartMerged` | After carts merged | No |

## Event Details

### CartItemAdding

Dispatched before an item is added to the cart.

```php
use Cart\Events\CartItemAdding;

class CartItemAdding
{
    public string $instance;
    public CartItem $item;
    public ?Buyable $buyable;
}
```

**Cancel:** Throw an exception in the listener.

**Example:**
```php
Event::listen(CartItemAdding::class, function (CartItemAdding $event) {
    // Check stock
    $product = $event->buyable;
    if ($product && $product->stock < $event->item->quantity) {
        throw new OutOfStockException('Insufficient stock');
    }

    // Log
    Log::info('Adding to cart', [
        'instance' => $event->instance,
        'item_id' => $event->item->id,
        'quantity' => $event->item->quantity,
    ]);
});
```

### CartItemAdded

Dispatched after an item is added.

```php
use Cart\Events\CartItemAdded;

class CartItemAdded
{
    public string $instance;
    public CartItem $item;
}
```

**Example:**
```php
Event::listen(CartItemAdded::class, function (CartItemAdded $event) {
    // Track analytics
    Analytics::track('add_to_cart', [
        'product_id' => $event->item->id,
        'quantity' => $event->item->quantity,
    ]);

    // Update recommendations
    Recommendations::recordInteraction($event->item->buyableId);
});
```

### CartItemUpdating

Dispatched before an item is updated.

```php
use Cart\Events\CartItemUpdating;

class CartItemUpdating
{
    public string $instance;
    public CartItem $item;
    public array $changes;  // ['quantity' => 5, 'options' => [...]]
}
```

**Example:**
```php
Event::listen(CartItemUpdating::class, function (CartItemUpdating $event) {
    $newQty = $event->changes['quantity'] ?? $event->item->quantity;

    // Validate max quantity
    if ($newQty > 10) {
        throw new \Exception('Maximum 10 items per product');
    }
});
```

### CartItemUpdated

Dispatched after an item is updated.

```php
use Cart\Events\CartItemUpdated;

class CartItemUpdated
{
    public string $instance;
    public CartItem $item;
    public array $changes;
}
```

### CartItemRemoving

Dispatched before an item is removed.

```php
use Cart\Events\CartItemRemoving;

class CartItemRemoving
{
    public string $instance;
    public CartItem $item;
}
```

### CartItemRemoved

Dispatched after an item is removed.

```php
use Cart\Events\CartItemRemoved;

class CartItemRemoved
{
    public string $instance;
    public CartItem $item;
}
```

**Example:**
```php
Event::listen(CartItemRemoved::class, function (CartItemRemoved $event) {
    // Release reserved stock
    StockReservation::release($event->item->buyableId, $event->item->quantity);
});
```

### CartClearing

Dispatched before cart is cleared.

```php
use Cart\Events\CartClearing;

class CartClearing
{
    public string $instance;
    public CartContent $content;
}
```

### CartCleared

Dispatched after cart is cleared.

```php
use Cart\Events\CartCleared;

class CartCleared
{
    public readonly string $instance;
    public readonly int $itemsCleared;  // Number of items that were cleared
}
```

### CartConditionAdded

Dispatched after a condition is added.

```php
use Cart\Events\CartConditionAdded;

class CartConditionAdded
{
    public string $instance;
    public Condition $condition;
}
```

**Example:**
```php
Event::listen(CartConditionAdded::class, function (CartConditionAdded $event) {
    if ($event->condition->getType() === 'discount') {
        Log::info('Coupon applied', [
            'name' => $event->condition->getName(),
        ]);
    }
});
```

### CartConditionRemoved

Dispatched after a condition is removed.

```php
use Cart\Events\CartConditionRemoved;

class CartConditionRemoved
{
    public string $instance;
    public Condition $condition;
}
```

### CartConditionInvalidated

Dispatched when a condition is automatically removed because it failed validation.

```php
use Cart\Events\CartConditionInvalidated;

class CartConditionInvalidated
{
    public string $instance;
    public Condition $condition;
    public ?string $reason;  // Debug message (not for end-user display)
}
```

**Example:**
```php
Event::listen(CartConditionInvalidated::class, function (CartConditionInvalidated $event) {
    Log::warning('Condition auto-removed', [
        'instance' => $event->instance,
        'condition' => $event->condition->getName(),
        'reason' => $event->reason,
    ]);

    // Notify user that their coupon was removed
    session()->flash('warning', "Coupon '{$event->condition->getName()}' is no longer valid.");
});
```

### CartMerging

Dispatched before carts are merged on login.

```php
use Cart\Events\CartMerging;

class CartMerging
{
    public CartContent $guestCart;
    public CartContent $userCart;
    public string $strategy;
    public Authenticatable $user;
}
```

**Example:**
```php
Event::listen(CartMerging::class, function (CartMerging $event) {
    Log::info('Merging carts', [
        'user_id' => $event->user->id,
        'guest_items' => $event->guestCart->items->count(),
        'user_items' => $event->userCart->items->count(),
        'strategy' => $event->strategy,
    ]);
});
```

### CartMerged

Dispatched after carts are merged.

```php
use Cart\Events\CartMerged;

class CartMerged
{
    public CartContent $resultCart;
    public int $itemsMerged;
    public Authenticatable $user;
}
```

## Registering Listeners

### In EventServiceProvider

```php
// app/Providers/EventServiceProvider.php

use Cart\Events\CartItemAdded;
use Cart\Events\CartCleared;
use App\Listeners\TrackCartAnalytics;
use App\Listeners\HandleCartCleared;

protected $listen = [
    CartItemAdded::class => [
        TrackCartAnalytics::class,
    ],
    CartCleared::class => [
        HandleCartCleared::class,
    ],
];
```

### Using Closures

```php
// In a service provider boot method
use Illuminate\Support\Facades\Event;
use Cart\Events\CartItemAdded;

Event::listen(CartItemAdded::class, function (CartItemAdded $event) {
    // Handle event
});
```

### Listener Class

```php
// app/Listeners/TrackCartAnalytics.php

namespace App\Listeners;

use Cart\Events\CartItemAdded;

class TrackCartAnalytics
{
    public function handle(CartItemAdded $event): void
    {
        // Track add to cart
        Analytics::track('add_to_cart', [
            'product_id' => $event->item->buyableId,
            'quantity' => $event->item->quantity,
            'price' => $event->item->unitPrice(),
        ]);
    }
}
```

## Common Use Cases

### Stock Validation

```php
Event::listen(CartItemAdding::class, function (CartItemAdding $event) {
    $product = Product::find($event->item->buyableId);

    if (!$product || $product->stock < $event->item->quantity) {
        throw new InsufficientStockException(
            "Only {$product->stock} items available"
        );
    }
});
```

### Abandoned Cart Tracking

```php
Event::listen(CartItemAdded::class, function (CartItemAdded $event) {
    $userId = auth()->id() ?? session()->getId();

    AbandonedCart::updateOrCreate(
        ['user_identifier' => $userId],
        ['last_activity' => now(), 'item_count' => Cart::count()]
    );
});

Event::listen(CartCleared::class, function (CartCleared $event) {
    $userId = auth()->id() ?? session()->getId();
    AbandonedCart::where('user_identifier', $userId)->delete();
});
```

### Analytics Integration

```php
Event::listen(CartItemAdded::class, function (CartItemAdded $event) {
    // Google Analytics 4
    DataLayer::push([
        'event' => 'add_to_cart',
        'ecommerce' => [
            'items' => [[
                'item_id' => $event->item->buyableId,
                'quantity' => $event->item->quantity,
                'price' => cents_to_dollars($event->item->unitPrice()),
            ]],
        ],
    ]);
});
```

### Notification on Large Orders

```php
Event::listen(CartItemAdded::class, function (CartItemAdded $event) {
    $total = Cart::total();

    if ($total > 100000) { // $1000+
        Notification::route('slack', config('services.slack.orders'))
            ->notify(new LargeOrderAlert($total));
    }
});
```

## Disabling Events

### Globally

```php
// config/cart.php
'events' => [
    'enabled' => false,
],
```

### Per Instance

```php
Cart::instance('import')
    ->setEventsEnabled(false)
    ->add($product);
```

### In Fake Mode

```php
// Events disabled by default in fake mode
Cart::fake();

// Enable events in fake mode
Cart::fake(['events' => true]);
```
