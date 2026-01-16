<?php

declare(strict_types=1);

namespace Cart\Contracts;

use Cart\CartContent;

interface StorageDriver
{
    /**
     * Get cart content from storage.
     *
     * @param  string  $instance  The cart instance name
     * @param  string|null  $identifier  User identifier (user_id or session_id)
     */
    public function get(string $instance, ?string $identifier = null): ?CartContent;

    /**
     * Store cart content.
     *
     * @param  string  $instance  The cart instance name
     * @param  CartContent  $content  The cart content to store
     * @param  string|null  $identifier  User identifier (user_id or session_id)
     */
    public function put(string $instance, CartContent $content, ?string $identifier = null): void;

    /**
     * Remove cart content for a specific instance.
     *
     * @param  string  $instance  The cart instance name
     * @param  string|null  $identifier  User identifier (user_id or session_id)
     */
    public function forget(string $instance, ?string $identifier = null): void;

    /**
     * Remove all cart content for an identifier (all instances).
     *
     * @param  string|null  $identifier  User identifier (user_id or session_id)
     */
    public function flush(?string $identifier = null): void;
}
