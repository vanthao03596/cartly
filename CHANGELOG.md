# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
