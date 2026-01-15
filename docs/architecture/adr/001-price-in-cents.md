# ADR-001: Store Prices in Cents

## Status

Accepted

## Context

When building an e-commerce system, we need to decide how to store and manipulate monetary values. There are several options:

1. **Float/Double** - e.g., `99.99`
2. **Decimal String** - e.g., `"99.99"`
3. **Integer Cents** - e.g., `9999`

Floating-point arithmetic in PHP (and most languages) has precision issues:

```php
$price = 0.1 + 0.2;
// Result: 0.30000000000000004
```

This can lead to:
- Incorrect totals
- Penny discrepancies in financial reports
- Failed equality comparisons
- Accumulating errors in large calculations

## Decision

**Store all monetary values as integers in cents (or the smallest currency unit).**

Examples:
- `$99.99` = `9999` cents
- `$10.00` = `1000` cents
- `$0.50` = `50` cents

## Implementation

### Storage

```php
// CartItem
$item->unitPrice();    // Returns: 9999 (int)
$item->subtotal();     // Returns: 19998 (int)

// CartInstance
Cart::subtotal();      // Returns: 29997 (int)
Cart::total();         // Returns: 32996 (int)
```

### Display

```php
// Helper function for display
format_price(9999);  // Returns: "$99.99"

// Or manually
$dollars = $cents / 100;  // 99.99
```

### User Input

```php
// Convert user input to cents
$cents = dollars_to_cents((float) $request->price);  // Rounds properly
```

### Database

```php
// Migration
$table->integer('price');  // Not decimal!
```

## Consequences

### Positive

1. **No Precision Errors** - Integer arithmetic is exact
2. **Industry Standard** - Used by Stripe, PayPal, and major e-commerce platforms
3. **Simpler Comparisons** - `$priceA === $priceB` works correctly
4. **Better Performance** - Integer operations are faster
5. **No Rounding Issues** - Calculations are deterministic

### Negative

1. **Conversion Required** - Must convert for display and user input
2. **Learning Curve** - Developers must remember values are in cents
3. **Currency Limitations** - Some currencies have 3 decimal places (handled by using smallest unit)

### Neutral

1. **Maximum Value** - PHP integers can handle up to ~9 quadrillion cents (~$90 trillion)
2. **No Currency Symbol** - Formatting handled separately

## References

- [Stripe: Handling Amounts](https://stripe.com/docs/currencies#zero-decimal)
- [Why You Should Never Use Float for Money](https://husobee.github.io/money/float/2016/09/23/never-use-floats-for-currency.html)
- [Martin Fowler: Money Pattern](https://martinfowler.com/eaaCatalog/money.html)
