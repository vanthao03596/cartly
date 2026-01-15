# Condition Classes

Cartly provides several built-in condition classes for common cart modifiers.

## Condition Interface

All conditions implement the `Condition` contract:

```php
namespace Cart\Contracts;

interface Condition
{
    public function getName(): string;
    public function getType(): string;
    public function getTarget(): string;
    public function getOrder(): int;
    public function calculate(int $valueCents): int;
    public function getCalculatedValue(int $baseValueCents): int;
    public function toArray(): array;
    public static function fromArray(array $data): static;
}
```

### Method Descriptions

| Method | Description |
|--------|-------------|
| `getName()` | Unique identifier for this condition |
| `getType()` | Category: 'tax', 'discount', 'shipping', 'fee' |
| `getTarget()` | What to apply to: 'subtotal', 'total', 'item' |
| `getOrder()` | Priority (lower = applied first) |
| `calculate()` | Returns new value after applying condition |
| `getCalculatedValue()` | Returns the modifier amount (can be negative) |

## TaxCondition

Apply percentage-based tax.

```php
use Cart\Conditions\TaxCondition;
```

### Constructor

```php
public function __construct(
    string $name,
    float $rate,
    bool $includedInPrice = false,
    string $target = 'subtotal'
)
```

**Parameters:**
- `$name` - Condition name (e.g., 'VAT', 'Sales Tax')
- `$rate` - Tax rate as percentage (0-100)
- `$includedInPrice` - If true, tax is extracted from price (EU style)
- `$target` - Apply to 'subtotal' or 'total'

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `rate` | float | - | Tax rate (0-100) |
| `includedInPrice` | bool | false | Tax extraction mode |
| `target` | string | 'subtotal' | Application target |
| `order` | int | 100 | Applied after discounts |

### Example

```php
// US style: 8% tax added on top
$tax = new TaxCondition('Sales Tax', rate: 8);

// EU style: 20% VAT included in price
$vat = new TaxCondition('VAT', rate: 20, includedInPrice: true);

Cart::condition($tax);
```

### Methods

```php
$tax->isIncludedInPrice(): bool
$tax->getSubtotalExcludingTax(int $totalCents): int
```

## DiscountCondition

Apply percentage or fixed amount discounts.

```php
use Cart\Conditions\DiscountCondition;
```

### Constructor

```php
public function __construct(
    string $name,
    float|int $value,
    string $mode = 'percentage',
    string $target = 'subtotal',
    ?int $maxAmount = null,
    ?int $minOrderAmount = null,
    int $order = 50
)
```

**Parameters:**
- `$name` - Condition name (e.g., 'Summer Sale', 'Coupon')
- `$value` - Discount value (percentage 0-100, or cents for fixed)
- `$mode` - 'percentage' or 'fixed'
- `$target` - Apply to 'subtotal', 'total', or 'item'
- `$maxAmount` - Maximum discount in cents (percentage mode only)
- `$minOrderAmount` - Minimum order value to apply (cents)
- `$order` - Priority (default: 50)

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `value` | float\|int | - | Discount value |
| `mode` | string | 'percentage' | 'percentage' or 'fixed' |
| `maxAmount` | int\|null | null | Max discount cap |
| `minOrderAmount` | int\|null | null | Min order required |
| `order` | int | 50 | Applied before tax |

### Examples

```php
// 15% off
$discount = new DiscountCondition('Sale', value: 15, mode: 'percentage');

// $10 off
$discount = new DiscountCondition('Coupon', value: 1000, mode: 'fixed');

// 20% off, max $50
$discount = new DiscountCondition(
    'Big Sale',
    value: 20,
    mode: 'percentage',
    maxAmount: 5000
);

// 10% off orders over $100
$discount = new DiscountCondition(
    'Spend & Save',
    value: 10,
    mode: 'percentage',
    minOrderAmount: 10000
);

Cart::condition($discount);
```

### Methods

```php
$discount->getValue(): float|int
$discount->getMode(): string
$discount->isPercentage(): bool
$discount->isFixed(): bool
$discount->getMaxAmount(): ?int
$discount->getMinOrderAmount(): ?int
```

## ShippingCondition

Add fixed shipping cost.

```php
use Cart\Conditions\ShippingCondition;
```

### Constructor

```php
public function __construct(
    string $name,
    int $amountCents,
    string $target = 'subtotal',
    int $order = 75
)
```

**Parameters:**
- `$name` - Condition name (e.g., 'Standard Shipping')
- `$amountCents` - Shipping cost in cents
- `$target` - Apply to 'subtotal' or 'total'
- `$order` - Priority (default: 75)

### Example

```php
// $5.99 shipping
$shipping = new ShippingCondition('Standard', amountCents: 599);

// Free shipping (useful for conditional logic)
$shipping = new ShippingCondition('Free Shipping', amountCents: 0);

Cart::condition($shipping);
```

## PercentageCondition

Base class for percentage-based conditions.

```php
use Cart\Conditions\PercentageCondition;
```

### Constructor

```php
public function __construct(
    string $name,
    float $percentage,
    string $type = 'fee',
    string $target = 'subtotal',
    int $order = 100
)
```

### Example

```php
// Custom 5% service fee
$fee = new PercentageCondition(
    'Service Fee',
    percentage: 5,
    type: 'fee'
);
```

## FixedCondition

Base class for fixed amount conditions.

```php
use Cart\Conditions\FixedCondition;
```

### Constructor

```php
public function __construct(
    string $name,
    int $amountCents,
    string $type = 'fee',
    string $target = 'subtotal',
    int $order = 100
)
```

### Example

```php
// $2 handling fee
$fee = new FixedCondition(
    'Handling Fee',
    amountCents: 200,
    type: 'fee'
);
```

## Condition Order

Conditions are applied in order (lower number first):

| Type | Default Order |
|------|---------------|
| Discount | 50 |
| Shipping | 75 |
| Tax | 100 |
| Fee | 150 |

### Customizing Order

```php
// Apply shipping after tax
$shipping = new ShippingCondition('Shipping', 599, order: 150);

// Apply discount after tax
$discount = new DiscountCondition(
    'Post-Tax Discount',
    value: 10,
    mode: 'percentage',
    order: 110
);
```

## Serialization

All conditions support serialization:

```php
// To array
$array = $condition->toArray();
// [
//     'class' => 'Cart\\Conditions\\TaxCondition',
//     'name' => 'VAT',
//     'rate' => 20,
//     'includedInPrice' => true,
//     ...
// ]

// From array
$condition = TaxCondition::fromArray($array);
```

## Condition Types

| Type | Typically | Value |
|------|-----------|-------|
| `tax` | Positive | Added to total |
| `discount` | Negative | Subtracted from total |
| `shipping` | Positive | Added to total |
| `fee` | Positive | Added to total |

Use `getCalculatedValue()` to get the modifier amount:

```php
$discount->getCalculatedValue(10000);  // -1500 (15% of 10000)
$tax->getCalculatedValue(10000);       // 2000 (20% of 10000)
$shipping->getCalculatedValue(10000);  // 599 (fixed amount)
```

## Creating Custom Conditions

See [Custom Conditions](../extending/custom-conditions.md) for creating your own condition types.
