# Custom Price Resolver

Create a custom price resolver for advanced pricing logic.

## PriceResolver Interface

```php
namespace Cart\Contracts;

use Cart\CartItem;
use Cart\CartContext;
use Cart\CartItemCollection;
use Cart\ResolvedPrice;

interface PriceResolver
{
    /**
     * Resolve price for a single item.
     */
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice;

    /**
     * Resolve prices for multiple items (batch optimization).
     *
     * @return array<string, ResolvedPrice> Keyed by rowId
     */
    public function resolveMany(CartItemCollection $items, CartContext $context): array;
}
```

## ResolvedPrice Class

```php
readonly class ResolvedPrice
{
    public function __construct(
        public int $unitPrice,        // Current price in cents
        public int $originalPrice,    // Original price in cents
        public ?string $priceSource = null,  // 'base', 'sale', 'tier', etc.
        public array $meta = []       // Additional metadata
    ) {}
}
```

## Creating a Custom Resolver

### Example: Tiered Pricing Resolver

```php
<?php

namespace App\Cart\Resolvers;

use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Contracts\PriceResolver;
use Cart\Exceptions\UnresolvablePriceException;
use Cart\ResolvedPrice;
use App\Models\Product;
use App\Models\PriceTier;

class TieredPriceResolver implements PriceResolver
{
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice
    {
        $product = $this->loadProduct($item);

        if (!$product) {
            throw UnresolvablePriceException::modelNotFound(
                $item->rowId,
                $item->buyableType,
                $item->buyableId
            );
        }

        return $this->calculateTieredPrice($product, $item->quantity, $context);
    }

    public function resolveMany(CartItemCollection $items, CartContext $context): array
    {
        // Batch load products
        $productIds = $items->pluck('buyableId')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $resolved = [];

        foreach ($items as $item) {
            $product = $products->get($item->buyableId);

            if (!$product) {
                throw UnresolvablePriceException::modelNotFound(
                    $item->rowId,
                    $item->buyableType,
                    $item->buyableId
                );
            }

            $resolved[$item->rowId] = $this->calculateTieredPrice(
                $product,
                $item->quantity,
                $context
            );
        }

        return $resolved;
    }

    private function calculateTieredPrice(
        Product $product,
        int $quantity,
        CartContext $context
    ): ResolvedPrice {
        // Find applicable tier
        $tier = PriceTier::where('product_id', $product->id)
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($tier) {
            return new ResolvedPrice(
                unitPrice: $tier->price,
                originalPrice: $product->price,
                priceSource: 'tier',
                meta: [
                    'tier_id' => $tier->id,
                    'tier_name' => $tier->name,
                    'min_quantity' => $tier->min_quantity,
                ]
            );
        }

        // No tier, use base price
        return new ResolvedPrice(
            unitPrice: $product->sale_price ?? $product->price,
            originalPrice: $product->price,
            priceSource: $product->sale_price ? 'sale' : 'base'
        );
    }

    private function loadProduct(CartItem $item): ?Product
    {
        if ($item->buyableType !== Product::class) {
            return null;
        }

        return Product::find($item->buyableId);
    }
}
```

### Example: User-Based Pricing Resolver

