# Custom Conditions

Create custom conditions for specialized cart modifiers.

## Condition Interface

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

## Extending BaseCondition

The easiest way to create a custom condition is to extend `BaseCondition`:

```php
<?php

namespace App\Cart\Conditions;

use Cart\Conditions\BaseCondition;

class CustomCondition extends BaseCondition
{
    public function __construct(
        string $name,
        // Your custom parameters
    ) {
        parent::__construct($name, 'custom_type', 'subtotal', 100);
    }

    public function calculate(int $valueCents): int
    {
        // Return the new value after applying this condition
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    public function getCalculatedValue(int $baseValueCents): int
    {
        // Return the modifier amount (positive or negative)
        return 0;
    }
}
```

## Example: Handling Fee Condition

```php
<?php

namespace App\Cart\Conditions;

use Cart\Conditions\BaseCondition;

class HandlingFeeCondition extends BaseCondition
{
    private int $feeAmount;
    private int $freeThreshold;

    public function __construct(
        string $name,
        int $feeAmountCents,
        int $freeThresholdCents = 0
    ) {
        parent::__construct($name, 'fee', 'subtotal', 80);

        $this->feeAmount = $feeAmountCents;
        $this->freeThreshold = $freeThresholdCents;
    }

    public function calculate(int $valueCents): int
    {
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    public function getCalculatedValue(int $baseValueCents): int
    {
        // Free handling for orders over threshold
        if ($this->freeThreshold > 0 && $baseValueCents >= $this->freeThreshold) {
            return 0;
        }

        return $this->feeAmount;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'feeAmount' => $this->feeAmount,
            'freeThreshold' => $this->freeThreshold,
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['name'],
            $data['feeAmount'],
            $data['freeThreshold'] ?? 0
        );
    }
}
```

**Usage:**

```php
use App\Cart\Conditions\HandlingFeeCondition;

// $2.99 handling fee, free on orders over $50
Cart::condition(new HandlingFeeCondition(
    'Handling Fee',
    feeAmountCents: 299,
    freeThresholdCents: 5000
));
```

## Example: Buy X Get Y Discount

```php
<?php

namespace App\Cart\Conditions;

use Cart\Conditions\BaseCondition;
use Cart\Cart;

class BuyXGetYCondition extends BaseCondition
{
    private int $buyQuantity;
    private int $freeQuantity;
    private int $productId;
    private int $productPrice;

    public function __construct(
        string $name,
        int $productId,
        int $productPrice,
        int $buyQuantity = 2,
        int $freeQuantity = 1
    ) {
        parent::__construct($name, 'discount', 'subtotal', 40);

        $this->productId = $productId;
        $this->productPrice = $productPrice;
        $this->buyQuantity = $buyQuantity;
        $this->freeQuantity = $freeQuantity;
    }

    public function calculate(int $valueCents): int
    {
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    public function getCalculatedValue(int $baseValueCents): int
    {
        // Find the product in cart
        $item = Cart::find($this->productId);

        if (!$item) {
            return 0;
        }

        // Calculate free items
        $sets = intdiv($item->quantity, $this->buyQuantity + $this->freeQuantity);
        $freeItems = $sets * $this->freeQuantity;

        // Return negative discount value
        return -($freeItems * $this->productPrice);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'productId' => $this->productId,
            'productPrice' => $this->productPrice,
            'buyQuantity' => $this->buyQuantity,
            'freeQuantity' => $this->freeQuantity,
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['name'],
            $data['productId'],
            $data['productPrice'],
            $data['buyQuantity'] ?? 2,
            $data['freeQuantity'] ?? 1
        );
    }
}
```

**Usage:**

```php
use App\Cart\Conditions\BuyXGetYCondition;

// Buy 2 get 1 free on product #123
Cart::condition(new BuyXGetYCondition(
    'Buy 2 Get 1 Free',
    productId: 123,
    productPrice: 999, // $9.99
    buyQuantity: 2,
    freeQuantity: 1
));
```

## Example: Tiered Shipping

```php
<?php

namespace App\Cart\Conditions;

use Cart\Conditions\BaseCondition;

class TieredShippingCondition extends BaseCondition
{
    /** @var array<int, int> threshold => rate */
    private array $tiers;

    public function __construct(string $name, array $tiers)
    {
        parent::__construct($name, 'shipping', 'subtotal', 75);

        // Sort tiers by threshold descending
        krsort($tiers);
        $this->tiers = $tiers;
    }

    public function calculate(int $valueCents): int
    {
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    public function getCalculatedValue(int $baseValueCents): int
    {
        foreach ($this->tiers as $threshold => $rate) {
            if ($baseValueCents >= $threshold) {
                return $rate;
            }
        }

        // Return highest rate if below all thresholds
        return end($this->tiers);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'tiers' => $this->tiers,
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static($data['name'], $data['tiers']);
    }
}
```

**Usage:**

