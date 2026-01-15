# Helper Functions

Cartly provides several global helper functions for convenience.

## cart()

Get the cart manager or a specific instance.

```php
function cart(?string $instance = null): CartInstance|CartManager
```

**Parameters:**
- `$instance` - Instance name, or null for CartManager

**Returns:** `CartInstance` if instance specified, `CartManager` otherwise

**Examples:**
```php
// Get CartManager
$manager = cart();

// Get specific instance
$wishlist = cart('wishlist');

// Chain operations
cart('wishlist')->add($product);
cart()->instance('compare')->content();
```

## cart_count()

Get the total quantity of items in the cart.

```php
function cart_count(?string $instance = null): int
```

**Parameters:**
- `$instance` - Instance name (default: 'default')

**Returns:** Sum of all item quantities

**Examples:**
```php
// Default cart count
$count = cart_count();

// Wishlist count
$wishlistCount = cart_count('wishlist');

// In Blade template
<span class="badge">{{ cart_count() }}</span>
```

## cart_subtotal()

Get the cart subtotal.

```php
function cart_subtotal(?string $instance = null, bool $formatted = false): int|string
```

**Parameters:**
- `$instance` - Instance name (default: 'default')
- `$formatted` - Return formatted string if true

**Returns:** Cents (int) or formatted string

**Examples:**
```php
// Get cents
$cents = cart_subtotal();  // 9999

// Get formatted
$display = cart_subtotal(formatted: true);  // "$99.99"

// Specific instance
$wishlistSubtotal = cart_subtotal('wishlist', true);
```

## cart_total()

Get the cart total (after conditions).

```php
function cart_total(?string $instance = null, bool $formatted = false): int|string
```

**Parameters:**
- `$instance` - Instance name (default: 'default')
- `$formatted` - Return formatted string if true

**Returns:** Cents (int) or formatted string

**Examples:**
```php
// Get cents
$cents = cart_total();  // 10999

// Get formatted
$display = cart_total(formatted: true);  // "$109.99"

// In Blade
Total: {{ cart_total(formatted: true) }}
```

## format_price()

Format a price in cents to a display string.

```php
function format_price(int $cents, ?string $currency = null): string
```

**Parameters:**
- `$cents` - Price in cents
- `$currency` - Currency code (not used in default implementation)

**Returns:** Formatted price string

**Configuration:** Uses values from `config/cart.php`:
- `format.decimals` - Decimal places (default: 2)
- `format.decimal_separator` - Decimal character (default: '.')
- `format.thousand_separator` - Thousand separator (default: ',')
- `format.currency_symbol` - Currency symbol (default: '$')
- `format.currency_position` - 'before' or 'after' (default: 'before')

**Examples:**
```php
format_price(9999);      // "$99.99"
format_price(123456);    // "$1,234.56"
format_price(50);        // "$0.50"
format_price(0);         // "$0.00"

// With config: position => 'after', symbol => '€'
format_price(9999);      // "99.99€"
```

## cents_to_dollars()

Convert cents to dollars (float).

```php
function cents_to_dollars(int $cents): float
```

**Parameters:**
- `$cents` - Amount in cents

**Returns:** Amount in dollars

**Use case:** When external APIs require dollar amounts.

**Examples:**
```php
cents_to_dollars(9999);   // 99.99
cents_to_dollars(100);    // 1.0
cents_to_dollars(50);     // 0.5

// For Stripe
$amount = cents_to_dollars(Cart::total());
```

## dollars_to_cents()

Convert dollars to cents (int).

```php
function dollars_to_cents(float $dollars): int
```

**Parameters:**
- `$dollars` - Amount in dollars

**Returns:** Amount in cents (rounded)

**Use case:** When converting user input to internal format.

**Examples:**
```php
dollars_to_cents(99.99);  // 9999
dollars_to_cents(1.5);    // 150
dollars_to_cents(0.01);   // 1

// From user input
$price = dollars_to_cents((float) $request->price);
```

## Usage in Blade Templates

```blade
{{-- Cart count badge --}}
<a href="/cart">
    Cart ({{ cart_count() }})
</a>

{{-- Display totals --}}
<div class="cart-summary">
    <p>Subtotal: {{ cart_subtotal(formatted: true) }}</p>
    <p>Total: {{ cart_total(formatted: true) }}</p>
</div>

{{-- Wishlist count --}}
<a href="/wishlist">
    Wishlist ({{ cart_count('wishlist') }})
</a>

{{-- Format any price --}}
<p class="price">{{ format_price($product->price) }}</p>
```

## Usage in Controllers

```php
class CartController extends Controller
{
    public function index()
    {
        return view('cart.index', [
            'items' => cart()->content(),
            'count' => cart_count(),
            'subtotal' => cart_subtotal(formatted: true),
            'total' => cart_total(formatted: true),
        ]);
    }
}
```

## Usage in API Responses

```php
class CartResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'items' => CartItemResource::collection(cart()->content()),
            'count' => cart_count(),
            'subtotal' => [
                'cents' => cart_subtotal(),
                'formatted' => cart_subtotal(formatted: true),
            ],
            'total' => [
                'cents' => cart_total(),
                'formatted' => cart_total(formatted: true),
            ],
        ];
    }
}
```
