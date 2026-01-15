# Cartly Documentation

Welcome to the Cartly documentation. This guide will help you get started with the shopping cart library and explore its features.

## Getting Started

- [Installation](installation.md) - Install and configure the package
- [Configuration](configuration.md) - All configuration options explained

## Usage Guides

- [Basic Usage](usage/basic-usage.md) - Add, update, remove items and get totals
- [Multiple Instances](usage/multiple-instances.md) - Cart, wishlist, compare lists
- [Conditions](usage/conditions.md) - Tax, discounts, shipping fees
- [User Association](usage/user-association.md) - Guest to user cart merging

## API Reference

- [Cart Facade](api-reference/cart-facade.md) - Static methods reference
- [CartManager](api-reference/cart-manager.md) - Manager class API
- [CartInstance](api-reference/cart-instance.md) - Instance operations API
- [CartItem](api-reference/cart-item.md) - Item class API
- [Conditions](api-reference/conditions.md) - Condition classes API
- [Helpers](api-reference/helpers.md) - Helper functions

## Extending

- [Custom Storage Driver](extending/custom-storage-driver.md) - Create your own storage driver
- [Custom Price Resolver](extending/custom-price-resolver.md) - Create custom pricing logic
- [Custom Conditions](extending/custom-conditions.md) - Create custom condition types

## Architecture

- [Overview](architecture/overview.md) - Architecture diagrams and flow
- Architecture Decision Records:
  - [ADR-001: Price in Cents](architecture/adr/001-price-in-cents.md)
  - [ADR-002: No Stored Prices](architecture/adr/002-no-stored-prices.md)
  - [ADR-003: Lazy Price Resolution](architecture/adr/003-lazy-price-resolution.md)
  - [ADR-004: Multiple Instances](architecture/adr/004-multiple-instances.md)
  - [ADR-005: Condition Ordering](architecture/adr/005-condition-ordering.md)

## Reference

- [Events](events.md) - All cart events
- [Exceptions](exceptions.md) - Exception handling
- [Testing](testing.md) - Testing utilities and assertions

## Meta

- [Upgrade Guide](upgrade-guide.md) - Migration between versions
- [Contributing](contributing.md) - How to contribute

## Quick Links

| Task | Link |
|------|------|
| Add item to cart | [Basic Usage](usage/basic-usage.md#adding-items) |
| Apply discount | [Conditions](usage/conditions.md#discount-condition) |
| Handle tax | [Conditions](usage/conditions.md#tax-condition) |
| Use wishlist | [Multiple Instances](usage/multiple-instances.md#wishlist) |
| Write tests | [Testing](testing.md) |
| Create custom driver | [Custom Storage Driver](extending/custom-storage-driver.md) |
