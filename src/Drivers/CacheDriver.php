<?php

declare(strict_types=1);

namespace Cart\Drivers;

use Cart\CartContent;
use Cart\Contracts\StorageDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache-based storage driver (Redis, Memcached, etc.).
 */
class CacheDriver implements StorageDriver
{
    /**
     * The cache store name.
     */
    protected ?string $store;

    /**
     * The cache key prefix.
     */
    protected string $prefix;

    /**
     * The TTL in minutes.
     */
    protected int $ttl;

    public function __construct()
    {
        $this->store = config('cart.drivers.cache.store');
        $this->prefix = config('cart.drivers.cache.prefix', 'cart');
        $this->ttl = (int) config('cart.drivers.cache.ttl', 60 * 24 * 7); // 7 days default
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $instance, ?string $identifier = null): ?CartContent
    {
        if ($identifier === null) {
            Log::warning('CacheDriver::get called without identifier');

            return null;
        }

        try {
            $cacheKey = $this->getCacheKey($instance, $identifier);
            $data = $this->cache()->get($cacheKey);

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
        } catch (\Throwable $e) {
            Log::warning('Cart cache read failed', [
                'driver' => 'cache',
                'instance' => $instance,
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $instance, CartContent $content, ?string $identifier = null): void
    {
        if ($identifier === null) {
            throw new \InvalidArgumentException('CacheDriver requires an identifier');
        }

        $cacheKey = $this->getCacheKey($instance, $identifier);
        $this->cache()->put($cacheKey, $content->toJson(), now()->addMinutes($this->ttl));

        // Also track this instance for flush support
        $this->trackInstance($identifier, $instance);
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $instance, ?string $identifier = null): void
    {
        if ($identifier === null) {
            return;
        }

        $cacheKey = $this->getCacheKey($instance, $identifier);
        $this->cache()->forget($cacheKey);

        $this->untrackInstance($identifier, $instance);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(?string $identifier = null): void
    {
        if ($identifier === null) {
            return;
        }

        // Get tracked instances for this identifier
        $instances = $this->getTrackedInstances($identifier);

        foreach ($instances as $instance) {
            $cacheKey = $this->getCacheKey($instance, $identifier);
            $this->cache()->forget($cacheKey);
        }

        // Clear the tracking key
        $trackingKey = $this->getTrackingKey($identifier);
        $this->cache()->forget($trackingKey);
    }

    /**
     * Get the cache key for a cart instance.
     */
    protected function getCacheKey(string $instance, string $identifier): string
    {
        return "{$this->prefix}:{$identifier}:{$instance}";
    }

    /**
     * Get the tracking key for an identifier's instances.
     */
    protected function getTrackingKey(string $identifier): string
    {
        return "{$this->prefix}:{$identifier}:_instances";
    }

    /**
     * Track an instance for an identifier (for flush support).
     */
    protected function trackInstance(string $identifier, string $instance): void
    {
        $trackingKey = $this->getTrackingKey($identifier);
        $instances = $this->getTrackedInstances($identifier);

        if (! in_array($instance, $instances, true)) {
            $instances[] = $instance;
            $this->cache()->put($trackingKey, $instances, now()->addMinutes($this->ttl));
        }
    }

    /**
     * Untrack an instance.
     */
    protected function untrackInstance(string $identifier, string $instance): void
    {
        $trackingKey = $this->getTrackingKey($identifier);
        $instances = $this->getTrackedInstances($identifier);
        $instances = array_values(array_diff($instances, [$instance]));

        if (count($instances) > 0) {
            $this->cache()->put($trackingKey, $instances, now()->addMinutes($this->ttl));
        } else {
            $this->cache()->forget($trackingKey);
        }
    }

    /**
     * Get tracked instances for an identifier.
     *
     * @return array<int, string>
     */
    protected function getTrackedInstances(string $identifier): array
    {
        $trackingKey = $this->getTrackingKey($identifier);
        $instances = $this->cache()->get($trackingKey);

        return is_array($instances) ? $instances : [];
    }

    /**
     * Get the cache store.
     */
    protected function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store($this->store);
    }
}
