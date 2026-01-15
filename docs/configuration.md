# Configuration

After publishing the configuration file, you can customize the cart behavior in `config/cart.php`.

## Storage Driver

```php
'driver' => env('CART_DRIVER', 'session'),
```

Available drivers:
- `session` - Stores cart in Laravel session (default)
- `database` - Stores cart in database table
- `cache` - Stores cart in cache (Redis, Memcached, etc.)
- `array` - In-memory storage (for testing)

## Default Instance

```php
'default_instance' => 'default',
```

The default cart instance name when none is specified.

## Driver-Specific Settings

### Session Driver

```php
'drivers' => [
    'session' => [
        'key' => 'cart', // Session key prefix
    ],
],
```

### Database Driver

```php
'drivers' => [
    'database' => [
        'table' => 'carts',        // Table name
        'connection' => null,       // Database connection (null = default)
    ],
],
```

### Cache Driver

```php
'drivers' => [
    'cache' => [
        'store' => null,            // Cache store (null = default)
        'prefix' => 'cart',         // Cache key prefix
        'ttl' => 60 * 24 * 7,       // Time-to-live in minutes (7 days)
    ],
],
```

## Price Resolver

```php
'price_resolver' => null,
```

Set to `null` to use the default `BuyablePriceResolver`, or provide a custom resolver class:

```php
'price_resolver' => App\Cart\CustomPriceResolver::class,
```

## Instance Configuration

Configure different cart instances with their own settings:

```php
'instances' => [
    'default' => [
        'conditions' => [],          // Pre-configured conditions
        'max_items' => null,         // Maximum items (null = unlimited)
    ],

    'wishlist' => [
        'conditions' => [],
        'max_items' => 50,           // Limit wishlist to 50 items
    ],

    'compare' => [
        'max_items' => 4,            // Limit compare to 4 items
        'allow_duplicates' => false, // Prevent duplicate items
    ],
],
```

### Instance-Level Conditions

Pre-configure conditions that apply automatically:

```php
'instances' => [
    'default' => [
        'conditions' => [
            [
                'class' => \Cart\Conditions\TaxCondition::class,
                'params' => [
                    'name' => 'VAT',
                    'rate' => 10,
                ],
            ],
        ],
    ],
],
```

## Tax Configuration

```php
'tax' => [
    'enabled' => true,               // Enable/disable tax
    'rate' => 0,                     // Default tax rate (0-100)
    'included_in_price' => false,    // Tax handling mode
],
```

### Tax Modes

**Excluded from price (US style)** - `included_in_price => false`
- Product price: $100.00
- Tax (10%): $10.00
- Total: $110.00

**Included in price (EU style)** - `included_in_price => true`
- Product price: $110.00 (includes tax)
- Tax (10%): $10.00 (extracted)
- Net price: $100.00
- Total: $110.00

## Price Formatting

```php
'format' => [
    'decimals' => 2,                 // Decimal places
    'decimal_separator' => '.',      // Decimal separator
    'thousand_separator' => ',',     // Thousand separator
    'currency_symbol' => '$',        // Currency symbol
    'currency_position' => 'before', // 'before' or 'after'
],
```

Example outputs:
- `before`: $1,234.56
- `after`: 1,234.56$

## User Association

```php
'associate' => [
    'auto_associate' => true,        // Auto-associate logged-in user
    'merge_on_login' => true,        // Merge guest cart on login
    'merge_strategy' => 'combine',   // Merge strategy
],
```

### Merge Strategies

| Strategy | Description |
|----------|-------------|
| `keep_guest` | Keep only guest cart, discard user cart |
| `keep_user` | Keep only user cart, discard guest cart |
| `combine` | Merge both carts, combining quantities for same items |

## Events

```php
'events' => [
    'enabled' => true,               // Enable/disable event dispatching
],
```

When disabled, no cart events will be dispatched. This can improve performance in high-throughput scenarios.

## Full Configuration Example

```php
<?php

return [
    'driver' => env('CART_DRIVER', 'session'),
    'default_instance' => 'default',

    'drivers' => [
        'session' => [
            'key' => 'cart',
        ],
        'database' => [
            'table' => 'carts',
            'connection' => null,
        ],
        'cache' => [
            'store' => 'redis',
            'prefix' => 'cart',
            'ttl' => 60 * 24 * 7,
        ],
    ],

    'price_resolver' => null,

    'instances' => [
        'default' => [
            'conditions' => [
                [
                    'class' => \Cart\Conditions\TaxCondition::class,
                    'params' => ['name' => 'VAT', 'rate' => 20],
                ],
            ],
            'max_items' => null,
        ],
        'wishlist' => [
            'conditions' => [],
            'max_items' => 50,
        ],
        'compare' => [
            'max_items' => 4,
            'allow_duplicates' => false,
        ],
    ],

    'tax' => [
        'enabled' => true,
        'rate' => 20,
        'included_in_price' => true,
    ],

    'format' => [
        'decimals' => 2,
        'decimal_separator' => '.',
        'thousand_separator' => ',',
        'currency_symbol' => '$',
        'currency_position' => 'before',
    ],

    'associate' => [
        'auto_associate' => true,
        'merge_on_login' => true,
        'merge_strategy' => 'combine',
    ],

    'events' => [
        'enabled' => true,
    ],
];
```

## Next Steps

- [Basic Usage](usage/basic-usage.md) - Learn how to use the cart
- [Conditions](usage/conditions.md) - Apply taxes and discounts
