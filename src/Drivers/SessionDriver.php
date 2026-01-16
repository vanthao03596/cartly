<?php

declare(strict_types=1);

namespace Cart\Drivers;

use Cart\CartContent;
use Cart\Contracts\StorageDriver;
use Illuminate\Support\Facades\Session;

/**
 * Session-based storage driver for guest users.
 */
class SessionDriver implements StorageDriver
{
    /**
     * The session key prefix.
     */
    protected string $key;

    public function __construct()
    {
        $this->key = config('cart.drivers.session.key', 'cart');
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $instance, ?string $identifier = null): ?CartContent
    {
        // Identifier is ignored for session driver (session scopes data)
        $sessionKey = $this->getSessionKey($instance);
        $data = Session::get($sessionKey);

        if ($data === null) {
            return null;
        }

        if (is_string($data)) {
            return CartContent::fromJson($data);
        }

        if (is_array($data)) {
            return CartContent::fromArray($data);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $instance, CartContent $content, ?string $identifier = null): void
    {
        $sessionKey = $this->getSessionKey($instance);
        Session::put($sessionKey, $content->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $instance, ?string $identifier = null): void
    {
        $sessionKey = $this->getSessionKey($instance);
        Session::forget($sessionKey);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(?string $identifier = null): void
    {
        // Remove all cart-related session data
        $allSession = Session::all();

        foreach (array_keys($allSession) as $key) {
            if (str_starts_with((string) $key, $this->key.'.')) {
                Session::forget($key);
            }
        }
    }

    /**
     * Get the full session key for an instance.
     */
    protected function getSessionKey(string $instance): string
    {
        return "{$this->key}.{$instance}";
    }
}
