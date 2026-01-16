<?php

declare(strict_types=1);

namespace Cart\Drivers;

use Cart\CartContent;
use Cart\Contracts\StorageDriver;

/**
 * In-memory storage driver for testing.
 */
class ArrayDriver implements StorageDriver
{
    /**
     * In-memory storage.
     *
     * @var array<string, array<string, CartContent>>
     */
    protected array $storage = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $instance, ?string $identifier = null): ?CartContent
    {
        $identifier = $identifier ?? 'default';

        return $this->storage[$identifier][$instance] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $instance, CartContent $content, ?string $identifier = null): void
    {
        $identifier = $identifier ?? 'default';

        if (! isset($this->storage[$identifier])) {
            $this->storage[$identifier] = [];
        }

        $this->storage[$identifier][$instance] = $content;
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $instance, ?string $identifier = null): void
    {
        $identifier = $identifier ?? 'default';

        unset($this->storage[$identifier][$instance]);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(?string $identifier = null): void
    {
        if ($identifier === null) {
            $this->storage = [];
        } else {
            unset($this->storage[$identifier]);
        }
    }

    /**
     * Get all stored content (for testing/debugging).
     *
     * @return array<string, array<string, CartContent>>
     */
    public function all(): array
    {
        return $this->storage;
    }
}
