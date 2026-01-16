<?php

declare(strict_types=1);

namespace Cart\Tests;

use Cart\CartServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CartServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Cart' => \Cart\Cart::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Set default cart configuration
        $app['config']->set('cart.driver', 'array');
        $app['config']->set('cart.default_instance', 'default');
        $app['config']->set('cart.instances', [
            'default' => ['conditions' => [], 'max_items' => null],
            'wishlist' => ['conditions' => [], 'max_items' => 50],
            'compare' => ['max_items' => 4],
        ]);
        $app['config']->set('cart.tax', [
            'enabled' => true,
            'rate' => 0,
            'included_in_price' => false,
        ]);
        $app['config']->set('cart.format', [
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'currency_symbol' => '$',
            'currency_position' => 'before',
        ]);
        $app['config']->set('cart.associate', [
            'auto_associate' => false,
            'merge_on_login' => true,
            'merge_strategy' => 'combine',
        ]);
        $app['config']->set('cart.events', ['enabled' => true]);
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
