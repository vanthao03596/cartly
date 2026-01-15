# Contributing

Thank you for considering contributing to Cartly! This document provides guidelines and information for contributors.

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git

### Clone and Install

```bash
git clone https://github.com/vanthao03596/cartly.git
cd cartly
composer install
```

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test file
vendor/bin/phpunit tests/Unit/CartItemTest.php

# Run specific test method
vendor/bin/phpunit --filter=test_can_add_item
```

### Static Analysis

```bash
# Run PHPStan
composer analyse

# Generate baseline (for existing errors)
composer baseline
```

## Code Style

### PHP Standards

- Follow PSR-12 coding standard
- Use strict types: `declare(strict_types=1);`
- Type all parameters and return values
- Use PHP 8.1+ features where appropriate

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `CartManager` |
| Methods | camelCase | `getInstance()` |
| Properties | camelCase | `$priceResolver` |
| Constants | SCREAMING_SNAKE | `MAX_ITEMS` |
| Config keys | snake_case | `default_instance` |

### Documentation

- Add PHPDoc blocks to public methods
- Include `@param`, `@return`, `@throws` tags
- Keep descriptions concise

```php
/**
 * Add an item to the cart.
 *
 * @param Buyable|int|string $item The item to add
 * @param int $quantity Quantity (must be >= 1)
 * @param array<string, mixed> $options Item options
 * @param array<string, mixed> $meta Additional metadata
 * @return CartItem The added or updated item
 *
 * @throws InvalidQuantityException If quantity < 1
 * @throws MaxItemsExceededException If max_items limit reached
 */
public function add(
    Buyable|int|string $item,
    int $quantity = 1,
    array $options = [],
    array $meta = []
): CartItem {
    // ...
}
```

## Pull Request Process

### 1. Fork and Branch

```bash
# Fork the repository on GitHub, then:
git clone https://github.com/YOUR_USERNAME/cartly.git
cd cartly
git checkout -b feature/your-feature-name
```

### 2. Make Changes

- Write clean, tested code
- Follow existing patterns in the codebase
- Add tests for new functionality
- Update documentation if needed

### 3. Test Your Changes

```bash
# Run tests
composer test

# Run static analysis
composer analyse

# Ensure all pass before submitting
```

### 4. Commit Guidelines

Use conventional commit messages:

```
type(scope): description

[optional body]

[optional footer]
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `style`: Code style (formatting, no logic change)
- `refactor`: Code change that neither fixes nor adds
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

Examples:
```
feat(conditions): add ShippingCondition class
fix(storage): handle null identifier in DatabaseDriver
docs(readme): update installation instructions
test(cart): add tests for move operations
```

### 5. Submit Pull Request

- Push your branch to your fork
- Open a PR against `main` branch
- Fill out the PR template
- Link any related issues

### 6. Review Process

- Maintainers will review your PR
- Address any feedback
- Once approved, your PR will be merged

## Writing Tests

### Test Organization

```
tests/
├── Unit/                   # Unit tests (isolated)
│   ├── CartItemTest.php
│   ├── CartInstanceTest.php
│   └── Conditions/
│       └── TaxConditionTest.php
├── Feature/                # Feature tests (integration)
│   ├── CartOperationsTest.php
│   └── UserAssociationTest.php
└── TestCase.php           # Base test class
```

### Test Structure

```php
<?php

declare(strict_types=1);

namespace Cart\Tests\Unit;

use Cart\Cart;
use Cart\Tests\TestCase;

class CartItemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cart::fake();
        Cart::fakeResolver(1000);
    }

    public function test_can_add_item(): void
    {
        $item = Cart::add(1, quantity: 2);

        $this->assertEquals(1, $item->id);
        $this->assertEquals(2, $item->quantity);
    }

    public function test_throws_on_invalid_quantity(): void
    {
        $this->expectException(InvalidQuantityException::class);

        Cart::add(1, quantity: 0);
    }
}
```

### Test Naming

- Use `test_` prefix or `@test` annotation
- Describe the behavior being tested
- Use snake_case for readability

```php
// Good
public function test_can_move_item_to_wishlist(): void
public function test_throws_when_exceeding_max_items(): void

// Avoid
public function testMove(): void
public function test1(): void
```

## Reporting Issues

### Bug Reports

Include:
1. Cartly version
2. Laravel version
3. PHP version
4. Steps to reproduce
5. Expected behavior
6. Actual behavior
7. Stack trace (if applicable)

### Feature Requests

Include:
1. Clear description of the feature
2. Use case / problem it solves
3. Proposed implementation (optional)
4. Willingness to contribute

## Architecture Guidelines

### Adding New Features

1. **Discuss First** - Open an issue to discuss major changes
2. **Follow Patterns** - Look at existing code for patterns
3. **Interface First** - Define contracts before implementation
4. **Test Coverage** - Aim for high test coverage

### Adding Storage Drivers

1. Implement `StorageDriver` interface
2. Add configuration in `config/cart.php`
3. Register in `CartServiceProvider`
4. Add tests
5. Document in `extending/custom-storage-driver.md`

### Adding Conditions

1. Extend `BaseCondition` or implement `Condition`
2. Implement `toArray()` and `fromArray()`
3. Add tests
4. Document in `api-reference/conditions.md`

## Documentation

### Updating Docs

- Documentation lives in `docs/`
- Use Markdown format
- Include code examples
- Keep examples runnable

### Adding New Pages

1. Create the `.md` file in appropriate directory
2. Add link to `docs/index.md`
3. Cross-link from related pages

## Release Process

Maintainers follow semantic versioning:

- **Major** (1.0.0): Breaking changes
- **Minor** (1.1.0): New features, backwards compatible
- **Patch** (1.1.1): Bug fixes, backwards compatible

## Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow
- Report unacceptable behavior to maintainers

## Questions?

- Open a [GitHub Discussion](https://github.com/vanthao03596/cartly/discussions)
- Check existing issues and PRs
- Read the documentation first

Thank you for contributing!
