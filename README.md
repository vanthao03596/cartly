# Cartly

A flexible, customizable shopping cart library for Laravel with dynamic pricing.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vanthao03596/cartly.svg?style=flat-square)](https://packagist.org/packages/vanthao03596/cartly)
[![Total Downloads](https://img.shields.io/packagist/dt/vanthao03596/cartly.svg?style=flat-square)](https://packagist.org/packages/vanthao03596/cartly)
[![License](https://img.shields.io/packagist/l/vanthao03596/cartly.svg?style=flat-square)](https://packagist.org/packages/vanthao03596/cartly)

## Features

- **No Stored Prices** - All prices resolved at runtime via pluggable resolvers
- **Multiple Storage Drivers** - Session, Database, Cache, or custom implementations
- **Multiple Cart Instances** - Support cart, wishlist, compare, and custom instances
- **Flexible Conditions** - Tax, discounts, shipping, and custom fee modifiers
- **Event-Driven Architecture** - Hooks into every cart operation
- **Guest-to-User Cart Merging** - Seamlessly merge carts on user login
- **Comprehensive Testing Utilities** - Fake mode, assertions, and factories

## Requirements

- PHP 8.1+
- Laravel 10.0, 11.0 or 12.0

## Installation

```bash
composer require vanthao03596/cartly
```

The package uses Laravel's auto-discovery, so the service provider and facade are registered automatically.

### Publish Configuration

```bash
php artisan vendor:publish --provider="Cart\CartServiceProvider" --tag="config"
```

### Database Driver Setup (Optional)

If you want to use the database driver for persistent storage:

```bash
php artisan vendor:publish --provider="Cart\CartServiceProvider" --tag="migrations"
php artisan migrate
```

## Quick Start

### Basic Usage

```php
use Cart\Cart;

// Add an item to cart
$item = Cart::add($product, quantity: 2);

// Add with options
$item = Cart::add($product, quantity: 1, options: ['size' => 'L', 'color' => 'blue']);

// Update quantity
Cart::update($item->rowId, 5);

// Remove item
Cart::remove($item->rowId);

// Get totals (in cents)
$subtotal = Cart::subtotal();  // 4999
$total = Cart::total();        // 5499 (with tax)

// Clear cart
Cart::clear();
```

### Using Buyable Models

Implement the `Buyable` and `Priceable` interfaces on your model:

```php
use Cart\Contracts\Buyable;
use Cart\Contracts\Priceable;
use Cart\Traits\CanBeBought;

class Product extends Model implements Buyable, Priceable
{
    use CanBeBought;

    // The trait auto-detects price from common attributes:
    // price, sale_price, current_price, original_price, regular_price
}
```

Or implement the methods manually:

```php
class Product extends Model implements Buyable, Priceable
{
    public function getBuyableIdentifier(): int|string
    {
        return $this->id;
    }

    public function getBuyableDescription(): string
    {
        return $this->name;
    }

    public function getBuyableType(): string
    {
        return static::class;
    }

    public function getBuyablePrice(?CartContext $context = null): int
    {
        return $this->price; // In cents
    }

    public function getBuyableOriginalPrice(): int
    {
        return $this->original_price ?? $this->price;
    }
}
```

### Multiple Instances

```php
// Work with wishlist
Cart::instance('wishlist')->add($product);
// Or use magic method
Cart::wishlist()->add($product);

// Move item between instances
Cart::moveToWishlist($rowId);
Cart::moveToCart($rowId);
```

### Conditions

```php
use Cart\Conditions\TaxCondition;
use Cart\Conditions\DiscountCondition;

// Add 10% tax
Cart::condition(new TaxCondition('VAT', rate: 10));

// Add 15% discount
Cart::condition(new DiscountCondition('Summer Sale', value: 15, mode: 'percentage'));

// Add fixed discount
Cart::condition(new DiscountCondition('Coupon', value: 1000, mode: 'fixed')); // $10.00 off

// Get breakdown
$breakdown = Cart::getCalculationBreakdown();
```

## Documentation

For complete documentation, see the [docs](docs/index.md) folder:

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Basic Usage](docs/usage/basic-usage.md)
- [Multiple Instances](docs/usage/multiple-instances.md)
- [Conditions](docs/usage/conditions.md)
- [API Reference](docs/api-reference/cart-facade.md)
- [Testing](docs/testing.md)
- [Architecture](docs/architecture/overview.md)

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
