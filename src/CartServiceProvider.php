<?php

declare(strict_types=1);

namespace Cart;

use Cart\Contracts\PriceResolver;
use Cart\Contracts\StorageDriver;
use Cart\Drivers\SessionDriver;
use Cart\Resolvers\BuyablePriceResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cart.php',
            'cart'
        );

        // Register CartManager as singleton
        $this->app->singleton(CartManager::class, function () {
            return new CartManager();
        });

        // Alias for easier access
        $this->app->alias(CartManager::class, 'cart');

        // Bind contracts to default implementations
        $this->app->bind(StorageDriver::class, function () {
            $driver = config('cart.driver', 'session');

            return match ($driver) {
                'session' => new Drivers\SessionDriver(),
                'database' => new Drivers\DatabaseDriver(),
                'cache' => new Drivers\CacheDriver(),
                'array' => new Drivers\ArrayDriver(),
                default => new SessionDriver(),
            };
        });

        $this->app->bind(PriceResolver::class, function () {
            $resolverClass = config('cart.price_resolver');

            if ($resolverClass !== null && class_exists($resolverClass)) {
                return new $resolverClass();
            }

            return new BuyablePriceResolver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/cart.php' => config_path('cart.php'),
        ], 'cart-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'cart-migrations');

        // Register login event listener for cart merge
        $this->registerLoginListener();
    }

    /**
     * Register the login event listener for cart merging.
     */
    protected function registerLoginListener(): void
    {
        if (!config('cart.associate.merge_on_login', true)) {
            return;
        }

        Event::listen(Login::class, function (Login $event) {
            /** @var CartManager $cart */
            $cart = $this->app->make(CartManager::class);
            $cart->handleLogin($event->user);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            CartManager::class,
            'cart',
            StorageDriver::class,
            PriceResolver::class,
        ];
    }
}
