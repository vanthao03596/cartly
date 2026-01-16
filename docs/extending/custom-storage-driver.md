# Custom Storage Driver

Create a custom storage driver to store cart data in any backend.

## StorageDriver Interface

```php
namespace Cart\Contracts;

use Cart\CartContent;

interface StorageDriver
{
    /**
     * Get cart content from storage.
     */
    public function get(string $instance, ?string $identifier = null): ?CartContent;

    /**
     * Store cart content.
     */
    public function put(string $instance, CartContent $content, ?string $identifier = null): void;

    /**
     * Remove cart from storage.
     */
    public function forget(string $instance, ?string $identifier = null): void;

    /**
     * Remove all carts for an identifier.
     */
    public function flush(?string $identifier = null): void;
}
```

## Creating a Custom Driver

### Example: Redis Driver

```php
<?php

namespace App\Cart\Drivers;

use Cart\CartContent;
use Cart\Contracts\StorageDriver;
use Illuminate\Support\Facades\Redis;

class RedisDriver implements StorageDriver
{
    private string $prefix;
    private int $ttl;

    public function __construct(string $prefix = 'cart', int $ttl = 604800)
    {
        $this->prefix = $prefix;
        $this->ttl = $ttl; // 7 days in seconds
    }

    public function get(string $instance, ?string $identifier = null): ?CartContent
    {
        $key = $this->buildKey($instance, $identifier);
        $data = Redis::get($key);

        if ($data === null) {
            return null;
        }

        return CartContent::fromJson($data);
    }

    public function put(string $instance, CartContent $content, ?string $identifier = null): void
    {
        $key = $this->buildKey($instance, $identifier);

        Redis::setex($key, $this->ttl, $content->toJson());

        // Track instances for this identifier
        if ($identifier) {
            Redis::sadd("{$this->prefix}:instances:{$identifier}", $instance);
        }
    }

    public function forget(string $instance, ?string $identifier = null): void
    {
        $key = $this->buildKey($instance, $identifier);

        Redis::del($key);

        if ($identifier) {
            Redis::srem("{$this->prefix}:instances:{$identifier}", $instance);
        }
    }

    public function flush(?string $identifier = null): void
    {
        if ($identifier === null) {
            return;
        }

        // Get all instances for this identifier
        $instances = Redis::smembers("{$this->prefix}:instances:{$identifier}");

        foreach ($instances as $instance) {
            $this->forget($instance, $identifier);
        }

        Redis::del("{$this->prefix}:instances:{$identifier}");
    }

    private function buildKey(string $instance, ?string $identifier): string
    {
        return $identifier
            ? "{$this->prefix}:{$identifier}:{$instance}"
            : "{$this->prefix}:guest:{$instance}";
    }
}
```

### Example: File Driver

```php
<?php

namespace App\Cart\Drivers;

use Cart\CartContent;
use Cart\Contracts\StorageDriver;
use Illuminate\Support\Facades\File;

class FileDriver implements StorageDriver
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('carts');

        if (!File::isDirectory($this->path)) {
            File::makeDirectory($this->path, 0755, true);
        }
    }

    public function get(string $instance, ?string $identifier = null): ?CartContent
    {
        $file = $this->buildPath($instance, $identifier);

        if (!File::exists($file)) {
            return null;
        }

        $data = File::get($file);

        return CartContent::fromJson($data);
    }

    public function put(string $instance, CartContent $content, ?string $identifier = null): void
    {
        $file = $this->buildPath($instance, $identifier);
        $dir = dirname($file);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($file, $content->toJson());
    }

    public function forget(string $instance, ?string $identifier = null): void
    {
        $file = $this->buildPath($instance, $identifier);

        if (File::exists($file)) {
            File::delete($file);
        }
    }

    public function flush(?string $identifier = null): void
    {
        $dir = $identifier
            ? "{$this->path}/{$identifier}"
            : $this->path;

        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    private function buildPath(string $instance, ?string $identifier): string
    {
        $identifier = $identifier ?? 'guest';

        return "{$this->path}/{$identifier}/{$instance}.json";
    }
}
```

## Registering the Driver

### Method 1: Via Configuration (Recommended)

The simplest way to register a custom driver is via configuration:

```php
// config/cart.php
'driver' => env('CART_DRIVER', 'redis'),

'drivers' => [
    // ... existing drivers

    'redis' => [
        'class' => \App\Cart\Drivers\RedisDriver::class,
        'prefix' => 'cart',
        'ttl' => 60 * 60 * 24 * 7, // 7 days
        'connection' => 'default',
    ],
],
```

