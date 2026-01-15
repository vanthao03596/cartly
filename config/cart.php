<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default cart storage driver that will be used
    | by the cart library. Supported: "session", "database", "cache", "array"
    |
    */
    'driver' => env('CART_DRIVER', 'session'),

    /*
    |--------------------------------------------------------------------------
    | Default Instance
    |--------------------------------------------------------------------------
    |
    | The default cart instance name. You can have multiple instances like
    | "wishlist", "compare", etc.
    |
    */
    'default_instance' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Storage Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the storage driver settings for your application.
    |
    */
    'drivers' => [
        'session' => [
            'key' => 'cart',
        ],

        'database' => [
            'table' => 'carts',
            'connection' => null,
        ],

        'cache' => [
            'store' => null,
            'prefix' => 'cart',
            'ttl' => 60 * 24 * 7, // 7 days in minutes
        ],

        'array' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Price Resolver
    |--------------------------------------------------------------------------
    |
    | The class responsible for resolving prices at runtime. Set to null
    | to use the default BuyablePriceResolver.
    |
    */
    'price_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Cart Instances Configuration
    |--------------------------------------------------------------------------
    |
    | Configure different cart instances with their own settings.
    |
    */
    'instances' => [
        'default' => [
            'conditions' => [],
            'max_items' => null,
        ],

        'wishlist' => [
            'conditions' => [],
            'max_items' => 50,
        ],

        'compare' => [
            'max_items' => 4,
            'allow_duplicates' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tax handling for the cart. When included_in_price is true,
    | tax is extracted from prices (EU style). When false, tax is added
    | on top (US style).
    |
    */
    'tax' => [
        'enabled' => true,
        'rate' => 0,
        'included_in_price' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Price Formatting
    |--------------------------------------------------------------------------
    |
    | Configure how prices are formatted for display.
    |
    */
    'format' => [
        'decimals' => 2,
        'decimal_separator' => '.',
        'thousand_separator' => ',',
        'currency_symbol' => '$',
        'currency_position' => 'before', // 'before' or 'after'
    ],

    /*
    |--------------------------------------------------------------------------
    | User Association
    |--------------------------------------------------------------------------
    |
    | Configure how the cart handles user authentication and cart merging
    | when guests log in.
    |
    */
    'associate' => [
        'auto_associate' => true,
        'merge_on_login' => true,
        'merge_strategy' => 'combine', // 'keep_guest', 'keep_user', 'combine'
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable cart events.
    |
    */
    'events' => [
        'enabled' => true,
    ],
];
