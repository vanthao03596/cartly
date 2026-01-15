# Testing

Cartly provides comprehensive testing utilities for writing tests.

## Fake Mode

Enable fake mode to use in-memory storage and disable external dependencies.

```php
use Cart\Cart;

public function setUp(): void
{
    parent::setUp();

    Cart::fake();
}
```

### Fake Mode Options

```php
// Default: ArrayDriver, events disabled
Cart::fake();

// Enable events in fake mode
Cart::fake(['events' => true]);
```

## Fake Price Resolver

Set a fake price resolver for testing without database models.

### Fixed Price

```php
// All items return $10.00
Cart::fakeResolver(1000);

Cart::add(1);
Cart::add(2);

$this->assertEquals(2000, Cart::subtotal()); // 2 x $10
```

### Dynamic Price

```php
// Price based on item ID
Cart::fakeResolver(function (CartItem $item) {
    return $item->id * 100; // ID 1 = $1, ID 5 = $5
});

Cart::add(1);  // $1.00
Cart::add(5);  // $5.00

$this->assertEquals(600, Cart::subtotal()); // $6.00
```

## Cart Factory

Build test carts with predefined items and conditions.

```php
use Cart\Cart;

Cart::factory()
    ->withItems([
        ['id' => 1, 'quantity' => 2, 'price' => 1000],
        ['id' => 2, 'quantity' => 1, 'price' => 2500],
    ])
    ->create();
```

### Factory Methods

```php
Cart::factory()
    ->instance('wishlist')                    // Target instance
    ->withItems([...])                        // Add items
    ->withCondition($condition)               // Add condition
    ->withConditions([$cond1, $cond2])        // Add multiple conditions
    ->create();                               // Build the cart
```

### Item Format

```php
[
    'id' => 1,                    // Required: buyable ID
    'quantity' => 2,              // Optional: default 1
    'price' => 1000,              // Optional: price in cents
    'originalPrice' => 1500,      // Optional: original price
    'options' => ['size' => 'L'], // Optional: options
    'meta' => ['gift' => true],   // Optional: metadata
]
```

### Full Example

```php
use Cart\Cart;
use Cart\Conditions\TaxCondition;
use Cart\Conditions\DiscountCondition;

public function test_cart_total_with_conditions()
{
    Cart::fake();

    Cart::factory()
        ->withItems([
            ['id' => 1, 'quantity' => 2, 'price' => 1000],  // $20
            ['id' => 2, 'quantity' => 1, 'price' => 3000],  // $30
        ])
        ->withConditions([
            new DiscountCondition('Sale', 10, 'percentage'),  // -$5
            new TaxCondition('Tax', 10),                       // +$4.50
        ])
        ->create();

    // Subtotal: $50, -10% = $45, +10% tax = $49.50
    $this->assertEquals(4950, Cart::total());
}
```

## Assertions

Cartly provides assertion methods for testing.

### Item Assertions

```php
// Assert total quantity
Cart::assertItemCount(5);
Cart::assertItemCount(3, 'wishlist');

// Assert unique item count
Cart::assertUniqueItemCount(2);

// Assert item exists by buyable ID
Cart::assertHas($productId);
Cart::assertDoesNotHave($productId);

// Assert item exists by rowId
Cart::assertHasRowId($rowId);

// Assert specific quantity for item
Cart::assertQuantity($productId, 3);
```

### Total Assertions

```php
// Assert exact total (in cents)
Cart::assertTotal(4999);
Cart::assertTotal(4999, 'wishlist');

// Assert exact subtotal
Cart::assertSubtotal(4500);

// Assert tax total
Cart::assertTaxTotal(450);

// Assert discount total
Cart::assertDiscountTotal(-500);  // Note: negative value
```

### Empty/Not Empty

```php
Cart::assertEmpty();
Cart::assertEmpty('wishlist');

Cart::assertNotEmpty();
Cart::assertNotEmpty('compare');
```

### Condition Assertions

```php
Cart::assertConditionApplied('VAT');
Cart::assertConditionApplied('Shipping', 'default');

Cart::assertConditionNotApplied('Coupon');
```

## Test Examples

### Basic Cart Operations

```php
use Cart\Cart;
use Tests\TestCase;

class CartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cart::fake();
        Cart::fakeResolver(1000); // $10 per item
    }

    public function test_can_add_item()
    {
        Cart::add(1, quantity: 2);

        Cart::assertHas(1);
        Cart::assertItemCount(2);
        Cart::assertSubtotal(2000);
    }

    public function test_can_update_quantity()
    {
        $item = Cart::add(1);
        Cart::update($item->rowId, 5);

        Cart::assertQuantity(1, 5);
        Cart::assertSubtotal(5000);
    }

    public function test_can_remove_item()
    {
        $item = Cart::add(1);
        Cart::remove($item->rowId);

        Cart::assertEmpty();
        Cart::assertDoesNotHave(1);
    }
}
```

