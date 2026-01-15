<?php

declare(strict_types=1);

namespace Cart;

use Cart\Contracts\Buyable;
use Cart\Contracts\Condition;
use Cart\Contracts\PriceResolver;
use Cart\Contracts\StorageDriver;
use Cart\Testing\CartFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Cart Facade.
 *
 * @method static CartInstance instance(?string $name = null)
 * @method static string currentInstance()
 * @method static CartManager setDriver(string|StorageDriver $driver)
 * @method static CartManager setPriceResolver(PriceResolver $resolver)
 * @method static CartManager associate(Authenticatable $user)
 * @method static void handleLogin(Authenticatable $user)
 * @method static CartItem moveTo(string $rowId, string $targetInstance)
 * @method static CartItem moveToWishlist(string $rowId)
 * @method static CartItem moveToCart(string $rowId)
 * @method static CartManager fake(?array<string, mixed> $options = null)
 * @method static CartManager fakeResolver(int|callable $resolver)
 * @method static CartFactory factory()
 *
 * Instance methods (proxied to current instance):
 * @method static CartItem add(Buyable|int|string $item, int $quantity = 1, array<string, mixed> $options = [], array<string, mixed> $meta = [])
 * @method static CartItem update(string $rowId, int|array<string, mixed> $attributes)
 * @method static void remove(string $rowId)
 * @method static CartItem|null get(string $rowId)
 * @method static CartItem|null find(int|string $buyableId)
 * @method static bool has(string $rowId)
 * @method static CartItemCollection content()
 * @method static bool isEmpty()
 * @method static bool isNotEmpty()
 * @method static int count()
 * @method static int countItems()
 * @method static int subtotal()
 * @method static int total()
 * @method static int savings()
 * @method static int conditionsTotal()
 * @method static int taxTotal()
 * @method static int discountTotal()
 * @method static void condition(Condition $condition)
 * @method static void removeCondition(string $name)
 * @method static Condition|null getCondition(string $name)
 * @method static Collection<string, Condition> getConditions()
 * @method static bool hasCondition(string $name)
 * @method static void clearConditions()
 * @method static void clear()
 * @method static void destroy()
 * @method static void refreshPrices()
 *
 * Testing assertions:
 * @method static void assertItemCount(int $expected, ?string $instance = null)
 * @method static void assertUniqueItemCount(int $expected, ?string $instance = null)
 * @method static void assertHas(int|string $buyableId, ?string $instance = null)
 * @method static void assertDoesNotHave(int|string $buyableId, ?string $instance = null)
 * @method static void assertHasRowId(string $rowId, ?string $instance = null)
 * @method static void assertTotal(int $expectedCents, ?string $instance = null)
 * @method static void assertSubtotal(int $expectedCents, ?string $instance = null)
 * @method static void assertEmpty(?string $instance = null)
 * @method static void assertNotEmpty(?string $instance = null)
 * @method static void assertConditionApplied(string $conditionName, ?string $instance = null)
 * @method static void assertConditionNotApplied(string $conditionName, ?string $instance = null)
 * @method static void assertQuantity(int|string $buyableId, int $expectedQuantity, ?string $instance = null)
 * @method static void assertTaxTotal(int $expectedCents, ?string $instance = null)
 * @method static void assertDiscountTotal(int $expectedCents, ?string $instance = null)
 *
 * Magic instance methods:
 * @method static CartInstance wishlist()
 * @method static CartInstance compare()
 *
 * @see \Cart\CartManager
 */
class Cart extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return CartManager::class;
    }
}
