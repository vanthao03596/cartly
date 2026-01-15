# Upgrade Guide

This guide covers upgrading between major versions of Cartly.

## Upgrading to 1.0 from 0.x

### Breaking Changes

#### 1. Price Resolution Required

Items now require a price resolver. The default `BuyablePriceResolver` expects models to implement `Priceable`:

```php
// Before (0.x) - price stored
Cart::add(1, 2, ['price' => 1999]);

// After (1.0) - price resolved
// Option 1: Implement Priceable on your model
class Product extends Model implements Buyable, Priceable
{
    public function getBuyablePrice(?CartContext $context = null): int
    {
        return $this->price;
    }
}

// Option 2: Use custom resolver
Cart::setPriceResolver(new CustomResolver());
```

#### 2. Prices in Cents

All prices are now integers in cents:

```php
// Before (0.x)
$total = Cart::total();  // 99.99 (float)

// After (1.0)
$total = Cart::total();  // 9999 (int, cents)

// For display
$formatted = format_price($total);  // "$99.99"
```

#### 3. Condition API Changes

Conditions now use named parameters and require `fromArray()`:

```php
// Before (0.x)
$tax = new TaxCondition('VAT', 20, true);

// After (1.0)
$tax = new TaxCondition(
    name: 'VAT',
    rate: 20,
    includedInPrice: true
);
```

#### 4. Storage Driver Interface

`StorageDriver` interface changed:

```php
// Before (0.x)
interface StorageDriver
{
    public function get(string $key): ?array;
    public function put(string $key, array $data): void;
}

// After (1.0)
interface StorageDriver
{
    public function get(string $instance, ?string $identifier = null): ?CartContent;
    public function put(string $instance, CartContent $content, ?string $identifier = null): void;
    public function forget(string $instance, ?string $identifier = null): void;
    public function flush(?string $identifier = null): void;
}
```

### Migration Steps

#### Step 1: Update Composer

```bash
composer require vanthao03596/cartly:^1.0
```

#### Step 2: Implement Priceable

Add `Priceable` interface to your buyable models:

```php
use Cart\Contracts\Priceable;
use Cart\CartContext;

class Product extends Model implements Buyable, Priceable
{
    public function getBuyablePrice(?CartContext $context = null): int
    {
        // Return price in cents
        return $this->price;
    }

    public function getBuyableOriginalPrice(): int
    {
        return $this->original_price ?? $this->price;
    }
}
```

Or use the trait:

```php
use Cart\Traits\CanBeBought;

class Product extends Model implements Buyable, Priceable
{
    use CanBeBought;
}
```

#### Step 3: Update Price References

Find and update all price references:

```php
// Before
$total = Cart::total();
echo "$" . number_format($total, 2);

// After
$total = Cart::total();
echo format_price($total);
// Or
echo "$" . number_format($total / 100, 2);
```

#### Step 4: Update Conditions

If you have custom conditions, update to new interface:

```php
// Before
class MyCondition
{
    public function apply($value) { }
}

// After
class MyCondition extends BaseCondition
{
    public function calculate(int $valueCents): int
    {
        return $valueCents + $this->getCalculatedValue($valueCents);
    }

    public function getCalculatedValue(int $baseValueCents): int
    {
        return /* your calculation */;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            // your properties
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static(/* from $data */);
    }
}
```

#### Step 5: Update Storage Drivers

If you have custom storage drivers, implement the new interface:

```php
class MyDriver implements StorageDriver
{
    public function get(string $instance, ?string $identifier = null): ?CartContent
    {
        $data = $this->fetchData($instance, $identifier);
        return $data ? CartContent::fromArray($data) : null;
    }

    public function put(string $instance, CartContent $content, ?string $identifier = null): void
    {
        $this->storeData($instance, $identifier, $content->toArray());
    }

    public function forget(string $instance, ?string $identifier = null): void
    {
        $this->deleteData($instance, $identifier);
    }

    public function flush(?string $identifier = null): void
    {
        $this->deleteAllInstances($identifier);
    }
}
```

#### Step 6: Publish New Config

```bash
php artisan vendor:publish --provider="Cart\CartServiceProvider" --tag="config" --force
```

Review and merge with your existing config.

#### Step 7: Run Migrations (if using database driver)

```bash
php artisan vendor:publish --provider="Cart\CartServiceProvider" --tag="migrations" --force
php artisan migrate
```

### Deprecated Features

The following are deprecated and will be removed in 2.0:

| Deprecated | Replacement |
|------------|-------------|
| `Cart::setPrice()` | Use `PriceResolver` |
| `CartItem::$price` property | Use `CartItem::unitPrice()` |
| `Cart::getContent()` | Use `Cart::content()` |

### New Features in 1.0

- Multiple cart instances with configuration
- Lazy price resolution with batch optimization
- Cart merging on login
- Comprehensive testing utilities
- Event-driven architecture
- Type-safe with strict types

## Version Compatibility

| Cartly Version | Laravel Version | PHP Version |
|----------------|-----------------|-------------|
| 1.x | 10.x, 11.x, 12.x | 8.1+ |
| 0.x | 9.x, 10.x | 8.0+ |

## Getting Help

If you encounter issues during upgrade:

1. Check the [GitHub Issues](https://github.com/vanthao03596/cartly/issues)
2. Review the [full documentation](index.md)
3. Open a new issue with your upgrade scenario
