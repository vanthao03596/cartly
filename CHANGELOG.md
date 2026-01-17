# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-01-17

### Added

- **Feature**: Condition validation system - conditions can validate themselves against cart state
  - `Condition::isValid(?CartInstance $cart)` method to check if condition is still valid
  - `Condition::getValidationError()` method to get validation error message (for logging/debugging)
  - `DiscountCondition` validates `minOrderAmount` against cart subtotal
  - Invalid conditions are automatically removed when cart loads from storage
  - New `CartConditionInvalidated` event dispatched when a condition is auto-removed
  - New config option `cart.conditions.auto_remove_invalid` (default: `true`)

### Changed

- `Condition` interface now requires `isValid()` and `getValidationError()` methods
- Custom conditions extending `BaseCondition` will work without changes
- Custom conditions implementing `Condition` directly must add these two methods

## [1.1.2] - 2026-01-17

### Fixed

- **PHPStan**: Resolve PHPStan errors and simplify publish tags

## [1.1.1] - 2026-01-16

### Changed

- **Refactor**: Simplify `StorageDriver` binding to use config-based resolution with container
- **Refactor**: Remove unnecessary null check in `CartManager` auto-associate logic
- **Refactor**: Simplify `toJson()` method in `CartContent`
- **Refactor**: Use `in_array()` for condition filtering in `CartInstance`

### Fixed

- **Deps**: Update PHPUnit version constraint to `^10.1|^11.0` for `<source>` element support
- **Deps**: Update Laravel Pint version constraint to `^1.18` to support PHP 8.1

### Added

- **Tooling**: Add Laravel Pint for code formatting

## [1.1.0] - 2026-01-16

### Changed

- **Breaking**: Driver configuration now requires `class` key in `config/cart.php`
- **Architecture**: Driver resolution now uses Laravel container (`app()`) instead of hardcoded match statement
- **Refactor**: `handleLogin()` now uses `resolveDriver()` instead of direct instantiation

### Added

- **Feature**: Custom storage drivers can now be registered via config without extending the package

### Upgrade Notes

If you have published the cart config, add the `class` key to each driver:

```php
'drivers' => [
    'session' => [
        'class' => \Cart\Drivers\SessionDriver::class, // Add this line
        'key' => 'cart',
    ],
    // ... other drivers
],
```

## [1.0.1] - 2026-01-15

### Changed

- **Performance**: Add batch model loading to eliminate N+1 queries when accessing buyable models
- **Refactor**: Use `findMany()` instead of `whereIn('id')` for flexible primary key support in `BuyablePriceResolver`

## [1.0.0] - 2026-01-15

### Added

- Initial stable release
- **Cart Management**
  - Add, update, remove items from cart
  - Multiple cart instances (cart, wishlist, compare)
  - Move items between instances
  - Clear and destroy cart operations

- **Dynamic Pricing**
  - Price resolution at runtime (no stored prices)
  - Batch price resolution for performance
  - Lazy price loading with callbacks
  - Custom price resolver support
  - Prices stored in cents to avoid floating-point issues

- **Conditions System**
  - Built-in conditions: Tax, Discount, Shipping
  - Percentage and fixed amount support
  - Condition ordering with priorities
  - Item-level and cart-level conditions
  - Custom condition support

- **Storage Drivers**
  - Session driver (default)
  - Database driver with migrations
  - Array driver for testing
  - Custom driver support

- **User Association**
  - Guest cart with session identifier
  - User cart association
  - Automatic cart merging on login
  - Multiple merge strategies (keep_guest, keep_user, merge, combine)

- **Events**
  - 12 lifecycle events
  - Before/after events for all operations
  - Cancelable events (throw exception)
  - Configurable event dispatching

- **Testing Utilities**
  - Fake mode for testing
  - CartAssertions trait
  - CartFactory for test data
  - Preset configurations

- **Developer Experience**
  - Facade support (`Cart::`)
  - Helper functions (`cart()`, `cents_to_dollars()`, etc.)
  - Buyable contract for models
  - Comprehensive exception handling

### Security

- No stored prices prevents price manipulation
- Row ID generated from buyable + options hash

## [0.x.x] - Development

### Added

- Initial development and architecture design
- Core cart functionality
- Storage driver abstraction
- Condition system foundation

---

## Upgrade Guide

See [docs/upgrade-guide.md](docs/upgrade-guide.md) for detailed upgrade instructions.

## Links

- [Documentation](docs/index.md)
- [GitHub Repository](https://github.com/vanthao03596/cartly)
- [Packagist](https://packagist.org/packages/vanthao03596/cartly)
