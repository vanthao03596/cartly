# User Association

Cartly supports associating carts with users and automatically merging guest carts when users log in.

## How It Works

1. **Guest User**: Cart stored with session identifier
2. **User Logs In**: Guest cart merged with existing user cart
3. **Authenticated User**: Cart stored with user identifier

## Configuration

```php
// config/cart.php
'associate' => [
    'auto_associate' => true,        // Auto-associate logged-in user
    'merge_on_login' => true,        // Merge guest cart on login
    'merge_strategy' => 'combine',   // How to merge carts
],
```

## Auto Association

When `auto_associate` is `true`, the cart automatically uses the logged-in user's identifier:

```php
// Guest adds items (stored with session ID)
Cart::add($product);

// User logs in
Auth::login($user);

// Cart now uses user ID as identifier
// Guest cart merged based on merge_strategy
```

## Manual Association

Associate a user manually:

```php
use Cart\Cart;

// Associate user
Cart::associate($user);

// Check current identifier
Cart::getIdentifier(); // Returns user ID or session ID
```

## Merge Strategies

### combine (Default)

Merges both carts, combining quantities for matching items:

```php
// Guest cart: Product A (qty 2)
// User cart: Product A (qty 1), Product B (qty 1)
// Result: Product A (qty 3), Product B (qty 1)
```

### keep_guest

Keeps only the guest cart, discards user cart:

```php
// Guest cart: Product A (qty 2)
// User cart: Product B (qty 1)
// Result: Product A (qty 2)
```

### keep_user

Keeps only the user cart, discards guest cart:

```php
// Guest cart: Product A (qty 2)
// User cart: Product B (qty 1)
// Result: Product B (qty 1)
```

## Login Handling

The package listens to Laravel's `Login` event automatically:

```php
// In CartServiceProvider
Event::listen(Login::class, function (Login $event) {
    Cart::handleLogin($event->user);
});
```

### Custom Login Handling

For custom authentication systems:

```php
// In your login controller
public function login(Request $request)
{
    // Your authentication logic
    $user = Auth::user();

    // Handle cart merge manually
    Cart::handleLogin($user);

    return redirect('/');
}
```

## Storage Identifiers

How identifiers work with different drivers:

### Session Driver

Session driver ignores identifiers (session scopes data):

```php
// Guest: stored in session
// User: stored in session (same session)
```

### Database Driver

```php
// Guest: identifier = session_id
// User: identifier = user_id
```

```sql
SELECT * FROM carts WHERE identifier = 'user_123' AND instance = 'default';
```

### Cache Driver

```php
// Guest: key = cart:session_abc:default
// User: key = cart:user_123:default
```

## Cart Merge Events

Listen to merge events for custom logic:

```php
use Cart\Events\CartMerging;
use Cart\Events\CartMerged;

// Before merge
Event::listen(CartMerging::class, function (CartMerging $event) {
    Log::info('Merging carts', [
        'guest_items' => $event->guestCart->items->count(),
        'user_items' => $event->userCart->items->count(),
        'strategy' => $event->strategy,
    ]);

    // Throw exception to prevent merge
});

// After merge
Event::listen(CartMerged::class, function (CartMerged $event) {
    Log::info('Carts merged', [
        'result_items' => $event->resultCart->items->count(),
        'items_merged' => $event->itemsMerged,
    ]);
});
```

## Multiple Instances

Each instance merges independently:

```php
// Before login:
// Cart: 2 items
// Wishlist: 5 items

// After login:
// Cart merged with user's cart
// Wishlist merged with user's wishlist
```

## Disable Auto-Merge

To handle merging manually:

```php
// config/cart.php
'associate' => [
    'auto_associate' => true,
    'merge_on_login' => false,  // Disable auto-merge
    'merge_strategy' => 'combine',
],
```

Then merge manually when needed:

```php
public function login(Request $request)
{
    // Authentication
    $user = Auth::user();

    // Custom merge logic
    if ($request->has('keep_guest_cart')) {
        Cart::setIdentifier($user->id);
        // No merge - just use user identifier going forward
    } else {
        Cart::handleLogin($user);
    }

    return redirect('/');
}
```

## Common Patterns

### Ask User Before Merge

```php
// Controller
public function confirmMerge()
{
    $guestCount = session('guest_cart_count', 0);
    $userCount = Cart::count();

    if ($guestCount > 0 && $userCount > 0) {
        return view('cart.merge-confirm', [
            'guest_count' => $guestCount,
            'user_count' => $userCount,
        ]);
    }

    return redirect('/');
}

public function handleMergeChoice(Request $request)
{
    $strategy = $request->input('strategy', 'combine');

    // Override config temporarily
    config(['cart.associate.merge_strategy' => $strategy]);

    Cart::handleLogin(Auth::user());

    return redirect('/cart');
}
```

### Persist Guest Cart Reference

```php
// Before login, save guest identifier
$guestSessionId = session()->getId();

// After login, if needed later
$guestCart = Cart::instance('default')
    ->setIdentifier($guestSessionId)
    ->content();
```

### Force User Cart Refresh

```php
// When user's saved cart might be stale
Cart::associate(Auth::user());
Cart::refreshPrices();
```

## Logout Handling

By default, logging out doesn't affect the cart. To clear on logout:

```php
// In your logout logic
public function logout()
{
    Cart::destroy();  // Clear all instances
    Auth::logout();

    return redirect('/');
}

// Or just disassociate
public function logout()
{
    Cart::setIdentifier(null);  // Revert to session identifier
    Auth::logout();

    return redirect('/');
}
```

## Next Steps

- [Events](../events.md) - Listen to cart events
- [Testing](../testing.md) - Test cart functionality
