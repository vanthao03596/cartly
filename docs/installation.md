# Installation

## Requirements

- PHP 8.1 or higher
- Laravel 10.0, 11.0 or 12.0

## Install via Composer

```bash
composer require vanthao03596/cartly
```

The package uses Laravel's package auto-discovery, so the service provider and facade are registered automatically.

## Publish Configuration

To customize the cart configuration, publish the config file:

```bash
php artisan vendor:publish --provider="Cart\CartServiceProvider" --tag="config"
```

This will create `config/cart.php` in your application.

## Database Driver Setup

If you want to persist carts in the database (recommended for authenticated users), publish and run the migrations:

```bash
php artisan vendor:publish --provider="Cart\CartServiceProvider" --tag="migrations"
php artisan migrate
```

This creates a `carts` table with the following structure:

```sql
CREATE TABLE carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instance VARCHAR(255) NOT NULL,
    identifier VARCHAR(255) NULL,
    content LONGTEXT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY carts_instance_identifier_unique (instance, identifier),
    INDEX carts_instance_index (instance),
    INDEX carts_identifier_index (identifier)
);
```

## Storage Driver Configuration

### Session Driver (Default)

Best for guest users. Cart data is stored in the Laravel session.

```php
// config/cart.php
'driver' => 'session',

'drivers' => [
    'session' => [
        'key' => 'cart',
    ],
],
```

### Database Driver

Best for authenticated users. Cart data persists across sessions.

```php
// config/cart.php
'driver' => 'database',

'drivers' => [
    'database' => [
        'table' => 'carts',
        'connection' => null, // Uses default connection
    ],
],
```

### Cache Driver

Best for high-traffic scenarios. Uses Redis, Memcached, or other cache stores.

```php
// config/cart.php
'driver' => 'cache',

'drivers' => [
    'cache' => [
        'store' => 'redis', // Or null for default store
        'prefix' => 'cart',
        'ttl' => 60 * 24 * 7, // 7 days in minutes
    ],
],
```

### Environment Variables

You can set the driver via environment variable:

```env
CART_DRIVER=database
```

## Verify Installation

After installation, verify everything is working:

```php
use Cart\Cart;

// Add a test item
$item = Cart::add(1, quantity: 1);

// Check it was added
echo Cart::count(); // Output: 1

// Clean up
Cart::clear();
```

## Next Steps

- [Configuration](configuration.md) - Learn about all configuration options
- [Basic Usage](usage/basic-usage.md) - Start using the cart