### Testing Conditions

```php
use Cart\Conditions\TaxCondition;
use Cart\Conditions\DiscountCondition;

public function test_tax_is_applied()
{
    Cart::fake();
    Cart::fakeResolver(1000);

    Cart::add(1, quantity: 10); // $100 subtotal
    Cart::condition(new TaxCondition('VAT', 20)); // 20% tax

    Cart::assertSubtotal(10000);
    Cart::assertTaxTotal(2000);
    Cart::assertTotal(12000);
}

public function test_discount_is_applied()
{
    Cart::fake();
    Cart::fakeResolver(1000);

    Cart::add(1, quantity: 10); // $100 subtotal
    Cart::condition(new DiscountCondition('Sale', 10, 'percentage'));

    Cart::assertSubtotal(10000);
    Cart::assertDiscountTotal(-1000); // -$10
    Cart::assertTotal(9000);
}
```

### Testing Multiple Instances

```php
public function test_instances_are_isolated()
{
    Cart::fake();
    Cart::fakeResolver(1000);

    Cart::add(1);
    Cart::instance('wishlist')->add(2);
    Cart::instance('wishlist')->add(3);

    Cart::assertItemCount(1);
    Cart::assertItemCount(2, 'wishlist');

    Cart::clear();

    Cart::assertEmpty();
    Cart::assertNotEmpty('wishlist');
}
```

### Testing Move Operations

```php
public function test_can_move_to_wishlist()
{
    Cart::fake();
    Cart::fakeResolver(1000);

    $item = Cart::add(1);
    Cart::moveToWishlist($item->rowId);

    Cart::assertEmpty();
    Cart::assertHas(1, 'wishlist');
}
```

### Testing Exceptions

```php
use Cart\Exceptions\InvalidQuantityException;
use Cart\Exceptions\InvalidRowIdException;
use Cart\Exceptions\MaxItemsExceededException;

public function test_throws_on_invalid_quantity()
{
    Cart::fake();

    $this->expectException(InvalidQuantityException::class);

    Cart::add(1, quantity: 0);
}

public function test_throws_on_invalid_row_id()
{
    Cart::fake();

    $this->expectException(InvalidRowIdException::class);

    Cart::remove('nonexistent');
}

public function test_throws_on_max_items_exceeded()
{
    Cart::fake();
    Cart::fakeResolver(1000);

    // Compare has max_items: 4 in default config

    Cart::compare()->add(1);
    Cart::compare()->add(2);
    Cart::compare()->add(3);
    Cart::compare()->add(4);

    $this->expectException(MaxItemsExceededException::class);

    Cart::compare()->add(5);
}
```

### Testing Events

```php
use Cart\Events\CartItemAdded;
use Illuminate\Support\Facades\Event;

public function test_event_dispatched_on_add()
{
    Cart::fake(['events' => true]);
    Cart::fakeResolver(1000);

    Event::fake([CartItemAdded::class]);

    Cart::add(1);

    Event::assertDispatched(CartItemAdded::class, function ($event) {
        return $event->item->id === 1;
    });
}
```

### Feature Test Example

```php
use Cart\Cart;
use App\Models\Product;

class CartFeatureTest extends TestCase
{
    public function test_user_can_add_to_cart()
    {
        Cart::fake();

        $product = Product::factory()->create(['price' => 2999]);

        Cart::fakeResolver($product->price);

        $response = $this->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertOk();

        Cart::assertHas($product->id);
        Cart::assertQuantity($product->id, 2);
    }
}
```

## Best Practices

1. **Always call `Cart::fake()` in setUp**
   ```php
   protected function setUp(): void
   {
       parent::setUp();
       Cart::fake();
   }
   ```

2. **Use `fakeResolver` for price testing**
   ```php
   Cart::fakeResolver(1000); // Consistent $10 price
   ```

3. **Clear between tests** (handled automatically by fake mode)

4. **Test edge cases**
   - Empty cart
   - Single item
   - Maximum items
   - Zero quantity
   - Invalid row IDs

5. **Use assertions** for readable tests
   ```php
   // Good
   Cart::assertTotal(5000);

   // Less readable
   $this->assertEquals(5000, Cart::total());
   ```
