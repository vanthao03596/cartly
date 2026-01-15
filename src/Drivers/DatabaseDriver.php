<?php

declare(strict_types=1);

namespace Cart\Drivers;

use Cart\CartContent;
use Cart\Contracts\StorageDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Database storage driver for persistent carts.
 */
class DatabaseDriver implements StorageDriver
{
    /**
     * The database table name.
     */
    protected string $table;

    /**
     * The database connection name.
     */
    protected ?string $connection;

    public function __construct()
    {
        $this->table = config('cart.drivers.database.table', 'carts');
        $this->connection = config('cart.drivers.database.connection');
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $instance, ?string $identifier = null): ?CartContent
    {
        if ($identifier === null) {
            Log::warning('DatabaseDriver::get called without identifier');

            return null;
        }

        try {
            $row = $this->query()
                ->where('instance', $instance)
                ->where('identifier', $identifier)
                ->first();

            if ($row === null) {
                return null;
            }

            $content = is_string($row->content) ? $row->content : '';

            return CartContent::fromJson($content);
        } catch (\Throwable $e) {
            Log::warning('Cart storage read failed', [
                'driver' => 'database',
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
            throw new \InvalidArgumentException('DatabaseDriver requires an identifier');
        }

        $this->query()->updateOrInsert(
            [
                'instance' => $instance,
                'identifier' => $identifier,
            ],
            [
                'content' => $content->toJson(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $instance, ?string $identifier = null): void
    {
        if ($identifier === null) {
            return;
        }

        $this->query()
            ->where('instance', $instance)
            ->where('identifier', $identifier)
            ->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function flush(?string $identifier = null): void
    {
        if ($identifier === null) {
            return;
        }

        $this->query()
            ->where('identifier', $identifier)
            ->delete();
    }

    /**
     * Get a query builder instance for the cart table.
     */
    protected function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection($this->connection)->table($this->table);
    }
}