```php
<?php

namespace App\Cart\Resolvers;

use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Contracts\PriceResolver;
use Cart\ResolvedPrice;
use App\Models\Product;
use App\Models\UserPriceGroup;

class UserPriceResolver implements PriceResolver
{
    public function resolve(CartItem $item, CartContext $context): ResolvedPrice
    {
        $product = Product::find($item->buyableId);

        return $this->resolveForUser($product, $context);
    }

    public function resolveMany(CartItemCollection $items, CartContext $context): array
    {
        $productIds = $items->pluck('buyableId')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $resolved = [];

        foreach ($items as $item) {
            $product = $products->get($item->buyableId);
            $resolved[$item->rowId] = $this->resolveForUser($product, $context);
        }

        return $resolved;
    }

    private function resolveForUser(Product $product, CartContext $context): ResolvedPrice
    {
        $user = $context->user;

        // Guest pricing
        if (!$user) {
            return new ResolvedPrice(
                unitPrice: $product->price,
                originalPrice: $product->price,
                priceSource: 'guest'
            );
        }

        // Check for user-specific pricing
        $userPrice = $product->userPrices()
            ->where('user_id', $user->id)
            ->first();

        if ($userPrice) {
            return new ResolvedPrice(
                unitPrice: $userPrice->price,
                originalPrice: $product->price,
                priceSource: 'user_specific',
                meta: ['discount_percent' => $userPrice->discount_percent]
            );
        }

        // Check for price group
        $priceGroup = $user->priceGroup;

        if ($priceGroup) {
            $discountedPrice = (int) ($product->price * (1 - $priceGroup->discount / 100));

            return new ResolvedPrice(
                unitPrice: $discountedPrice,
                originalPrice: $product->price,
                priceSource: 'price_group',
                meta: [
                    'group_id' => $priceGroup->id,
                    'group_name' => $priceGroup->name,
                    'discount_percent' => $priceGroup->discount,
                ]
            );
        }

        // Default pricing
        return new ResolvedPrice(
            unitPrice: $product->sale_price ?? $product->price,
            originalPrice: $product->price,
            priceSource: $product->sale_price ? 'sale' : 'base'
        );
    }
}
```

### Example: External API Pricing Resolver

```php
<?php

namespace App\Cart\Resolvers;

use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;
use Cart\Contracts\PriceResolver;
use Cart\ResolvedPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ExternalPriceResolver implements PriceResolver
{
    private string $apiUrl;
    private int $cacheTtl;

    public function __construct(string $apiUrl, int $cacheTtl = 300)
    {
        $this->apiUrl = $apiUrl;
        $this->cacheTtl = $cacheTtl;
    }

    public function resolve(CartItem $item, CartContext $context): ResolvedPrice
    {
        $prices = $this->fetchPrices([$item->buyableId], $context);

        return $prices[$item->buyableId] ?? throw new \Exception('Price not found');
    }

    public function resolveMany(CartItemCollection $items, CartContext $context): array
    {
        $productIds = $items->pluck('buyableId')->unique()->toArray();
        $prices = $this->fetchPrices($productIds, $context);

        $resolved = [];

        foreach ($items as $item) {
            $price = $prices[$item->buyableId] ?? null;

            if (!$price) {
                throw new \Exception("Price not found for {$item->buyableId}");
            }

            $resolved[$item->rowId] = $price;
        }

        return $resolved;
    }

    private function fetchPrices(array $productIds, CartContext $context): array
    {
        $cacheKey = 'prices:' . md5(json_encode($productIds) . ($context->user?->id ?? 'guest'));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($productIds, $context) {
            $response = Http::post($this->apiUrl, [
                'product_ids' => $productIds,
                'user_id' => $context->user?->id,
                'currency' => $context->currency,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch prices from API');
            }

            $prices = [];

            foreach ($response->json('prices') as $priceData) {
                $prices[$priceData['product_id']] = new ResolvedPrice(
                    unitPrice: $priceData['price'],
                    originalPrice: $priceData['original_price'],
                    priceSource: $priceData['source'] ?? 'api',
                    meta: $priceData['meta'] ?? []
                );
            }

            return $prices;
        });
    }
}
```

## Using Chain Resolver

Combine multiple resolvers with fallback:

```php
use Cart\Resolvers\ChainPriceResolver;

$resolver = new ChainPriceResolver([
    new UserPriceResolver(),      // Try user-specific pricing first
    new TieredPriceResolver(),    // Fall back to tiered pricing
    new BuyablePriceResolver(),   // Finally use model pricing
]);

Cart::setPriceResolver($resolver);
```

## Using Best Price Resolver

