# Conditions

Conditions are modifiers that adjust cart totals. Cartly includes built-in conditions for tax, discounts, and shipping, and you can create custom conditions.

## Built-in Conditions

| Condition | Type | Description |
|-----------|------|-------------|
| `TaxCondition` | tax | Apply tax (percentage) |
| `DiscountCondition` | discount | Apply discount (percentage or fixed) |
| `ShippingCondition` | shipping | Add shipping fee (fixed) |

## Tax Condition

### Basic Tax (US Style)

Tax added on top of subtotal:

```php
use Cart\Conditions\TaxCondition;

// Add 10% tax
Cart::condition(new TaxCondition(
    name: 'Sales Tax',
    rate: 10
));

// Subtotal: $100.00
// Tax: $10.00
// Total: $110.00
```

### Inclusive Tax (EU Style)

Tax extracted from price (price includes tax):

```php
Cart::condition(new TaxCondition(
    name: 'VAT',
    rate: 20,
    includedInPrice: true
));

// Price (with VAT): $120.00
// VAT (20%): $20.00
// Net price: $100.00
// Total: $120.00
```

### Tax Configuration

Set default tax in config:

```php
// config/cart.php
'tax' => [
    'enabled' => true,
    'rate' => 20,
    'included_in_price' => true,
],
```

## Discount Condition

### Percentage Discount

```php
use Cart\Conditions\DiscountCondition;

// 15% off
Cart::condition(new DiscountCondition(
    name: 'Summer Sale',
    value: 15,
    mode: 'percentage'
));

// Subtotal: $100.00
// Discount: -$15.00
// Total: $85.00
```

### Fixed Amount Discount

```php
// $10 off (in cents)
Cart::condition(new DiscountCondition(
    name: 'Coupon Code',
    value: 1000,          // 1000 cents = $10
    mode: 'fixed'
));

// Subtotal: $100.00
// Discount: -$10.00
// Total: $90.00
```

### Capped Percentage Discount

Limit maximum discount amount:

```php
// 20% off, max $50
Cart::condition(new DiscountCondition(
    name: 'Big Sale',
    value: 20,
    mode: 'percentage',
    maxAmount: 5000       // 5000 cents = $50 max
));

// Subtotal: $500.00
// 20% = $100, but capped at $50
// Discount: -$50.00
// Total: $450.00
```

### Minimum Order Discount

Require minimum subtotal:

```php
// 10% off orders over $50
Cart::condition(new DiscountCondition(
    name: 'Spend More Save More',
    value: 10,
    mode: 'percentage',
    minOrderAmount: 5000  // 5000 cents = $50 minimum
));

// If subtotal < $50: discount not applied
// If subtotal >= $50: 10% discount applied
```

## Shipping Condition

```php
use Cart\Conditions\ShippingCondition;

// Fixed shipping fee
Cart::condition(new ShippingCondition(
    name: 'Standard Shipping',
    amountCents: 599      // $5.99
));

// Subtotal: $50.00
// Shipping: $5.99
// Total: $55.99
```

## Managing Conditions

### Add Condition

```php
Cart::condition($condition);
```

### Remove Condition

```php
// By name
Cart::removeCondition('Summer Sale');
```

### Get Conditions

```php
// Get specific condition
$condition = Cart::getCondition('VAT');

// Get all conditions
$conditions = Cart::getConditions();

// Check if exists
if (Cart::hasCondition('VAT')) {
    // ...
}
```

### Clear All Conditions

```php
Cart::clearConditions();
```

## Condition Order

Conditions are applied in a specific order (lower number = applied first):

| Type | Default Order |
|------|---------------|
| discount | 50 |
| shipping | 75 |
| tax | 100 |
| fee | 150 |

### Custom Order

```php
// Discount applied after tax
Cart::condition(new DiscountCondition(
    name: 'Post-Tax Discount',
    value: 10,
    mode: 'percentage',
    target: 'subtotal',
    order: 150  // After tax (100)
));
```

## Calculation Breakdown

See how conditions affect the total:

```php
$breakdown = Cart::getCalculationBreakdown();

// Example output:
[
    'subtotal' => 10000,    // $100.00
    'steps' => [
        [
            'name' => 'Summer Sale',
            'type' => 'discount',
            'value' => -1000,
            'running_total' => 9000,
        ],
        [
            'name' => 'VAT',
            'type' => 'tax',
            'value' => 1800,
            'running_total' => 10800,
        ],
    ],
    'total' => 10800,       // $108.00
]
```

## Condition Totals

```php
// Get total of all tax conditions
$taxTotal = Cart::taxTotal();           // 1800

// Get total of all discount conditions (negative)
$discountTotal = Cart::discountTotal(); // -1000

// Get sum of all conditions
$conditionsTotal = Cart::conditionsTotal(); // 800
```

## Item-Level Conditions

Apply conditions to individual items:

```php
// Get item
$item = Cart::get($rowId);

// Add item-level discount
$item->condition(new DiscountCondition(
    name: 'Item Promo',
    value: 5,
    mode: 'percentage',
    target: 'item'
));

// Item subtotal reflects condition
$item->subtotal();  // unitPrice * quantity
$item->total();     // With item-level conditions applied
```

## Pre-configured Conditions

Set conditions in config for automatic application:

```php
// config/cart.php
'instances' => [
    'default' => [
        'conditions' => [
            [
                'class' => \Cart\Conditions\TaxCondition::class,
                'params' => [
                    'name' => 'VAT',
                    'rate' => 20,
                    'includedInPrice' => true,
                ],
            ],
            [
                'class' => \Cart\Conditions\ShippingCondition::class,
                'params' => [
                    'name' => 'Standard Shipping',
                    'amountCents' => 499,
                ],
            ],
        ],
    ],
],
```

## Common Patterns

### Apply Coupon Code

```php
public function applyCoupon(Request $request)
{
    $coupon = Coupon::where('code', $request->code)->first();

    if (!$coupon || $coupon->isExpired()) {
        return back()->with('error', 'Invalid coupon code');
    }

    // Remove existing coupon if any
    Cart::removeCondition('coupon');

    // Apply new coupon
    Cart::condition(new DiscountCondition(
        name: 'coupon',
        value: $coupon->discount_value,
        mode: $coupon->discount_type,
        minOrderAmount: $coupon->min_order,
    ));

    return back()->with('success', 'Coupon applied!');
}
```

### Dynamic Shipping

```php
public function updateShipping(Request $request)
{
    $rate = ShippingRate::find($request->shipping_rate_id);

    // Remove existing shipping
    Cart::removeCondition('shipping');

    // Apply selected shipping
    Cart::condition(new ShippingCondition(
        name: 'shipping',
        amountCents: $rate->price_cents
    ));

    return back();
}
```

### Free Shipping Threshold

```php
// In a service provider or middleware
$subtotal = Cart::subtotal();
$freeShippingThreshold = 5000; // $50

if ($subtotal < $freeShippingThreshold) {
    Cart::condition(new ShippingCondition(
        name: 'Standard Shipping',
        amountCents: 599
    ));
} else {
    Cart::removeCondition('Standard Shipping');
}
```

## Next Steps

- [User Association](user-association.md) - Handle logged-in users
- [Custom Conditions](../extending/custom-conditions.md) - Create your own conditions
