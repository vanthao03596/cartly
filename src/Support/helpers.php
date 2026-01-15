<?php

declare(strict_types=1);

use Cart\CartInstance;
use Cart\CartManager;

if (!function_exists('cart')) {
    /**
     * Get the cart manager or a specific instance.
     */
    function cart(?string $instance = null): CartInstance|CartManager
    {
        /** @var CartManager $manager */
        $manager = app(CartManager::class);

        if ($instance !== null) {
            return $manager->instance($instance);
        }

        return $manager;
    }
}

if (!function_exists('cart_count')) {
    /**
     * Get the total quantity of items in the cart.
     */
    function cart_count(?string $instance = null): int
    {
        return cart($instance ?? 'default')->count();
    }
}

if (!function_exists('cart_subtotal')) {
    /**
     * Get the cart subtotal.
     *
     * @param string|null $instance Cart instance name
     * @param bool $formatted Whether to return formatted string
     * @return int|string Cents or formatted string
     */
    function cart_subtotal(?string $instance = null, bool $formatted = false): int|string
    {
        $cents = cart($instance ?? 'default')->subtotal();

        return $formatted ? format_price($cents) : $cents;
    }
}

if (!function_exists('cart_total')) {
    /**
     * Get the cart total.
     *
     * @param string|null $instance Cart instance name
     * @param bool $formatted Whether to return formatted string
     * @return int|string Cents or formatted string
     */
    function cart_total(?string $instance = null, bool $formatted = false): int|string
    {
        $cents = cart($instance ?? 'default')->total();

        return $formatted ? format_price($cents) : $cents;
    }
}

if (!function_exists('format_price')) {
    /**
     * Format a price in cents to a display string.
     *
     * @param int $cents The price in cents
     * @param string|null $currency Optional currency code
     */
    function format_price(int $cents, ?string $currency = null): string
    {
        $decimals = (int) config('cart.format.decimals', 2);
        $decimalSeparator = config('cart.format.decimal_separator', '.');
        $thousandSeparator = config('cart.format.thousand_separator', ',');
        $currencySymbol = config('cart.format.currency_symbol', '$');
        $currencyPosition = config('cart.format.currency_position', 'before');

        // Convert cents to decimal
        $amount = $cents / 100;

        // Format the number
        $formatted = number_format($amount, $decimals, $decimalSeparator, $thousandSeparator);

        // Apply currency symbol
        if ($currencyPosition === 'before') {
            return $currencySymbol . $formatted;
        }

        return $formatted . $currencySymbol;
    }
}

if (!function_exists('cents_to_dollars')) {
    /**
     * Convert cents to dollars (for external APIs).
     */
    function cents_to_dollars(int $cents): float
    {
        return $cents / 100;
    }
}

if (!function_exists('dollars_to_cents')) {
    /**
     * Convert dollars to cents (from user input).
     */
    function dollars_to_cents(float $dollars): int
    {
        return (int) round($dollars * 100);
    }
}