Get the lowest price from multiple sources:

```php
use Cart\Resolvers\BestPriceResolver;

$resolver = new BestPriceResolver([
    new RegularPriceResolver(),
    new SalePriceResolver(),
    new CouponPriceResolver(),
]);

Cart::setPriceResolver($resolver);
```

## Registering Custom Resolver

### Method 1: Direct Usage

```php
use Cart\Cart;
use App\Cart\Resolvers\TieredPriceResolver;

Cart::setPriceResolver(new TieredPriceResolver());
```

### Method 2: Configuration

```php
// config/cart.php
'price_resolver' => App\Cart\Resolvers\TieredPriceResolver::class,
```

### Method 3: Service Provider

```php
use Cart\Contracts\PriceResolver;
use App\Cart\Resolvers\TieredPriceResolver;

$this->app->singleton(PriceResolver::class, TieredPriceResolver::class);
```

## CartContext

The `CartContext` provides contextual information for pricing:

```php
readonly class CartContext
{
    public ?Authenticatable $user;  // Current user
    public string $instance;         // Cart instance name
    public ?string $currency;        // Currency code
    public ?string $locale;          // Locale
    public array $meta;              // Additional context

    public static function current(?string $instance = null): self;
    public function withUser(?Authenticatable $user): self;
    public function withMeta(array $meta): self;
}
```

### Using Context in Resolver

```php
private function resolvePrice(Product $product, CartContext $context): ResolvedPrice
{
    // User-based pricing
    if ($context->user?->is_vip) {
        return new ResolvedPrice(
            unitPrice: (int) ($product->price * 0.9),
            originalPrice: $product->price,
            priceSource: 'vip'
        );
    }

    // Currency conversion
    if ($context->currency && $context->currency !== 'USD') {
        $rate = $this->getExchangeRate($context->currency);
        return new ResolvedPrice(
            unitPrice: (int) ($product->price * $rate),
            originalPrice: (int) ($product->price * $rate),
            priceSource: 'converted',
            meta: ['currency' => $context->currency, 'rate' => $rate]
        );
    }

    return new ResolvedPrice(
        unitPrice: $product->price,
        originalPrice: $product->price,
        priceSource: 'base'
    );
}
```

## Testing Custom Resolvers

```php
use App\Cart\Resolvers\TieredPriceResolver;
use Cart\CartContext;
use Cart\CartItem;
use Cart\CartItemCollection;

class TieredPriceResolverTest extends TestCase
{
    private TieredPriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TieredPriceResolver();
    }

    public function test_applies_tier_pricing()
    {
        $product = Product::factory()->create(['price' => 1000]);
        PriceTier::factory()->create([
            'product_id' => $product->id,
            'min_quantity' => 10,
            'price' => 800,
        ]);

        $item = CartItem::fromArray([
            'rowId' => 'test',
            'id' => $product->id,
            'quantity' => 10,
            'buyableType' => Product::class,
            'buyableId' => $product->id,
        ]);

        $context = CartContext::current();
        $price = $this->resolver->resolve($item, $context);

        $this->assertEquals(800, $price->unitPrice);
        $this->assertEquals(1000, $price->originalPrice);
        $this->assertEquals('tier', $price->priceSource);
    }

    public function test_uses_base_price_below_tier()
    {
        $product = Product::factory()->create(['price' => 1000]);
        PriceTier::factory()->create([
            'product_id' => $product->id,
            'min_quantity' => 10,
            'price' => 800,
        ]);

        $item = CartItem::fromArray([
            'rowId' => 'test',
            'id' => $product->id,
            'quantity' => 5, // Below tier threshold
            'buyableType' => Product::class,
            'buyableId' => $product->id,
        ]);

        $context = CartContext::current();
        $price = $this->resolver->resolve($item, $context);

        $this->assertEquals(1000, $price->unitPrice);
        $this->assertEquals('base', $price->priceSource);
    }
}
```
