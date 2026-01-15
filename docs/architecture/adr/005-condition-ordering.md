# ADR-005: Condition Ordering System

## Status

Accepted

## Context

Cart conditions (tax, discounts, shipping, fees) must be applied in a specific order to produce correct totals. The order matters significantly:

**Example: 10% discount + 8% tax on $100 subtotal**

Order 1: Discount first, then tax
```
$100 - 10% = $90
$90 + 8% = $97.20
```

Order 2: Tax first, then discount
```
$100 + 8% = $108
$108 - 10% = $97.20
```

Same result here, but consider percentage discount with max cap:

**Example: 20% discount (max $15) + 8% tax on $100**

Order 1: Discount first
```
$100 - 20% (max $15) = $85
$85 + 8% = $91.80
```

Order 2: Tax first
```
$100 + 8% = $108
$108 - 20% (max $15) = $93  // 20% of $108 = $21.60, capped at $15
```

Different results! The order matters.

## Decision

**Use numeric ordering where lower numbers are applied first.**

```php
interface Condition
{
    public function getOrder(): int;
}
```

### Default Order Values

| Condition Type | Default Order | Rationale |
|----------------|---------------|-----------|
| Discount | 50 | Apply discounts to base price |
| Shipping | 75 | Add shipping after discounts |
| Tax | 100 | Tax the discounted + shipping amount |
| Fee | 150 | Additional fees after tax |

### Implementation

```php
class CalculationPipeline
{
    public function process(int $valueCents): int
    {
        // Sort conditions by order (ascending)
        $sorted = $this->conditions->sortBy(fn($c) => $c->getOrder());

        $running = $valueCents;

        foreach ($sorted as $condition) {
            $running = $condition->calculate($running);

            $this->steps[] = [
                'name' => $condition->getName(),
                'type' => $condition->getType(),
                'value' => $condition->getCalculatedValue($valueCents),
                'running_total' => $running,
            ];
        }

        return $running;
    }
}
```

## Consequences

### Positive

1. **Predictable Results** - Same conditions always produce same total
2. **Tax Compliance** - Tax calculated on final amount (after discounts)
3. **Flexible Ordering** - Custom conditions can specify any order
4. **Transparent Breakdown** - `getCalculationBreakdown()` shows order
5. **Business Logic Encoded** - Order reflects real-world rules

### Negative

1. **Potential Conflicts** - Two conditions with same order (undefined order)
2. **Learning Curve** - Developers must understand ordering impact
3. **Configuration Required** - Custom conditions need order specified

### Mitigation for Same Order

When multiple conditions have the same order, they're applied in the order they were added:

```php
// Both have order 50
Cart::condition(new DiscountCondition('A', 10, 'percentage'));
Cart::condition(new DiscountCondition('B', 5, 'percentage'));

// A applied first, then B (insertion order)
```

For deterministic behavior, use unique orders:

```php
Cart::condition(new DiscountCondition('A', 10, 'percentage', order: 50));
Cart::condition(new DiscountCondition('B', 5, 'percentage', order: 51));
```

## Common Scenarios

### Standard E-commerce (US)

```
Subtotal: $100.00
Discount (10%): -$10.00    [order: 50]
Shipping: $5.99            [order: 75]
Tax (8%): $7.68            [order: 100]
Total: $103.67
```

### EU VAT (Tax Included)

```
Subtotal: $100.00 (includes VAT)
VAT Extracted (20%): -$16.67   [order: 100, included_in_price: true]
Net Amount: $83.33
Discount (10%): -$8.33         [order: 50] -- Note: Actually applied before extraction display
Total: $100.00 (VAT included in display)
```

### Complex Promotion

```
Subtotal: $200.00
Member Discount (5%): -$10.00   [order: 45]
Coupon (15%): -$28.50           [order: 50]
Free Shipping: $0.00            [order: 75]
Tax (10%): $16.15               [order: 100]
Handling Fee: $2.99             [order: 150]
Total: $180.64
```

## Custom Order Examples

### Post-Tax Discount (e.g., Cashback)

```php
Cart::condition(new DiscountCondition(
    'Cashback',
    5,
    'percentage',
    order: 110  // After tax (100)
));
```

### Early Shipping (Before Discounts)

```php
Cart::condition(new ShippingCondition(
    'Express',
    1999,
    order: 40  // Before discount (50)
));

// Discount now applies to subtotal + shipping
```

### Surcharge After Everything

```php
Cart::condition(new FixedCondition(
    'Credit Card Fee',
    100,  // $1.00
    'fee',
    order: 200  // Very last
));
```

## Alternatives Considered

### Named Phases

Use named phases instead of numbers:

```php
enum Phase { DISCOUNT, SHIPPING, TAX, FEE }
```

**Rejected because**: Not flexible enough, can't insert between phases.

### Dependency Declaration

Declare what each condition depends on:

```php
$condition->after('discount')->before('tax');
```

**Rejected because**: Complex resolution, potential circular dependencies.

### Fixed Pipeline

Hardcode the pipeline stages:

```php
$total = $this->applyDiscounts($subtotal);
$total = $this->applyShipping($total);
$total = $this->applyTax($total);
```

**Rejected because**: Not extensible, custom conditions can't participate.

## References

- WooCommerce uses similar numeric priority for hooks
- Tax calculation order follows accounting standards (tax on final sale price)
- Stripe and PayPal calculate tax after discounts