```php
use App\Cart\Conditions\TieredShippingCondition;

Cart::condition(new TieredShippingCondition('Shipping', [
    10000 => 0,      // Free shipping over $100
    5000 => 499,     // $4.99 for orders $50-$99.99
    2500 => 799,     // $7.99 for orders $25-$49.99
    0 => 999,        // $9.99 for orders under $25
]));
```

## Example: Member Discount

```php
<?php

namespace App\Cart\Conditions;

use Cart\Conditions\BaseCondition;
use Illuminate\Support\Facades\Auth;

class MemberDiscountCondition extends BaseCondition
{
    private array $tierDiscounts;

    public function __construct(string $name, array $tierDiscounts)
    {
        parent::__construct($name, 'discount', 'subtotal', 45);

        $this->tierDiscounts = $tierDiscounts;
    }

    public function calculate(int $valueCents): int
    {
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    public function getCalculatedValue(int $baseValueCents): int
    {
        $user = Auth::user();

        if (!$user || !isset($this->tierDiscounts[$user->membership_tier])) {
            return 0;
        }

        $discountPercent = $this->tierDiscounts[$user->membership_tier];

        return -(int) ($baseValueCents * $discountPercent / 100);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'tierDiscounts' => $this->tierDiscounts,
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static($data['name'], $data['tierDiscounts']);
    }
}
```

**Usage:**

```php
use App\Cart\Conditions\MemberDiscountCondition;

Cart::condition(new MemberDiscountCondition('Member Discount', [
    'bronze' => 5,    // 5% off
    'silver' => 10,   // 10% off
    'gold' => 15,     // 15% off
    'platinum' => 20, // 20% off
]));
```

## Item-Level Conditions

For item-specific discounts, use `target: 'item'`:

```php
<?php

namespace App\Cart\Conditions;

use Cart\Conditions\BaseCondition;

class ItemBundleDiscount extends BaseCondition
{
    private int $discountPercent;
    private int $minQuantity;

    public function __construct(
        string $name,
        int $discountPercent,
        int $minQuantity = 3
    ) {
        parent::__construct($name, 'discount', 'item', 50);

        $this->discountPercent = $discountPercent;
        $this->minQuantity = $minQuantity;
    }

    public function calculate(int $valueCents): int
    {
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    public function getCalculatedValue(int $baseValueCents): int
    {
        // Applied per-item, so just return the discount amount
        return -(int) ($baseValueCents * $this->discountPercent / 100);
    }

    public function shouldApply(int $quantity): bool
    {
        return $quantity >= $this->minQuantity;
    }

    // ... toArray/fromArray
}
```

**Usage:**

```php
use App\Cart\Conditions\ItemBundleDiscount;

$item = Cart::get($rowId);
$item->condition(new ItemBundleDiscount('Bundle Discount', 10, 3));
```

## Condition Order

Control when conditions are applied:

| Order Range | Typical Use |
|-------------|-------------|
| 0-49 | Early discounts, item-level promotions |
| 50-74 | Standard discounts, coupons |
| 75-99 | Shipping, handling fees |
| 100-149 | Tax |
| 150+ | Late fees, surcharges |

## Registering in Config

```php
// config/cart.php
'instances' => [
    'default' => [
        'conditions' => [
            [
                'class' => App\Cart\Conditions\MemberDiscountCondition::class,
                'params' => [
                    'name' => 'Member Discount',
                    'tierDiscounts' => ['gold' => 15, 'silver' => 10],
                ],
            ],
            [
                'class' => App\Cart\Conditions\TieredShippingCondition::class,
                'params' => [
                    'name' => 'Shipping',
                    'tiers' => [10000 => 0, 5000 => 499, 0 => 999],
                ],
            ],
        ],
    ],
],
```

## Testing Custom Conditions

```php
use App\Cart\Conditions\TieredShippingCondition;

class TieredShippingConditionTest extends TestCase
{
    public function test_free_shipping_over_threshold()
    {
        $condition = new TieredShippingCondition('Shipping', [
            10000 => 0,
            5000 => 499,
            0 => 999,
        ]);

        // $150 order = free shipping
        $result = $condition->getCalculatedValue(15000);
        $this->assertEquals(0, $result);
    }

    public function test_mid_tier_shipping()
    {
        $condition = new TieredShippingCondition('Shipping', [
            10000 => 0,
            5000 => 499,
            0 => 999,
        ]);

        // $75 order = $4.99 shipping
        $result = $condition->getCalculatedValue(7500);
        $this->assertEquals(499, $result);
    }

    public function test_serialization()
    {
        $condition = new TieredShippingCondition('Shipping', [
            10000 => 0,
            5000 => 499,
        ]);

        $array = $condition->toArray();
        $restored = TieredShippingCondition::fromArray($array);

        $this->assertEquals($condition->getName(), $restored->getName());
        $this->assertEquals(
            $condition->getCalculatedValue(7500),
            $restored->getCalculatedValue(7500)
        );
    }
}
```