The driver will be resolved via Laravel's container, so you can use dependency injection in your driver's constructor.

### Method 2: Direct Usage

```php
use App\Cart\Drivers\RedisDriver;
use Cart\Cart;

Cart::setDriver(new RedisDriver());
```

### Method 3: Service Provider

```php
<?php

namespace App\Providers;

use App\Cart\Drivers\RedisDriver;
use Cart\Contracts\StorageDriver;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageDriver::class, function ($app) {
            return new RedisDriver(
                prefix: config('cart.drivers.redis.prefix', 'cart'),
                ttl: config('cart.drivers.redis.ttl', 604800)
            );
        });
    }
}
```

### Method 4: Extend CartManager

```php
// In a service provider
use Cart\CartManager;
use App\Cart\Drivers\RedisDriver;

$this->app->extend(CartManager::class, function ($manager, $app) {
    if (config('cart.driver') === 'redis') {
        $manager->setDriver(new RedisDriver());
    }

    return $manager;
});
```

## CartContent Serialization

The `CartContent` class handles serialization:

```php
// Serialize
$json = $content->toJson();
$array = $content->toArray();

// Deserialize
$content = CartContent::fromJson($json);
$content = CartContent::fromArray($array);
```

### Serialized Structure

```json
{
    "items": [
        {
            "rowId": "abc123...",
            "id": 1,
            "quantity": 2,
            "options": {"size": "L"},
            "meta": {},
            "buyableType": "App\\Models\\Product",
            "buyableId": 1,
            "conditions": []
        }
    ],
    "conditions": [
        {
            "class": "Cart\\Conditions\\TaxCondition",
            "name": "VAT",
            "rate": 20,
            "includedInPrice": false,
            "target": "subtotal"
        }
    ],
    "meta": {}
}
```

## Best Practices

1. **Handle null identifier gracefully**
   - Session-based carts may not have an identifier
   - Use a fallback like 'guest' or session ID

2. **Implement proper error handling**
   ```php
   public function get(string $instance, ?string $identifier = null): ?CartContent
   {
       try {
           // Fetch data
       } catch (\Exception $e) {
           Log::warning('Cart fetch failed', ['error' => $e->getMessage()]);
           return null; // Return empty cart on read failure
       }
   }
   ```

3. **Use transactions for database drivers**
   ```php
   DB::transaction(function () use ($instance, $content, $identifier) {
       // Update or insert
   });
   ```

4. **Consider TTL/expiration**
   - Clean up old carts periodically
   - Use built-in expiration where available (Redis, cache)

5. **Track instances per identifier**
   - Needed for proper `flush()` implementation
   - Allows cleaning all instances when user logs out

## Testing Custom Drivers

```php
use App\Cart\Drivers\RedisDriver;
use Cart\CartContent;
use Cart\CartItem;

class RedisDriverTest extends TestCase
{
    private RedisDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new RedisDriver('test_cart');
    }

    protected function tearDown(): void
    {
        $this->driver->flush('test_user');
        parent::tearDown();
    }

    public function test_can_store_and_retrieve()
    {
        $content = new CartContent();
        $content->items->put('abc', CartItem::fromArray([
            'rowId' => 'abc',
            'id' => 1,
            'quantity' => 2,
        ]));

        $this->driver->put('default', $content, 'test_user');

        $retrieved = $this->driver->get('default', 'test_user');

        $this->assertNotNull($retrieved);
        $this->assertTrue($retrieved->items->hasRowId('abc'));
    }

    public function test_returns_null_for_missing_cart()
    {
        $result = $this->driver->get('nonexistent', 'test_user');

        $this->assertNull($result);
    }

    public function test_can_forget_cart()
    {
        $content = new CartContent();
        $this->driver->put('default', $content, 'test_user');

        $this->driver->forget('default', 'test_user');

        $this->assertNull($this->driver->get('default', 'test_user'));
    }

    public function test_can_flush_all_instances()
    {
        $content = new CartContent();

        $this->driver->put('default', $content, 'test_user');
        $this->driver->put('wishlist', $content, 'test_user');

        $this->driver->flush('test_user');

        $this->assertNull($this->driver->get('default', 'test_user'));
        $this->assertNull($this->driver->get('wishlist', 'test_user'));
    }
}
```
